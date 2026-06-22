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

namespace local_rhssosync\task;

use local_rhssosync\local\synchronizer;
use local_rhssosync\local\sync_result;

/**
 * Scheduled synchronisation task (default: every 12 hours).
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduled_sync_task extends \core\task\scheduled_task {
    /**
     * Human readable task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_scheduledsync', 'local_rhssosync');
    }

    /**
     * Run the scheduled synchronisation.
     *
     * @return void
     */
    public function execute() {
        $result = synchronizer::instance()->run('scheduled', false, 0);
        mtrace('local_rhssosync scheduled sync: ' . $result->status . ' - ' . $result->summary);
        if ($result->status === sync_result::STATUS_FAILED) {
            // Surface failure to the task runner so it is visible/retried.
            throw new \moodle_exception('errorsyncfailed', 'local_rhssosync', '', $result->summary);
        }
    }
}
