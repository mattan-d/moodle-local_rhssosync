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
 * Upgrade steps for local_rhssosync.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin database.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_rhssosync_upgrade($oldversion) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/ddllib.php');

    $dbman = $DB->get_manager();

    if ($oldversion < 2026101400) {
        // Selected groups move out of config_plugins into a dedicated table.
        $table = new xmldb_table('local_rhssosync_groups');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('groupid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grouppath', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('groupid', XMLDB_INDEX_UNIQUE, ['groupid']);
        $table->add_index('sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Migrate any previously saved selection from plugin config.
        if (!$DB->record_exists('local_rhssosync_groups', [])) {
            $ids = [];
            $paths = [];

            $idsjson = get_config('local_rhssosync', 'selectedgroupids');
            if ($idsjson) {
                $decoded = json_decode($idsjson, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $value) {
                        $value = (string) $value;
                        if ($value !== '') {
                            $ids[] = $value;
                        }
                    }
                }
            }
            $pathsjson = get_config('local_rhssosync', 'selectedgrouppaths');
            if ($pathsjson) {
                $decoded = json_decode($pathsjson, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $value) {
                        $paths[] = (string) $value;
                    }
                }
            }
            if (empty($ids)) {
                $legacyid = (string) get_config('local_rhssosync', 'selectedgroupid');
                if ($legacyid !== '') {
                    $ids[] = $legacyid;
                    $paths[] = (string) get_config('local_rhssosync', 'selectedgrouppath');
                }
            }

            $now = time();
            foreach ($ids as $i => $groupid) {
                $path = isset($paths[$i]) && $paths[$i] !== '' ? $paths[$i] : $groupid;
                $DB->insert_record('local_rhssosync_groups', (object) [
                    'groupid'      => $groupid,
                    'grouppath'    => $path,
                    'sortorder'    => $i,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        // Drop obsolete config keys so we never write multi-group JSON there again.
        unset_config('selectedgroupids', 'local_rhssosync');
        unset_config('selectedgrouppaths', 'local_rhssosync');
        unset_config('selectedgroupid', 'local_rhssosync');
        unset_config('selectedgrouppath', 'local_rhssosync');

        upgrade_plugin_savepoint(true, 2026101400, 'local', 'rhssosync');
    }

    return true;
}
