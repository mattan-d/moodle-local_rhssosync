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
 * RHSSO group selection page (multi-select autocomplete).
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rhssosync\client\rhsso_client;
use local_rhssosync\local\config;
use local_rhssosync\form\group_selection_form;

admin_externalpage_setup('local_rhssosync_groupselection');

$context = context_system::instance();
require_capability('local/rhssosync:manage', $context);

$config = new config();
$pageurl = new moodle_url('/local/rhssosync/groupselection.php');

// Current selection for prefill.
$current = [];
$ids = $config->get_group_ids();
$paths = $config->get_group_paths();
foreach ($ids as $i => $id) {
    $current[] = (object) [
        'id'   => $id,
        'path' => $paths[$i] ?? $id,
    ];
}

$form = new group_selection_form($pageurl->out(false), ['current' => $current]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/rhssosync/index.php'));
} else if ($data = $form->get_data()) {
    $rawids = $data->groupids ?? [];
    if (!is_array($rawids)) {
        $rawids = $rawids !== '' && $rawids !== null ? [(string) $rawids] : [];
    }
    $rawids = array_values(array_unique(array_filter(array_map('strval', $rawids), static function($id) {
        return $id !== '';
    })));

    if (empty($rawids)) {
        $config->set_groups([]);
        redirect(
            $pageurl,
            get_string('groupcleared', 'local_rhssosync'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Resolve canonical paths server-side so stored paths are trustworthy.
    $groups = [];
    $pathfail = false;
    $client = null;
    try {
        $client = new rhsso_client($config);
    } catch (\moodle_exception $e) {
        $pathfail = true;
    }

    foreach ($rawids as $groupid) {
        $path = $groupid;
        if ($client) {
            try {
                $group = $client->get_group($groupid);
                if ($group) {
                    $path = $group->path;
                } else {
                    $pathfail = true;
                }
            } catch (\moodle_exception $e) {
                $pathfail = true;
            }
        }
        $groups[$groupid] = $path;
    }

    if ($pathfail) {
        \core\notification::warning(get_string('grouppathresolvefail', 'local_rhssosync'));
    }

    $config->set_groups($groups);
    $display = implode(', ', array_values($groups));
    redirect(
        $pageurl,
        get_string('groupsaved', 'local_rhssosync', $display),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('groupselection', 'local_rhssosync'));

if (!$config->is_connection_configured()) {
    echo $OUTPUT->notification(
        get_string('dashnotconfigured', 'local_rhssosync'),
        \core\output\notification::NOTIFY_WARNING
    );
}

echo $OUTPUT->box(get_string('groupselection_help', 'local_rhssosync'));
$form->display();
echo $OUTPUT->footer();
