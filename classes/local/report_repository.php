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
 * Persistence for synchronisation run reports, per-user logs and conflicts.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_repository {
    /** @var string */
    const TABLE_RUNS = 'local_rhssosync_runs';
    /** @var string */
    const TABLE_LOG = 'local_rhssosync_log';
    /** @var string */
    const TABLE_CONFLICTS = 'local_rhssosync_conflicts';

    /**
     * Persist a completed run plus its logs and conflicts in a transaction.
     *
     * @param sync_result $result
     * @return int The new run id.
     */
    public function save(sync_result $result) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $run = $result->to_run_record();
        $runid = $DB->insert_record(self::TABLE_RUNS, $run);

        $now = time();
        foreach ($result->get_logs() as $log) {
            $DB->insert_record(self::TABLE_LOG, (object) [
                'runid'       => $runid,
                'action'      => $log->action,
                'rhssouserid' => $log->rhssouserid !== '' ? $log->rhssouserid : null,
                'userid'      => $log->userid,
                'username'    => $log->username !== '' ? $log->username : null,
                'detail'      => $log->detail !== '' ? $log->detail : null,
                'timecreated' => $now,
            ]);
        }

        // Conflicts are persisted for actual runs only; previews are transient.
        if (!$result->dryrun) {
            foreach ($result->get_conflicts() as $conflict) {
                $existing = $DB->get_record(self::TABLE_CONFLICTS, [
                    'username' => $conflict->username,
                    'resolved' => 0,
                ]);
                if ($existing) {
                    $existing->runid = $runid;
                    $existing->rhssouserid = $conflict->rhssouserid;
                    $existing->moodleuserid = $conflict->moodleuserid;
                    $existing->reason = $conflict->reason;
                    $existing->timemodified = $now;
                    $DB->update_record(self::TABLE_CONFLICTS, $existing);
                } else {
                    $DB->insert_record(self::TABLE_CONFLICTS, (object) [
                        'runid'        => $runid,
                        'username'     => $conflict->username,
                        'rhssouserid'  => $conflict->rhssouserid,
                        'moodleuserid' => $conflict->moodleuserid,
                        'reason'       => $conflict->reason,
                        'resolved'     => 0,
                        'timecreated'  => $now,
                        'timemodified' => $now,
                    ]);
                }
            }
        }

        $transaction->allow_commit();
        return $runid;
    }

    /**
     * Recent runs for the history page.
     *
     * @param int $limit
     * @return \stdClass[]
     */
    public function get_recent_runs($limit = 50) {
        global $DB;
        return $DB->get_records(self::TABLE_RUNS, null, 'timestart DESC', '*', 0, $limit);
    }

    /**
     * A single run.
     *
     * @param int $runid
     * @return \stdClass|false
     */
    public function get_run($runid) {
        global $DB;
        return $DB->get_record(self::TABLE_RUNS, ['id' => (int) $runid]);
    }

    /**
     * The most recent finished run, if any.
     *
     * @return \stdClass|false
     */
    public function get_last_run() {
        global $DB;
        $runs = $DB->get_records(self::TABLE_RUNS, null, 'timestart DESC', '*', 0, 1);
        return $runs ? reset($runs) : false;
    }

    /**
     * Log entries for a run.
     *
     * @param int $runid
     * @return \stdClass[]
     */
    public function get_run_logs($runid) {
        global $DB;
        return $DB->get_records(self::TABLE_LOG, ['runid' => (int) $runid], 'id ASC');
    }

    /**
     * Open (unresolved) conflicts.
     *
     * @return \stdClass[]
     */
    public function get_open_conflicts() {
        global $DB;
        return $DB->get_records(self::TABLE_CONFLICTS, ['resolved' => 0], 'timemodified DESC');
    }

    /**
     * Count open conflicts.
     *
     * @return int
     */
    public function count_open_conflicts() {
        global $DB;
        return $DB->count_records(self::TABLE_CONFLICTS, ['resolved' => 0]);
    }

    /**
     * Remove run reports (and dependent logs) older than the retention period.
     *
     * @param int $retentiondays Days to keep (0 disables cleanup).
     * @return int Number of runs removed.
     */
    public function purge_old($retentiondays) {
        global $DB;
        $retentiondays = (int) $retentiondays;
        if ($retentiondays <= 0) {
            return 0;
        }
        $cutoff = time() - ($retentiondays * DAYSECS);
        $oldruns = $DB->get_records_select(self::TABLE_RUNS, 'timestart < :cutoff', ['cutoff' => $cutoff], '', 'id');
        if (!$oldruns) {
            return 0;
        }
        $ids = array_keys($oldruns);
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(self::TABLE_LOG, "runid $insql", $params);
        $DB->delete_records_select(self::TABLE_RUNS, "id $insql", $params);
        // Resolved conflicts older than the cutoff can also go.
        $DB->delete_records_select(self::TABLE_CONFLICTS, 'resolved = 1 AND timemodified < :cutoff', ['cutoff' => $cutoff]);
        return count($ids);
    }
}
