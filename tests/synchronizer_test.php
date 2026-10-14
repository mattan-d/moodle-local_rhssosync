<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_rhssosync;

use local_rhssosync\local\config;
use local_rhssosync\local\sync_result;
use local_rhssosync\local\synchronizer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/rhssosync/tests/fixtures/mock_rhsso_client.php');

/**
 * Integration tests for the synchronisation engine.
 *
 * @package    local_rhssosync
 * @covers     \local_rhssosync\local\synchronizer
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class synchronizer_test extends \advanced_testcase {
    /**
     * Configure the plugin for a working run.
     *
     * @param int $maxsuspend
     * @return void
     */
    protected function configure($maxsuspend = 25): void {
        set_config('baseurl', 'https://sso.example.org', 'local_rhssosync');
        set_config('realm', 'ADUsers', 'local_rhssosync');
        set_config('clientid', 'moodle-sync', 'local_rhssosync');
        set_config('clientsecret', 'secret', 'local_rhssosync');
        // Prefer the dedicated groups table when available (post-1.1.1).
        global $DB;
        if ($DB->get_manager()->table_exists('local_rhssosync_groups')) {
            $DB->delete_records('local_rhssosync_groups');
            $now = time();
            $DB->insert_record('local_rhssosync_groups', (object) [
                'groupid'      => 'group-1',
                'grouppath'    => '/ADUsers/Staff',
                'sortorder'    => 0,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        } else {
            set_config('selectedgroupid', 'group-1', 'local_rhssosync');
            set_config('selectedgrouppath', '/ADUsers/Staff', 'local_rhssosync');
        }
        set_config('usernamesource', 'username', 'local_rhssosync');
        set_config('updateexisting', 1, 'local_rhssosync');
        set_config('updateemail', 1, 'local_rhssosync');
        set_config('maxsuspendpercent', $maxsuspend, 'local_rhssosync');
    }

    /**
     * Build a synchronizer using the mock client.
     *
     * @param mock_rhsso_client $client
     * @return synchronizer
     */
    protected function make_sync(mock_rhsso_client $client): synchronizer {
        return synchronizer::instance($client, new config());
    }

    /**
     * Build a member representation.
     *
     * @param string $id
     * @param string $username
     * @param string $email
     * @return array
     */
    protected function member($id, $username, $email): array {
        return [
            'id'        => $id,
            'username'  => $username,
            'firstName' => ucfirst($username),
            'lastName'  => 'Test',
            'email'     => $email,
        ];
    }

    /**
     * New members are created as managed oauth2 users.
     */
    public function test_creates_managed_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure();

        $client = new mock_rhsso_client();
        $client->members = [$this->member('u1', 'alice', 'alice@example.org')];

        $result = $this->make_sync($client)->run('adhoc', false, 0);

        $this->assertSame(sync_result::STATUS_SUCCESS, $result->status);
        $this->assertSame(1, $result->count(sync_result::ACTION_CREATE));

        $user = $DB->get_record('user', ['username' => 'alice']);
        $this->assertNotEmpty($user);
        $this->assertSame('oauth2', $user->auth);
        $this->assertEquals(0, $user->suspended);

        $mapping = $DB->get_record('local_rhssosync_users', ['rhssouserid' => 'u1']);
        $this->assertNotEmpty($mapping);
        $this->assertEquals(1, $mapping->managed);
        $this->assertEquals($user->id, $mapping->userid);
    }

    /**
     * Running twice with the same data changes nothing the second time.
     */
    public function test_idempotent(): void {
        $this->resetAfterTest();
        $this->configure();

        $client = new mock_rhsso_client();
        $client->members = [$this->member('u1', 'alice', 'alice@example.org')];

        $this->make_sync($client)->run('adhoc', false, 0);
        $second = $this->make_sync($client)->run('adhoc', false, 0);

        $this->assertSame(0, $second->count(sync_result::ACTION_CREATE));
        $this->assertSame(0, $second->count(sync_result::ACTION_UPDATE));
        $this->assertSame(1, $second->count(sync_result::ACTION_UNCHANGED));
    }

    /**
     * An existing unmanaged user with the same username is a conflict and is
     * never modified, adopted or suspended.
     */
    public function test_existing_user_conflict(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure();

        $existing = $this->getDataGenerator()->create_user([
            'username' => 'bob',
            'auth'     => 'manual',
            'email'    => 'bob@example.org',
        ]);

        $client = new mock_rhsso_client();
        $client->members = [$this->member('u2', 'bob', 'bob@example.org')];

        $result = $this->make_sync($client)->run('adhoc', false, 0);

        $this->assertSame(1, $result->count(sync_result::ACTION_CONFLICT));
        $this->assertSame(0, $result->count(sync_result::ACTION_CREATE));

        // The existing user is untouched and not managed.
        $reloaded = $DB->get_record('user', ['id' => $existing->id]);
        $this->assertSame('manual', $reloaded->auth);
        $this->assertEquals(0, $reloaded->suspended);
        $this->assertFalse($DB->record_exists('local_rhssosync_users', ['userid' => $existing->id]));
        $this->assertTrue($DB->record_exists('local_rhssosync_conflicts', ['username' => 'bob', 'resolved' => 0]));
    }

    /**
     * A managed user that leaves the group is suspended (within threshold).
     */
    public function test_suspends_when_left_group(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure(25);

        $client = new mock_rhsso_client();
        $client->members = [
            $this->member('u1', 'alice', 'alice@example.org'),
            $this->member('u2', 'bob', 'bob@example.org'),
            $this->member('u3', 'carol', 'carol@example.org'),
            $this->member('u4', 'dave', 'dave@example.org'),
        ];
        $this->make_sync($client)->run('adhoc', false, 0);

        // Dave leaves: 1 of 4 = 25%, not above the 25% threshold.
        array_pop($client->members);
        $result = $this->make_sync($client)->run('adhoc', false, 0);

        $this->assertSame(1, $result->count(sync_result::ACTION_SUSPEND));
        $this->assertFalse($result->needsreview);

        $dave = $DB->get_record('user', ['username' => 'dave']);
        $this->assertEquals(1, $dave->suspended);
        $mapping = $DB->get_record('local_rhssosync_users', ['rhssouserid' => 'u4']);
        $this->assertEquals(1, $mapping->suspendedbyplugin);
        $this->assertSame('suspended', $mapping->status);
    }

    /**
     * A user the plugin suspended is reactivated when they return.
     */
    public function test_reactivates_on_return(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure(25);

        $client = new mock_rhsso_client();
        $all = [
            $this->member('u1', 'alice', 'alice@example.org'),
            $this->member('u2', 'bob', 'bob@example.org'),
            $this->member('u3', 'carol', 'carol@example.org'),
            $this->member('u4', 'dave', 'dave@example.org'),
        ];
        $client->members = $all;
        $this->make_sync($client)->run('adhoc', false, 0);

        $client->members = array_slice($all, 0, 3); // Dave leaves.
        $this->make_sync($client)->run('adhoc', false, 0);

        $client->members = $all; // Dave returns.
        $result = $this->make_sync($client)->run('adhoc', false, 0);

        $this->assertSame(1, $result->count(sync_result::ACTION_REACTIVATE));
        $dave = $DB->get_record('user', ['username' => 'dave']);
        $this->assertEquals(0, $dave->suspended);
        $mapping = $DB->get_record('local_rhssosync_users', ['rhssouserid' => 'u4']);
        $this->assertEquals(0, $mapping->suspendedbyplugin);
        $this->assertSame('active', $mapping->status);
    }

    /**
     * An admin-suspended managed user (not suspended by the plugin) is not
     * reactivated.
     */
    public function test_does_not_reactivate_admin_suspension(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure();

        $client = new mock_rhsso_client();
        $client->members = [$this->member('u1', 'alice', 'alice@example.org')];
        $this->make_sync($client)->run('adhoc', false, 0);

        // Admin suspends alice directly; mapping reflects it was not the plugin.
        $alice = $DB->get_record('user', ['username' => 'alice']);
        $DB->set_field('user', 'suspended', 1, ['id' => $alice->id]);

        $result = $this->make_sync($client)->run('adhoc', false, 0);

        $this->assertSame(0, $result->count(sync_result::ACTION_REACTIVATE));
        $reloaded = $DB->get_record('user', ['id' => $alice->id]);
        $this->assertEquals(1, $reloaded->suspended);
    }

    /**
     * A failed/incomplete retrieval never suspends anyone.
     */
    public function test_partial_sync_protection(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure(100);

        $client = new mock_rhsso_client();
        $client->members = [$this->member('u1', 'alice', 'alice@example.org')];
        $this->make_sync($client)->run('adhoc', false, 0);

        // Retrieval now fails entirely.
        $client->throw = true;
        $result = $this->make_sync($client)->run('adhoc', false, 0);

        $this->assertSame(sync_result::STATUS_FAILED, $result->status);
        $this->assertSame(0, $result->count(sync_result::ACTION_SUSPEND));

        $alice = $DB->get_record('user', ['username' => 'alice']);
        $this->assertEquals(0, $alice->suspended);
    }

    /**
     * Exceeding the maximum suspension percentage withholds all suspensions.
     */
    public function test_suspension_threshold_withholds(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure(25);

        $client = new mock_rhsso_client();
        $client->members = [
            $this->member('u1', 'alice', 'alice@example.org'),
            $this->member('u2', 'bob', 'bob@example.org'),
        ];
        $this->make_sync($client)->run('adhoc', false, 0);

        // Everyone leaves: 2 of 2 = 100% > 25%.
        $client->members = [];
        $result = $this->make_sync($client)->run('adhoc', false, 0);

        $this->assertTrue($result->needsreview);
        $this->assertSame(0, $result->count(sync_result::ACTION_SUSPEND));
        $this->assertSame(2, $result->count(sync_result::ACTION_SKIP));

        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['username' => 'alice']));
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['username' => 'bob']));
    }

    /**
     * A preview run reports intended changes but writes nothing.
     */
    public function test_preview_writes_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure();

        $client = new mock_rhsso_client();
        $client->members = [$this->member('u1', 'alice', 'alice@example.org')];

        $result = $this->make_sync($client)->run('preview', true, 0);

        $this->assertSame(1, $result->count(sync_result::ACTION_CREATE));
        $this->assertFalse($DB->record_exists('user', ['username' => 'alice']));
        $this->assertFalse($DB->record_exists('local_rhssosync_users', ['rhssouserid' => 'u1']));
    }

    /**
     * The static threshold helper behaves as specified.
     */
    public function test_exceeds_threshold_helper(): void {
        $this->assertFalse(synchronizer::exceeds_threshold(0, 10, 25));
        $this->assertFalse(synchronizer::exceeds_threshold(2, 10, 25)); // 20%.
        $this->assertFalse(synchronizer::exceeds_threshold(25, 100, 25)); // Exactly 25%.
        $this->assertTrue(synchronizer::exceeds_threshold(3, 10, 25));  // 30%.
        $this->assertTrue(synchronizer::exceeds_threshold(1, 0, 25));   // No baseline.
    }
}
