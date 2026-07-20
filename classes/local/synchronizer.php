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

namespace local_rhssosync\local;

use local_rhssosync\client\rhsso_client;

/**
 * Core synchronisation engine.
 *
 * Pulls the direct members of the configured RHSSO group(s) and reconciles them
 * with Moodle users, creating and updating managed accounts, recording
 * conflicts for unmanaged collisions, and suspending or reactivating managed
 * users under strict safety rules.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synchronizer {
    /** @var config */
    protected $config;

    /** @var rhsso_client */
    protected $client;

    /** @var mapping_repository */
    protected $mappings;

    /** @var user_mapper */
    protected $mapper;

    /** @var report_repository */
    protected $reports;

    /**
     * Constructor.
     *
     * @param config $config
     * @param rhsso_client $client
     * @param mapping_repository $mappings
     * @param user_mapper $mapper
     * @param report_repository $reports
     */
    public function __construct(
        config $config,
        rhsso_client $client,
        mapping_repository $mappings,
        user_mapper $mapper,
        report_repository $reports
    ) {
        $this->config = $config;
        $this->client = $client;
        $this->mappings = $mappings;
        $this->mapper = $mapper;
        $this->reports = $reports;
    }

    /**
     * Build a synchronizer with default collaborators.
     *
     * @param rhsso_client|null $client Optional client (e.g. a mock in tests).
     * @param config|null $config Optional config.
     * @return synchronizer
     */
    public static function instance(?rhsso_client $client = null, ?config $config = null) {
        $config = $config ?? new config();
        $client = $client ?? new rhsso_client($config);
        return new self($config, $client, new mapping_repository(), new user_mapper($config), new report_repository());
    }

    /**
     * Execute a synchronisation run.
     *
     * @param string $triggertype scheduled|adhoc|preview|cli
     * @param bool $dryrun When true no changes are written.
     * @param int $userid Triggering user id (0 for system).
     * @return sync_result
     */
    public function run($triggertype, $dryrun = false, $userid = 0) {
        $result = new sync_result($triggertype, $dryrun, $userid);
        $lock = null;

        try {
            if (!$this->config->is_connection_configured()) {
                $result->status = sync_result::STATUS_FAILED;
                $result->summary = get_string('errornotconfigured', 'local_rhssosync');
                return $this->finish($result);
            }
            if (!$this->config->is_group_selected()) {
                $result->status = sync_result::STATUS_FAILED;
                $result->summary = get_string('errornogroup', 'local_rhssosync');
                return $this->finish($result);
            }

            // Cross-pod mutual exclusion for real runs only; previews are read only.
            if (!$dryrun) {
                $lock = $this->acquire_lock();
                if (!$lock) {
                    $result->status = sync_result::STATUS_ABORTED;
                    $result->summary = get_string('errorlock', 'local_rhssosync');
                    return $this->finish($result);
                }
            }

            $groupids = $this->config->get_group_ids();

            // Retrieve complete, fully paginated member lists for every selected
            // group. Any failure throws, which means the union is NOT trusted
            // and no suspensions may be performed.
            $membersbyid = [];
            $incomplete = [];
            try {
                foreach ($groupids as $groupid) {
                    $members = $this->client->get_group_members($groupid);
                    foreach ($members as $member) {
                        $memberid = !empty($member['id']) ? (string) $member['id'] : '';
                        if ($memberid === '') {
                            $incomplete[] = ['member' => $member, 'groupid' => $groupid];
                            continue;
                        }
                        if (!isset($membersbyid[$memberid])) {
                            $membersbyid[$memberid] = ['member' => $member, 'groupid' => $groupid];
                        }
                    }
                }
            } catch (\moodle_exception $e) {
                $result->status = sync_result::STATUS_FAILED;
                $result->summary = get_string('errorretrieval', 'local_rhssosync', $e->getMessage());
                $result->record(sync_result::ACTION_ERROR, '', 0, '', $e->getMessage());
                return $this->finish($result);
            }

            $result->received = count($membersbyid) + count($incomplete);

            // Process the present members (deduped across selected groups).
            $seen = [];
            foreach ($membersbyid as $memberid => $entry) {
                $seen[$memberid] = true;
                $this->process_member($entry['member'], $entry['groupid'], $result, $dryrun);
            }
            foreach ($incomplete as $entry) {
                $this->process_member($entry['member'], $entry['groupid'], $result, $dryrun);
            }

            // Suspension pass: the union list is complete and trusted here.
            // Any managed user not present in any selected group is a candidate.
            $this->process_suspensions($seen, $result, $dryrun);

            $result->status = $result->count(sync_result::ACTION_ERROR) > 0
                ? sync_result::STATUS_PARTIAL
                : sync_result::STATUS_SUCCESS;
            $result->summary = get_string('syncsummaryline', 'local_rhssosync', (object) [
                'received'    => $result->received,
                'created'     => $result->count(sync_result::ACTION_CREATE),
                'updated'     => $result->count(sync_result::ACTION_UPDATE),
                'suspended'   => $result->count(sync_result::ACTION_SUSPEND),
                'reactivated' => $result->count(sync_result::ACTION_REACTIVATE),
                'conflicts'   => $result->count(sync_result::ACTION_CONFLICT),
            ]);

            return $this->finish($result);
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Finalise a run: stamp the end time and persist the report.
     *
     * @param sync_result $result
     * @return sync_result
     */
    protected function finish(sync_result $result) {
        $result->timeend = time();
        try {
            $this->reports->save($result);
        } catch (\moodle_exception $e) {
            // Persisting the report must never mask the run outcome.
            debugging('local_rhssosync could not save run report: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return $result;
    }

    /**
     * Reconcile a single RHSSO member with Moodle.
     *
     * @param array $member Raw RHSSO user representation.
     * @param string $groupid
     * @param sync_result $result
     * @param bool $dryrun
     * @return void
     */
    protected function process_member(array $member, $groupid, sync_result $result, $dryrun) {
        global $DB, $CFG;

        $mapped = $this->mapper->map($member);

        if ($mapped->rhssouserid === '' || $mapped->username === '') {
            $result->record(
                sync_result::ACTION_ERROR,
                $mapped->username,
                0,
                $mapped->rhssouserid,
                get_string('errorunmappable', 'local_rhssosync')
            );
            return;
        }

        $mapping = $this->mappings->get_by_rhsso_id($mapped->rhssouserid);

        if ($mapping) {
            $this->process_existing_mapping($mapping, $mapped, $groupid, $result, $dryrun);
            return;
        }

        // No mapping: check for an existing (unmanaged) Moodle user with this
        // username. Such users are never adopted or modified.
        $existing = $DB->get_record('user', [
            'username'   => $mapped->username,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted'    => 0,
        ]);

        if ($existing) {
            $result->record_conflict($mapped->username, (int) $existing->id, $mapped->rhssouserid, 'usernameexists');
            return;
        }

        // Brand new managed user.
        if (in_array('invalidemail', $mapped->errors, true)) {
            $result->record(
                sync_result::ACTION_ERROR,
                $mapped->username,
                0,
                $mapped->rhssouserid,
                get_string('errorinvalidemail', 'local_rhssosync')
            );
            return;
        }

        $this->create_user($mapped, $groupid, $result, $dryrun);
    }

    /**
     * Handle a member that already has a managed mapping.
     *
     * @param \stdClass $mapping
     * @param \stdClass $mapped
     * @param string $groupid
     * @param sync_result $result
     * @param bool $dryrun
     * @return void
     */
    protected function process_existing_mapping(
        \stdClass $mapping,
        \stdClass $mapped,
        $groupid,
        sync_result $result,
        $dryrun
    ) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $mapping->userid]);
        if (!$user || $user->deleted) {
            $result->record(
                sync_result::ACTION_ERROR,
                $mapped->username,
                (int) $mapping->userid,
                $mapped->rhssouserid,
                get_string('errormappinguserdeleted', 'local_rhssosync')
            );
            return;
        }

        $reactivated = false;

        // Reactivate only if THIS plugin previously suspended the user.
        if ($user->suspended && (int) $mapping->suspendedbyplugin === 1) {
            if (!$dryrun) {
                $this->set_suspended($user->id, 0);
                $mapping->suspendedbyplugin = 0;
                $mapping->status = 'active';
            }
            $reactivated = true;
        }

        // Compute field changes.
        $update = ['id' => $user->id];
        if ($this->config->get_update_existing()) {
            if ($mapped->firstname !== '' && $user->firstname !== $mapped->firstname) {
                $update['firstname'] = $mapped->firstname;
            }
            if ($mapped->lastname !== '' && $user->lastname !== $mapped->lastname) {
                $update['lastname'] = $mapped->lastname;
            }
            if (
                $this->config->get_update_email()
                    && !in_array('invalidemail', $mapped->errors, true)
                    && \core_text::strtolower($user->email) !== $mapped->email
            ) {
                $update['email'] = $mapped->email;
            }
        }

        $haschanges = count($update) > 1;
        if ($haschanges && !$dryrun) {
            $this->apply_user_update($update, $mapped, $result);
        }

        if (!$dryrun) {
            $this->mappings->touch_seen($mapping, $groupid);
        }

        if ($reactivated) {
            $result->record(sync_result::ACTION_REACTIVATE, $mapped->username, (int) $user->id, $mapped->rhssouserid);
        } else if ($haschanges) {
            $result->record(
                sync_result::ACTION_UPDATE,
                $mapped->username,
                (int) $user->id,
                $mapped->rhssouserid,
                implode(',', array_diff(array_keys($update), ['id']))
            );
        } else {
            $result->record(sync_result::ACTION_UNCHANGED, $mapped->username, (int) $user->id, $mapped->rhssouserid);
        }
    }

    /**
     * Create a new managed Moodle user via the user API.
     *
     * @param \stdClass $mapped
     * @param string $groupid
     * @param sync_result $result
     * @param bool $dryrun
     * @return void
     */
    protected function create_user(\stdClass $mapped, $groupid, sync_result $result, $dryrun) {
        global $CFG;

        if ($dryrun) {
            $result->record(
                sync_result::ACTION_CREATE,
                $mapped->username,
                0,
                $mapped->rhssouserid,
                get_string('previewwouldcreate', 'local_rhssosync')
            );
            return;
        }

        require_once($CFG->dirroot . '/user/lib.php');

        $newuser = new \stdClass();
        $newuser->username = $mapped->username;
        $newuser->auth = 'oauth2';
        $newuser->firstname = $mapped->firstname;
        $newuser->lastname = $mapped->lastname;
        $newuser->email = $mapped->email;
        $newuser->confirmed = 1;
        $newuser->mnethostid = $CFG->mnet_localhost_id;
        $newuser->lang = $CFG->lang ?? 'en';
        $newuser->timezone = '99';

        try {
            // Never create or manage a local password for these accounts.
            $userid = user_create_user($newuser, false, true);
            $this->mappings->create($mapped->rhssouserid, $userid, $groupid);
            $result->record(sync_result::ACTION_CREATE, $mapped->username, (int) $userid, $mapped->rhssouserid);
        } catch (\moodle_exception $e) {
            $result->record(
                sync_result::ACTION_ERROR,
                $mapped->username,
                0,
                $mapped->rhssouserid,
                get_string('errorcreatefailed', 'local_rhssosync', $e->getMessage())
            );
        }
    }

    /**
     * Apply a field update via the user API.
     *
     * @param array $update Fields including 'id'.
     * @param \stdClass $mapped
     * @param sync_result $result
     * @return void
     */
    protected function apply_user_update(array $update, \stdClass $mapped, sync_result $result) {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        try {
            user_update_user((object) $update, false, true);
        } catch (\moodle_exception $e) {
            $result->record(
                sync_result::ACTION_ERROR,
                $mapped->username,
                (int) $update['id'],
                $mapped->rhssouserid,
                get_string('errorupdatefailed', 'local_rhssosync', $e->getMessage())
            );
        }
    }

    /**
     * Suspend managed users no longer present in any selected group, subject
     * to the maximum suspension threshold.
     *
     * @param array $seen Map of present RHSSO user ids => true (union of groups).
     * @param sync_result $result
     * @param bool $dryrun
     * @return void
     */
    protected function process_suspensions(array $seen, sync_result $result, $dryrun) {
        // All managed users: membership is defined by the union of selected
        // groups, so users from a deselected group are also candidates.
        $managed = $this->mappings->get_all_managed();
        $totalmanaged = count($managed);

        // Candidates: active managed users not present in the trusted list.
        $candidates = [];
        foreach ($managed as $mapping) {
            if (isset($seen[$mapping->rhssouserid])) {
                continue;
            }
            if ($mapping->status === 'suspended') {
                // Already suspended (idempotent), nothing to do.
                continue;
            }
            $candidates[] = $mapping;
        }

        if (empty($candidates)) {
            return;
        }

        $maxpercent = $this->config->get_max_suspend_percent();
        if (self::exceeds_threshold(count($candidates), $totalmanaged, $maxpercent)) {
            // Withhold all suspensions pending administrator review.
            $result->needsreview = true;
            $result->summary = get_string('suspendwithheldsummary', 'local_rhssosync', (object) [
                'candidates' => count($candidates),
                'total'      => $totalmanaged,
                'max'        => $maxpercent,
            ]);
            foreach ($candidates as $mapping) {
                $result->record(
                    sync_result::ACTION_SKIP,
                    '',
                    (int) $mapping->userid,
                    $mapping->rhssouserid,
                    get_string('suspendwithheld', 'local_rhssosync')
                );
            }
            return;
        }

        foreach ($candidates as $mapping) {
            $this->suspend_user($mapping, $result, $dryrun);
        }
    }

    /**
     * Suspend a single managed user.
     *
     * @param \stdClass $mapping
     * @param sync_result $result
     * @param bool $dryrun
     * @return void
     */
    protected function suspend_user(\stdClass $mapping, sync_result $result, $dryrun) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $mapping->userid]);
        if (!$user || $user->deleted) {
            $result->record(
                sync_result::ACTION_ERROR,
                '',
                (int) $mapping->userid,
                $mapping->rhssouserid,
                get_string('errormappinguserdeleted', 'local_rhssosync')
            );
            return;
        }

        if ($dryrun) {
            $result->record(
                sync_result::ACTION_SUSPEND,
                $user->username,
                (int) $user->id,
                $mapping->rhssouserid,
                get_string('previewwouldsuspend', 'local_rhssosync')
            );
            return;
        }

        if (!$user->suspended) {
            $this->set_suspended($user->id, 1);
        }
        $mapping->suspendedbyplugin = 1;
        $mapping->status = 'suspended';
        $this->mappings->update($mapping);

        $result->record(sync_result::ACTION_SUSPEND, $user->username, (int) $user->id, $mapping->rhssouserid);
    }

    /**
     * Set the suspended flag on a user through the user API.
     *
     * @param int $userid
     * @param int $suspended 1 to suspend, 0 to reactivate.
     * @return void
     */
    protected function set_suspended($userid, $suspended) {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user((object) ['id' => $userid, 'suspended' => $suspended], false, true);
        if ($suspended) {
            \core\session\manager::kill_user_sessions($userid);
        }
    }

    /**
     * Decide whether the suspension count exceeds the configured threshold.
     *
     * @param int $candidates Number of users that would be suspended.
     * @param int $totalmanaged Total managed users in the group.
     * @param int $maxpercent Maximum allowed percentage (0-100).
     * @return bool True when the run must withhold suspensions.
     */
    public static function exceeds_threshold($candidates, $totalmanaged, $maxpercent) {
        $candidates = (int) $candidates;
        $totalmanaged = (int) $totalmanaged;
        if ($candidates <= 0) {
            return false;
        }
        if ($totalmanaged <= 0) {
            // No baseline to compare against: be conservative and withhold.
            return true;
        }
        $percent = ($candidates / $totalmanaged) * 100;
        return $percent > $maxpercent;
    }

    /**
     * Acquire the cross-pod synchronisation lock.
     *
     * @return \core\lock\lock|false
     */
    protected function acquire_lock() {
        $factory = \core\lock\lock_config::get_lock_factory('local_rhssosync');
        // Wait up to 10s for an in-progress run on another pod, then give up.
        return $factory->get_lock('sync', 10, 7200);
    }
}
