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

namespace local_rhssosync\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use local_rhssosync\client\rhsso_client;
use local_rhssosync\local\config;

/**
 * AJAX external function backing the RHSSO group autocomplete.
 *
 * The browser only ever talks to Moodle; Moodle calls RHSSO server-side using
 * the service account, so no credentials reach the client.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_groups extends external_api {
    /** @var int Minimum query length before searching. */
    const MIN_QUERY = 2;

    /** @var int Maximum number of results returned. */
    const MAX_RESULTS = 25;

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW, 'Search text (at least 2 characters).'),
        ]);
    }

    /**
     * Search RHSSO groups.
     *
     * @param string $query
     * @return array
     */
    public static function execute($query) {
        $params = self::validate_parameters(self::execute_parameters(), ['query' => $query]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rhssosync:manage', $context);

        $query = trim($params['query']);
        if (\core_text::strlen($query) < self::MIN_QUERY) {
            return ['groups' => []];
        }

        $config = new config();
        if (!$config->is_connection_configured()) {
            return ['groups' => []];
        }

        $client = new rhsso_client($config);
        $groups = $client->search_groups($query, self::MAX_RESULTS);

        $out = [];
        foreach ($groups as $group) {
            $out[] = [
                'id'   => $group->id,
                'name' => $group->name,
                'path' => $group->path,
            ];
        }
        return ['groups' => $out];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'groups' => new external_multiple_structure(
                new external_single_structure([
                    'id'   => new external_value(PARAM_RAW, 'RHSSO group id.'),
                    'name' => new external_value(PARAM_TEXT, 'Group name.'),
                    'path' => new external_value(PARAM_RAW, 'Full group path.'),
                ])
            ),
        ]);
    }
}
