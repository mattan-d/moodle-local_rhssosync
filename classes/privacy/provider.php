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

namespace local_rhssosync\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_system;

/**
 * Privacy provider for local_rhssosync.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_rhssosync_users', [
            'userid'      => 'privacy:metadata:local_rhssosync_users:userid',
            'rhssouserid' => 'privacy:metadata:local_rhssosync_users:rhssouserid',
            'status'      => 'privacy:metadata:local_rhssosync_users:status',
            'lastseen'    => 'privacy:metadata:local_rhssosync_users:lastseen',
        ], 'privacy:metadata:local_rhssosync_users');

        $collection->add_database_table('local_rhssosync_log', [
            'userid'      => 'privacy:metadata:local_rhssosync_log:userid',
            'username'    => 'privacy:metadata:local_rhssosync_log:username',
            'rhssouserid' => 'privacy:metadata:local_rhssosync_log:rhssouserid',
            'action'      => 'privacy:metadata:local_rhssosync_log:action',
            'timecreated' => 'privacy:metadata:local_rhssosync_log:timecreated',
        ], 'privacy:metadata:local_rhssosync_log');

        $collection->add_database_table('local_rhssosync_conflicts', [
            'moodleuserid' => 'privacy:metadata:local_rhssosync_conflicts:moodleuserid',
            'username'     => 'privacy:metadata:local_rhssosync_conflicts:username',
            'rhssouserid'  => 'privacy:metadata:local_rhssosync_conflicts:rhssouserid',
        ], 'privacy:metadata:local_rhssosync_conflicts');

        // The plugin transmits the username/sAMAccountName to RHSSO in order to
        // identify the directory account; no data is stored remotely by Moodle.
        $collection->add_external_location_link('rhsso', [
            'username' => 'privacy:metadata:rhsso:username',
        ], 'privacy:metadata:rhsso');

        return $collection;
    }

    /**
     * Get the contexts that contain data for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT 1
                  FROM {local_rhssosync_users} m
                 WHERE m.userid = :u1
                 UNION
                SELECT 1
                  FROM {local_rhssosync_log} l
                 WHERE l.userid = :u2
                 UNION
                SELECT 1
                  FROM {local_rhssosync_conflicts} c
                 WHERE c.moodleuserid = :u3";
        $params = ['u1' => $userid, 'u2' => $userid, 'u3' => $userid];

        global $DB;
        if ($DB->record_exists_sql($sql, $params)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_rhssosync_users}", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_rhssosync_log} WHERE userid <> 0", []);
        $userlist->add_from_sql(
            'moodleuserid',
            "SELECT moodleuserid FROM {local_rhssosync_conflicts} WHERE moodleuserid <> 0",
            []
        );
    }

    /**
     * Export all user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $hassystem = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $hassystem = true;
                break;
            }
        }
        if (!$hassystem) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();
        $subcontext = [get_string('pluginname', 'local_rhssosync')];

        $mappings = $DB->get_records('local_rhssosync_users', ['userid' => $userid]);
        if ($mappings) {
            $data = [];
            foreach ($mappings as $m) {
                $data[] = (object) [
                    'rhssouserid' => $m->rhssouserid,
                    'groupid'     => $m->groupid,
                    'status'      => $m->status,
                    'lastseen'    => $m->lastseen ? transform::datetime($m->lastseen) : null,
                ];
            }
            writer::with_context($context)->export_data(
                array_merge($subcontext, [get_string('managedusers', 'local_rhssosync')]),
                (object) ['mappings' => $data]
            );
        }

        $logs = $DB->get_records('local_rhssosync_log', ['userid' => $userid], 'timecreated ASC');
        if ($logs) {
            $data = [];
            foreach ($logs as $l) {
                $data[] = (object) [
                    'action'      => $l->action,
                    'username'    => $l->username,
                    'detail'      => $l->detail,
                    'timecreated' => transform::datetime($l->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                array_merge($subcontext, [get_string('synchistory', 'local_rhssosync')]),
                (object) ['logs' => $data]
            );
        }

        $conflicts = $DB->get_records('local_rhssosync_conflicts', ['moodleuserid' => $userid]);
        if ($conflicts) {
            $data = [];
            foreach ($conflicts as $c) {
                $data[] = (object) [
                    'username'    => $c->username,
                    'rhssouserid' => $c->rhssouserid,
                    'reason'      => $c->reason,
                ];
            }
            writer::with_context($context)->export_data(
                array_merge($subcontext, [get_string('conflicts', 'local_rhssosync')]),
                (object) ['conflicts' => $data]
            );
        }
    }

    /**
     * Delete all data for all users in the given context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if (!$context instanceof context_system) {
            return;
        }
        // The mapping table is operational state for the plugin; clearing all
        // contexts removes the user-identifying rows from logs and conflicts and
        // detaches the mapping from the Moodle user where applicable.
        $DB->delete_records('local_rhssosync_log');
        $DB->delete_records('local_rhssosync_conflicts');
        $DB->delete_records('local_rhssosync_users');
    }

    /**
     * Delete data for the given user in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $DB->delete_records('local_rhssosync_users', ['userid' => $userid]);
                $DB->delete_records('local_rhssosync_log', ['userid' => $userid]);
                $DB->delete_records('local_rhssosync_conflicts', ['moodleuserid' => $userid]);
            }
        }
    }

    /**
     * Delete data for the approved list of users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_rhssosync_users', "userid $insql", $params);
        $DB->delete_records_select('local_rhssosync_log', "userid $insql", $params);
        $DB->delete_records_select('local_rhssosync_conflicts', "moodleuserid $insql", $params);
    }
}
