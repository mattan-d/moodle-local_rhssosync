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
 * Dashboard and primary actions for local_rhssosync.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_rhssosync\client\rhsso_client;
use local_rhssosync\local\config;
use local_rhssosync\local\mapping_repository;
use local_rhssosync\local\report_repository;
use local_rhssosync\local\sync_result;
use local_rhssosync\local\synchronizer;

admin_externalpage_setup('local_rhssosync_dashboard');

$context = context_system::instance();
$config = new config();
$baseurl = new moodle_url('/local/rhssosync/index.php');

// Handle actions.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey() && data_submitted()) {
    require_capability('local/rhssosync:manage', $context);

    if ($action === 'testconnection') {
        $client = new rhsso_client($config);
        $result = $client->test_connection();
        if ($result->success) {
            redirect($baseurl, $result->message, null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($baseurl, $result->message, null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    if ($action === 'syncnow') {
        if (!$config->is_group_selected()) {
            redirect(
                $baseurl,
                get_string('errornogroup', 'local_rhssosync'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        // Run in this request. Queueing for cron left the preview list
        // uncreated whenever ad-hoc tasks were not processed.
        \core_php_time_limit::raise(0);
        raise_memory_limit(MEMORY_EXTRA);
        $result = synchronizer::instance()->run('adhoc', false, (int) $USER->id);

        $notify = \core\output\notification::NOTIFY_SUCCESS;
        if ($result->status === sync_result::STATUS_FAILED || $result->status === sync_result::STATUS_ABORTED) {
            $notify = \core\output\notification::NOTIFY_ERROR;
        } else if ($result->status === sync_result::STATUS_PARTIAL || $result->needsreview) {
            $notify = \core\output\notification::NOTIFY_WARNING;
        }
        redirect($baseurl, $result->summary, null, $notify);
    }
}

$reports = new report_repository();
$mappings = new mapping_repository();
$lastrun = $reports->get_last_run();
// Drop mappings for Moodle users that were deleted, then count only live ones.
$mappings->purge_stale();
$managedcount = $mappings->count_live_managed();
$conflictcount = $reports->count_open_conflicts();

/** @var \local_rhssosync\output\renderer $output */
$output = $PAGE->get_renderer('local_rhssosync');
$dashboard = new \local_rhssosync\output\dashboard($config, $lastrun, $managedcount, $conflictcount);

echo $output->header();
echo $output->heading(get_string('dashboard', 'local_rhssosync'));
echo $output->render($dashboard);
echo $output->footer();
