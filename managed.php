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
 * Managed users report.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('local_rhssosync_managed');

$context = context_system::instance();
require_capability('local/rhssosync:viewreports', $context);

$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$mappings = new \local_rhssosync\local\mapping_repository();
$mappings->purge_stale();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managedusers', 'local_rhssosync'));

$total = $mappings->count_live_managed();

$sql = "SELECT m.id, m.rhssouserid, m.groupid, m.managed, m.suspendedbyplugin, m.status,
               m.lastseen, m.timemodified, u.username, u.firstname, u.lastname, u.email, u.suspended
          FROM {local_rhssosync_users} m
          JOIN {user} u ON u.id = m.userid AND u.deleted = 0
         WHERE m.managed = 1
      ORDER BY m.timemodified DESC";
$records = $DB->get_records_sql($sql, [], $page * $perpage, $perpage);

if (!$records) {
    echo $OUTPUT->notification(
        get_string('nomanagedusers', 'local_rhssosync'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    $table = new html_table();
    $table->head = [
        get_string('username', 'local_rhssosync'),
        get_string('fullname'),
        get_string('email'),
        get_string('status', 'local_rhssosync'),
        get_string('suspendedbyplugin', 'local_rhssosync'),
        get_string('lastseen', 'local_rhssosync'),
    ];
    $table->attributes['class'] = 'table table-sm generaltable';
    foreach ($records as $r) {
        $status = $r->suspended ? get_string('suspended', 'local_rhssosync') : get_string('active', 'local_rhssosync');
        $table->data[] = [
            s($r->username),
            s(trim($r->firstname . ' ' . $r->lastname)),
            s($r->email),
            $status,
            $r->suspendedbyplugin ? get_string('yes') : get_string('no'),
            $r->lastseen ? userdate($r->lastseen) : '-',
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/local/rhssosync/managed.php'));
}

echo $OUTPUT->footer();
