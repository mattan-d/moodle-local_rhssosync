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
 * In-memory accumulator for a single synchronisation run.
 *
 * Pure data object with no database access, so the synchronisation logic can be
 * unit tested by inspecting the recorded actions and counters.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_result {
    /** @var string Action: user created. */
    const ACTION_CREATE = 'create';
    /** @var string Action: user updated. */
    const ACTION_UPDATE = 'update';
    /** @var string Action: user suspended. */
    const ACTION_SUSPEND = 'suspend';
    /** @var string Action: user reactivated. */
    const ACTION_REACTIVATE = 'reactivate';
    /** @var string Action: member skipped. */
    const ACTION_SKIP = 'skip';
    /** @var string Action: user unchanged. */
    const ACTION_UNCHANGED = 'unchanged';
    /** @var string Action: unmanaged conflict. */
    const ACTION_CONFLICT = 'conflict';
    /** @var string Action: error processing member. */
    const ACTION_ERROR = 'error';

    /** @var string Status: run in progress. */
    const STATUS_RUNNING = 'running';
    /** @var string Status: completed successfully. */
    const STATUS_SUCCESS = 'success';
    /** @var string Status: completed with errors. */
    const STATUS_PARTIAL = 'partial';
    /** @var string Status: run failed. */
    const STATUS_FAILED = 'failed';
    /** @var string Status: run aborted (e.g. lock not acquired). */
    const STATUS_ABORTED = 'aborted';

    /** @var string */
    public $triggertype;

    /** @var bool */
    public $dryrun;

    /** @var int */
    public $userid;

    /** @var int */
    public $timestart;

    /** @var int */
    public $timeend = 0;

    /** @var string */
    public $status = self::STATUS_RUNNING;

    /** @var bool Whether suspensions were withheld for admin review. */
    public $needsreview = false;

    /** @var int */
    public $received = 0;

    /** @var int[] Action counters keyed by action constant. */
    protected $counts = [
        self::ACTION_CREATE     => 0,
        self::ACTION_UPDATE     => 0,
        self::ACTION_SUSPEND    => 0,
        self::ACTION_REACTIVATE => 0,
        self::ACTION_SKIP       => 0,
        self::ACTION_UNCHANGED  => 0,
        self::ACTION_CONFLICT   => 0,
        self::ACTION_ERROR      => 0,
    ];

    /** @var array Recorded per-user log entries. */
    protected $logs = [];

    /** @var array Recorded conflicts. */
    protected $conflicts = [];

    /** @var string Free-text summary message. */
    public $summary = '';

    /**
     * Constructor.
     *
     * @param string $triggertype scheduled|adhoc|preview|cli
     * @param bool $dryrun
     * @param int $userid Triggering user id (0 for system).
     */
    public function __construct($triggertype, $dryrun = false, $userid = 0) {
        $this->triggertype = $triggertype;
        $this->dryrun = (bool) $dryrun;
        $this->userid = (int) $userid;
        $this->timestart = time();
    }

    /**
     * Record an action and increment its counter.
     *
     * @param string $action One of the ACTION_* constants.
     * @param string $username
     * @param int $userid Moodle user id (0 if none).
     * @param string $rhssouserid
     * @param string $detail Optional human readable detail.
     * @return void
     */
    public function record($action, $username = '', $userid = 0, $rhssouserid = '', $detail = '') {
        if (array_key_exists($action, $this->counts)) {
            $this->counts[$action]++;
        }
        $this->logs[] = (object) [
            'action'      => $action,
            'username'    => (string) $username,
            'userid'      => (int) $userid,
            'rhssouserid' => (string) $rhssouserid,
            'detail'      => (string) $detail,
        ];
    }

    /**
     * Record a conflict (and the matching log entry).
     *
     * @param string $username
     * @param int $moodleuserid
     * @param string $rhssouserid
     * @param string $reason
     * @return void
     */
    public function record_conflict($username, $moodleuserid, $rhssouserid, $reason = 'usernameexists') {
        $this->conflicts[] = (object) [
            'username'     => (string) $username,
            'moodleuserid' => (int) $moodleuserid,
            'rhssouserid'  => (string) $rhssouserid,
            'reason'       => (string) $reason,
        ];
        $this->record(self::ACTION_CONFLICT, $username, $moodleuserid, $rhssouserid, $reason);
    }

    /**
     * Get the counter value for an action.
     *
     * @param string $action
     * @return int
     */
    public function count($action) {
        return isset($this->counts[$action]) ? $this->counts[$action] : 0;
    }

    /**
     * Get the recorded per-user log entries.
     *
     * @return array Recorded log entries.
     */
    public function get_logs() {
        return $this->logs;
    }

    /**
     * Get the recorded conflicts.
     *
     * @return array Recorded conflicts.
     */
    public function get_conflicts() {
        return $this->conflicts;
    }

    /**
     * Build the database record for local_rhssosync_runs.
     *
     * @return \stdClass
     */
    public function to_run_record() {
        return (object) [
            'triggertype'      => $this->triggertype,
            'status'           => $this->status,
            'dryrun'           => $this->dryrun ? 1 : 0,
            'needsreview'      => $this->needsreview ? 1 : 0,
            'timestart'        => $this->timestart,
            'timeend'          => $this->timeend,
            'receivedcount'    => $this->received,
            'createdcount'     => $this->counts[self::ACTION_CREATE],
            'updatedcount'     => $this->counts[self::ACTION_UPDATE],
            'suspendedcount'   => $this->counts[self::ACTION_SUSPEND],
            'reactivatedcount' => $this->counts[self::ACTION_REACTIVATE],
            'skippedcount'     => $this->counts[self::ACTION_SKIP],
            'unchangedcount'   => $this->counts[self::ACTION_UNCHANGED],
            'conflictcount'    => $this->counts[self::ACTION_CONFLICT],
            'errorcount'       => $this->counts[self::ACTION_ERROR],
            'userid'           => $this->userid,
            'summary'          => $this->summary,
        ];
    }
}
