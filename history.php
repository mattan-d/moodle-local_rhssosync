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

/**
 * Synchronisation history report.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rhssosync\local\report_repository;

admin_externalpage_setup('local_rhssosync_history');

$context = context_system::instance();
require_capability('local/rhssosync:viewreports', $context);

$reports = new report_repository();
$runs = $reports->get_recent_runs(100);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('synchistory', 'local_rhssosync'));

if (!$runs) {
    echo $OUTPUT->notification(get_string('noruns', 'local_rhssosync'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('time'),
        get_string('trigger', 'local_rhssosync'),
        get_string('status', 'local_rhssosync'),
        get_string('received', 'local_rhssosync'),
        get_string('created', 'local_rhssosync'),
        get_string('updated', 'local_rhssosync'),
        get_string('suspended', 'local_rhssosync'),
        get_string('reactivated', 'local_rhssosync'),
        get_string('conflicts', 'local_rhssosync'),
        get_string('errors', 'local_rhssosync'),
        '',
    ];
    $table->attributes['class'] = 'table table-sm generaltable';

    foreach ($runs as $run) {
        $label = get_string('trigger_' . $run->triggertype, 'local_rhssosync');
        if ((int) $run->dryrun === 1) {
            $label .= ' (' . get_string('preview', 'local_rhssosync') . ')';
        }
        $statuscell = get_string('status_' . $run->status, 'local_rhssosync');
        if ((int) $run->needsreview === 1) {
            $statuscell .= ' ' . html_writer::tag(
                'span',
                get_string('needsreview', 'local_rhssosync'),
                ['class' => 'badge badge-warning']
            );
        }
        $table->data[] = [
            userdate($run->timestart),
            $label,
            $statuscell,
            $run->receivedcount,
            $run->createdcount,
            $run->updatedcount,
            $run->suspendedcount,
            $run->reactivatedcount,
            $run->conflictcount,
            $run->errorcount,
            html_writer::link(
                new moodle_url('/local/rhssosync/report.php', ['id' => $run->id]),
                get_string('viewdetails', 'local_rhssosync')
            ),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
