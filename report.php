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
 * Detailed view of a single synchronisation run.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rhssosync\local\report_repository;

$id = required_param('id', PARAM_INT);

admin_externalpage_setup('local_rhssosync_history');

$context = context_system::instance();
require_capability('local/rhssosync:viewreports', $context);

$PAGE->set_url(new moodle_url('/local/rhssosync/report.php', ['id' => $id]));

$reports = new report_repository();
$run = $reports->get_run($id);
if (!$run) {
    throw new \moodle_exception('errorrunnotfound', 'local_rhssosync');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rundetails', 'local_rhssosync'));

$meta = new html_table();
$meta->attributes['class'] = 'table table-sm w-auto';
$meta->data = [
    [get_string('time', 'core'), userdate($run->timestart)],
    [get_string('duration', 'local_rhssosync'), format_time(max(0, $run->timeend - $run->timestart))],
    [get_string('trigger', 'local_rhssosync'), get_string('trigger_' . $run->triggertype, 'local_rhssosync')],
    [get_string('status', 'local_rhssosync'), get_string('status_' . $run->status, 'local_rhssosync')],
    [get_string('preview', 'local_rhssosync'), ((int) $run->dryrun === 1) ? get_string('yes') : get_string('no')],
    [get_string('needsreview', 'local_rhssosync'), ((int) $run->needsreview === 1) ? get_string('yes') : get_string('no')],
    [get_string('received', 'local_rhssosync'), $run->receivedcount],
    [get_string('created', 'local_rhssosync'), $run->createdcount],
    [get_string('updated', 'local_rhssosync'), $run->updatedcount],
    [get_string('suspended', 'local_rhssosync'), $run->suspendedcount],
    [get_string('reactivated', 'local_rhssosync'), $run->reactivatedcount],
    [get_string('skipped', 'local_rhssosync'), $run->skippedcount],
    [get_string('unchanged', 'local_rhssosync'), $run->unchangedcount],
    [get_string('conflicts', 'local_rhssosync'), $run->conflictcount],
    [get_string('errors', 'local_rhssosync'), $run->errorcount],
];
echo html_writer::table($meta);

if ($run->summary) {
    echo $OUTPUT->box(format_text($run->summary, FORMAT_PLAIN));
}

$logs = $reports->get_run_logs($id);
if ($logs) {
    echo $OUTPUT->heading(get_string('peruseractions', 'local_rhssosync'), 4);
    $table = new html_table();
    $table->head = [
        get_string('action', 'local_rhssosync'),
        get_string('username', 'local_rhssosync'),
        get_string('detail', 'local_rhssosync'),
    ];
    $table->attributes['class'] = 'table table-sm generaltable';
    foreach ($logs as $log) {
        $table->data[] = [
            get_string('action_' . $log->action, 'local_rhssosync'),
            s((string) $log->username),
            s((string) $log->detail),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->single_button(
    new moodle_url('/local/rhssosync/history.php'),
    get_string('backtohistory', 'local_rhssosync'),
    'get'
);

echo $OUTPUT->footer();
