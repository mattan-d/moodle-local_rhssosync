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

use local_rhssosync\client\rhsso_client;
use local_rhssosync\http\response;
use local_rhssosync\local\config;
use local_rhssosync\local\sync_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/rhssosync/tests/fixtures/fake_http_client.php');

/**
 * Tests for the RHSSO client (pagination and search) with a fake transport.
 *
 * @package    local_rhssosync
 * @covers     \local_rhssosync\client\rhsso_client
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rhsso_client_test extends \advanced_testcase {
    /**
     * Build a configured client around a fake transport.
     *
     * @param fake_http_client $http
     * @param int $pagesize
     * @return rhsso_client
     */
    protected function make_client(fake_http_client $http, $pagesize = 2): rhsso_client {
        $config = new config([
            'baseurl'      => 'https://sso.example.org',
            'realm'        => 'ADUsers',
            'clientid'     => 'moodle-sync',
            'clientsecret' => 'secret',
            'pagesize'     => $pagesize,
        ]);
        return new rhsso_client($config, $http);
    }

    /**
     * Pagination aggregates every full page until a short page is returned.
     */
    public function test_get_group_members_paginates_completely(): void {
        $this->resetAfterTest();
        $http = new fake_http_client();
        // Page size 2: two full pages then a short page ends pagination.
        $http->queue_json([['id' => 'a'], ['id' => 'b']]);
        $http->queue_json([['id' => 'c'], ['id' => 'd']]);
        $http->queue_json([['id' => 'e']]);

        $client = $this->make_client($http, 2);
        $members = $client->get_group_members('group-1');

        $this->assertCount(5, $members);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], array_column($members, 'id'));
        // Three member pages requested (token uses post_form, not the get queue).
        $this->assertCount(3, $http->geturls);
    }

    /**
     * An exact multiple of the page size triggers one final empty page.
     */
    public function test_get_group_members_exact_multiple(): void {
        $this->resetAfterTest();
        $http = new fake_http_client();
        $http->queue_json([['id' => 'a'], ['id' => 'b']]);
        $http->queue_json([]); // Empty short page stops the loop.

        $client = $this->make_client($http, 2);
        $members = $client->get_group_members('group-1');

        $this->assertCount(2, $members);
    }

    /**
     * A failed page makes the whole retrieval throw (incomplete = untrusted).
     */
    public function test_get_group_members_throws_on_page_failure(): void {
        $this->resetAfterTest();
        $http = new fake_http_client();
        $http->queue_json([['id' => 'a'], ['id' => 'b']]);
        $http->queue_response(new response(500, 'error'));

        $client = $this->make_client($http, 2);
        $this->expectException(sync_exception::class);
        $client->get_group_members('group-1');
    }

    /**
     * A failed token request throws before any admin call.
     */
    public function test_token_failure_throws(): void {
        $this->resetAfterTest();
        $http = new fake_http_client();
        $http->tokenresponse = new response(401, 'unauthorized');

        $client = $this->make_client($http);
        $this->expectException(sync_exception::class);
        $client->get_group_members('group-1');
    }

    /**
     * Group search flattens nested subgroups.
     */
    public function test_search_groups_flattens(): void {
        $this->resetAfterTest();
        $http = new fake_http_client();
        $http->queue_json([
            [
                'id'   => 'g1',
                'name' => 'Staff',
                'path' => '/ADUsers/Staff',
                'subGroups' => [
                    ['id' => 'g2', 'name' => 'StaffAdmins', 'path' => '/ADUsers/Staff/StaffAdmins'],
                ],
            ],
        ]);

        $client = $this->make_client($http);
        $results = $client->search_groups('staff', 10);

        $paths = array_map(static function ($g) {
            return $g->path;
        }, $results);
        $this->assertContains('/ADUsers/Staff', $paths);
        $this->assertContains('/ADUsers/Staff/StaffAdmins', $paths);
    }
}
