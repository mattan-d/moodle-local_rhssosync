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
 * Conflicts report (existing unmanaged users colliding with RHSSO usernames).
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rhssosync\local\report_repository;

admin_externalpage_setup('local_rhssosync_conflicts');

$context = context_system::instance();
require_capability('local/rhssosync:viewreports', $context);

$pageurl = new moodle_url('/local/rhssosync/conflicts.php');

// Allow an administrator to dismiss (mark resolved) a conflict after review.
$resolve = optional_param('resolve', 0, PARAM_INT);
if ($resolve && confirm_sesskey()) {
    require_capability('local/rhssosync:manage', $context);
    $conflict = $DB->get_record('local_rhssosync_conflicts', ['id' => $resolve]);
    if ($conflict) {
        $conflict->resolved = 1;
        $conflict->timemodified = time();
        $DB->update_record('local_rhssosync_conflicts', $conflict);
    }
    redirect(
        $pageurl,
        get_string('conflictresolved', 'local_rhssosync'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$reports = new report_repository();
$conflicts = $reports->get_open_conflicts();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conflicts', 'local_rhssosync'));
echo $OUTPUT->box(get_string('conflicts_help', 'local_rhssosync'));

$canmanage = has_capability('local/rhssosync:manage', $context);

if (!$conflicts) {
    echo $OUTPUT->notification(
        get_string('noconflicts', 'local_rhssosync'),
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    $table = new html_table();
    $table->head = [
        get_string('username', 'local_rhssosync'),
        get_string('reason', 'local_rhssosync'),
        get_string('moodleuser', 'local_rhssosync'),
        get_string('time'),
    ];
    if ($canmanage) {
        $table->head[] = get_string('actions');
    }
    $table->attributes['class'] = 'table table-sm generaltable';

    foreach ($conflicts as $c) {
        $userlink = '-';
        if ($c->moodleuserid) {
            $user = $DB->get_record('user', ['id' => $c->moodleuserid], 'id, firstname, lastname');
            if ($user) {
                $userlink = html_writer::link(
                    new moodle_url('/user/profile.php', ['id' => $user->id]),
                    s(fullname($user))
                );
            }
        }
        $row = [
            s($c->username),
            get_string('reason_' . $c->reason, 'local_rhssosync'),
            $userlink,
            userdate($c->timemodified),
        ];
        if ($canmanage) {
            $row[] = html_writer::link(
                new moodle_url($pageurl, ['resolve' => $c->id, 'sesskey' => sesskey()]),
                get_string('markresolved', 'local_rhssosync'),
                ['class' => 'btn btn-sm btn-secondary']
            );
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
