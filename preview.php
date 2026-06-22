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
 * Preview (dry-run) synchronisation page.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rhssosync\local\config;
use local_rhssosync\local\synchronizer;

admin_externalpage_setup('local_rhssosync_dashboard');

$context = context_system::instance();
require_capability('local/rhssosync:manage', $context);

$pageurl = new moodle_url('/local/rhssosync/preview.php');
$PAGE->set_url($pageurl);

$config = new config();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('previewsync', 'local_rhssosync'));

$run = optional_param('run', 0, PARAM_BOOL);

if (!$config->is_connection_configured() || !$config->is_group_selected()) {
    echo $OUTPUT->notification(
        get_string('previewnotready', 'local_rhssosync'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo $OUTPUT->footer();
    die;
}

if ($run && confirm_sesskey() && data_submitted()) {
    \core\session\manager::write_close();
    $result = synchronizer::instance()->run('preview', true, $USER->id);

    // Summary counts.
    echo $OUTPUT->box(format_text($result->summary, FORMAT_PLAIN));

    $summarytable = new html_table();
    $summarytable->attributes['class'] = 'table table-sm w-auto';
    $summarytable->data = [
        [get_string('received', 'local_rhssosync'), $result->received],
        [get_string('created', 'local_rhssosync'), $result->count('create')],
        [get_string('updated', 'local_rhssosync'), $result->count('update')],
        [get_string('suspended', 'local_rhssosync'), $result->count('suspend')],
        [get_string('reactivated', 'local_rhssosync'), $result->count('reactivate')],
        [get_string('skipped', 'local_rhssosync'), $result->count('skip')],
        [get_string('unchanged', 'local_rhssosync'), $result->count('unchanged')],
        [get_string('conflicts', 'local_rhssosync'), $result->count('conflict')],
        [get_string('errors', 'local_rhssosync'), $result->count('error')],
    ];
    echo html_writer::table($summarytable);

    if ($result->needsreview) {
        echo $OUTPUT->notification(
            get_string('previewneedsreview', 'local_rhssosync'),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    // Intended actions detail (creates, suspensions, conflicts first).
    $logs = $result->get_logs();
    if ($logs) {
        $table = new html_table();
        $table->head = [
            get_string('action', 'local_rhssosync'),
            get_string('username', 'local_rhssosync'),
            get_string('detail', 'local_rhssosync'),
        ];
        $table->attributes['class'] = 'table table-sm';
        foreach ($logs as $log) {
            if ($log->action === 'unchanged') {
                continue; // Keep the preview focused on intended changes.
            }
            $table->data[] = [
                s(get_string('action_' . $log->action, 'local_rhssosync')),
                s($log->username),
                s($log->detail),
            ];
        }
        if ($table->data) {
            echo $OUTPUT->heading(get_string('intendedactions', 'local_rhssosync'), 4);
            echo html_writer::table($table);
        }
    }

    echo $OUTPUT->single_button(
        new moodle_url('/local/rhssosync/index.php'),
        get_string('backtodashboard', 'local_rhssosync'),
        'get'
    );
} else {
    echo $OUTPUT->box(get_string('previewintro', 'local_rhssosync'));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'run', 'value' => 1]);
    echo html_writer::tag(
        'button',
        get_string('runpreview', 'local_rhssosync'),
        ['type' => 'submit', 'class' => 'btn btn-primary']
    );
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
