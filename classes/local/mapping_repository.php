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

/**
 * Data access for the managed-user mapping table.
 *
 * A Moodle user is only ever considered "managed" when a row exists here with
 * managed = 1. The plugin never infers management from the auth method.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_repository {
    /** @var string Table name. */
    const TABLE = 'local_rhssosync_users';

    /**
     * Fetch a mapping by RHSSO user id.
     *
     * @param string $rhssouserid
     * @return \stdClass|false
     */
    public function get_by_rhsso_id($rhssouserid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['rhssouserid' => $rhssouserid]);
    }

    /**
     * Fetch a mapping by Moodle user id.
     *
     * @param int $userid
     * @return \stdClass|false
     */
    public function get_by_user_id($userid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => (int) $userid]);
    }

    /**
     * All managed mappings for a given group.
     *
     * @param string $groupid
     * @return \stdClass[] Keyed by record id.
     */
    public function get_managed_for_group($groupid) {
        global $DB;
        return $DB->get_records(self::TABLE, ['groupid' => $groupid, 'managed' => 1]);
    }

    /**
     * All managed mappings for any of the given groups.
     *
     * @param string[] $groupids
     * @return \stdClass[] Keyed by record id.
     */
    public function get_managed_for_groups(array $groupids) {
        global $DB;
        $groupids = array_values(array_filter(array_map('strval', $groupids), static function($id) {
            return $id !== '';
        }));
        if (empty($groupids)) {
            return [];
        }
        if (count($groupids) === 1) {
            return $this->get_managed_for_group($groupids[0]);
        }
        list($insql, $params) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'g');
        $params['managed'] = 1;
        $sql = "SELECT * FROM {" . self::TABLE . "} WHERE managed = :managed AND groupid {$insql}";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * All managed mappings (any group).
     *
     * @return \stdClass[] Keyed by record id.
     */
    public function get_all_managed() {
        global $DB;
        return $DB->get_records(self::TABLE, ['managed' => 1]);
    }

    /**
     * Count managed mappings whose Moodle user still exists and is not deleted.
     *
     * @return int
     */
    public function count_live_managed() {
        global $DB;
        $sql = "SELECT COUNT(1)
                  FROM {" . self::TABLE . "} m
                  JOIN {user} u ON u.id = m.userid AND u.deleted = 0
                 WHERE m.managed = 1";
        return (int) $DB->count_records_sql($sql);
    }

    /**
     * Remove mappings that no longer point at a live Moodle user.
     *
     * Moodle keeps deleted accounts (deleted = 1) and the mapping row would
     * otherwise keep counting them as managed.
     *
     * @return int Number of mappings removed.
     */
    public function purge_stale() {
        global $DB;
        $sql = "SELECT m.id
                  FROM {" . self::TABLE . "} m
             LEFT JOIN {user} u ON u.id = m.userid AND u.deleted = 0
                 WHERE u.id IS NULL";
        $ids = $DB->get_fieldset_sql($sql);
        if (empty($ids)) {
            return 0;
        }
        $DB->delete_records_list(self::TABLE, 'id', $ids);
        return count($ids);
    }

    /**
     * Delete a mapping by its record id.
     *
     * @param int $id
     * @return void
     */
    public function delete($id) {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => (int) $id]);
    }

    /**
     * Count managed mappings for a group.
     *
     * @param string $groupid
     * @return int
     */
    public function count_managed_for_group($groupid) {
        global $DB;
        return $DB->count_records(self::TABLE, ['groupid' => $groupid, 'managed' => 1]);
    }

    /**
     * Whether a Moodle user is managed by this plugin.
     *
     * @param int $userid
     * @return bool
     */
    public function is_managed($userid) {
        global $DB;
        return $DB->record_exists(self::TABLE, ['userid' => (int) $userid, 'managed' => 1]);
    }

    /**
     * Create a new managed mapping.
     *
     * @param string $rhssouserid
     * @param int $userid
     * @param string $groupid
     * @return int New record id.
     */
    public function create($rhssouserid, $userid, $groupid) {
        global $DB;
        $now = time();
        $record = (object) [
            'rhssouserid'       => $rhssouserid,
            'userid'            => (int) $userid,
            'groupid'           => $groupid,
            'managed'           => 1,
            'suspendedbyplugin' => 0,
            'status'            => 'active',
            'lastseen'          => $now,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ];
        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Persist an existing mapping record.
     *
     * @param \stdClass $record
     * @return void
     */
    public function update(\stdClass $record) {
        global $DB;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Mark a mapping as seen during the current run.
     *
     * @param \stdClass $record
     * @param string $groupid
     * @return void
     */
    public function touch_seen(\stdClass $record, $groupid) {
        $record->lastseen = time();
        $record->groupid = $groupid;
        $this->update($record);
    }

    /**
     * All mappings (used by the managed-users report and privacy provider).
     *
     * @param int $limitfrom
     * @param int $limitnum
     * @return \stdClass[]
     */
    public function get_all($limitfrom = 0, $limitnum = 0) {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'timemodified DESC', '*', $limitfrom, $limitnum);
    }
}
