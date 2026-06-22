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

/**
 * Ad-hoc synchronisation task queued by the "Sync now" action.
 *
 * The long-running synchronisation always executes here (via cron), never
 * inside the browser request.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_sync_task extends \core\task\adhoc_task {
    /**
     * Run the ad-hoc synchronisation.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();
        $userid = isset($data->userid) ? (int) $data->userid : 0;

        $result = synchronizer::instance()->run('adhoc', false, $userid);
        mtrace('local_rhssosync ad-hoc sync: ' . $result->status . ' - ' . $result->summary);
    }
}
