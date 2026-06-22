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
 * CLI synchronisation runner for local_rhssosync.
 *
 * Usage:
 *   php local/rhssosync/cli/sync.php            Run a real synchronisation.
 *   php local/rhssosync/cli/sync.php --dry-run  Preview without changes.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_rhssosync\local\synchronizer;
use local_rhssosync\local\sync_result;

[$options, $unrecognised] = cli_get_params(
    [
        'help'    => false,
        'dry-run' => false,
    ],
    [
        'h' => 'help',
    ]
);

if ($options['help']) {
    cli_writeln(get_string('clihelp', 'local_rhssosync'));
    exit(0);
}

$dryrun = (bool) $options['dry-run'];
$trigger = $dryrun ? 'preview' : 'cli';

cli_heading('local_rhssosync ' . ($dryrun ? '(dry run)' : ''));

$result = synchronizer::instance()->run($trigger, $dryrun, 0);

cli_writeln('Status: ' . $result->status);
cli_writeln('Summary: ' . $result->summary);
cli_writeln('Received: ' . $result->received);
cli_writeln('Created: ' . $result->count(sync_result::ACTION_CREATE));
cli_writeln('Updated: ' . $result->count(sync_result::ACTION_UPDATE));
cli_writeln('Suspended: ' . $result->count(sync_result::ACTION_SUSPEND));
cli_writeln('Reactivated: ' . $result->count(sync_result::ACTION_REACTIVATE));
cli_writeln('Conflicts: ' . $result->count(sync_result::ACTION_CONFLICT));
cli_writeln('Errors: ' . $result->count(sync_result::ACTION_ERROR));

if ($result->status === sync_result::STATUS_FAILED) {
    exit(1);
}
exit(0);
