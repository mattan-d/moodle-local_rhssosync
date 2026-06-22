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
 * Administration settings and pages for local_rhssosync.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $component = 'local_rhssosync';

    // Top level category for the plugin.
    $category = new admin_category('local_rhssosync', get_string('pluginname', $component));
    $ADMIN->add('localplugins', $category);

    // Dashboard (overview + actions).
    $ADMIN->add('local_rhssosync', new admin_externalpage(
        'local_rhssosync_dashboard',
        get_string('dashboard', $component),
        new moodle_url('/local/rhssosync/index.php'),
        'local/rhssosync:viewreports'
    ));

    // Connection settings page.
    $connection = new admin_settingpage('local_rhssosync_connection', get_string('connectionsettings', $component));

    $connection->add(new admin_setting_configtext(
        "$component/baseurl",
        get_string('baseurl', $component),
        get_string('baseurl_desc', $component),
        '',
        PARAM_URL
    ));

    $connection->add(new admin_setting_configtext(
        "$component/realm",
        get_string('realm', $component),
        get_string('realm_desc', $component),
        'ADUsers',
        PARAM_ALPHANUMEXT
    ));

    $connection->add(new admin_setting_configtext(
        "$component/clientid",
        get_string('clientid', $component),
        get_string('clientid_desc', $component),
        '',
        PARAM_RAW_TRIMMED
    ));

    $connection->add(new admin_setting_configpasswordunmask(
        "$component/clientsecret",
        get_string('clientsecret', $component),
        get_string('clientsecret_desc', $component),
        ''
    ));

    $connection->add(new admin_setting_configcheckbox(
        "$component/tlsverify",
        get_string('tlsverify', $component),
        get_string('tlsverify_desc', $component),
        1
    ));

    $connection->add(new admin_setting_configtext(
        "$component/connecttimeout",
        get_string('connecttimeout', $component),
        get_string('connecttimeout_desc', $component),
        10,
        PARAM_INT
    ));

    $connection->add(new admin_setting_configtext(
        "$component/readtimeout",
        get_string('readtimeout', $component),
        get_string('readtimeout_desc', $component),
        30,
        PARAM_INT
    ));

    $connection->add(new admin_setting_configtext(
        "$component/pagesize",
        get_string('pagesize', $component),
        get_string('pagesize_desc', $component),
        100,
        PARAM_INT
    ));

    $ADMIN->add('local_rhssosync', $connection);

    // Group selection (custom page with autocomplete).
    $ADMIN->add('local_rhssosync', new admin_externalpage(
        'local_rhssosync_groupselection',
        get_string('groupselection', $component),
        new moodle_url('/local/rhssosync/groupselection.php'),
        'local/rhssosync:manage'
    ));

    // Synchronisation settings page (mapping, policies, thresholds).
    $sync = new admin_settingpage('local_rhssosync_syncsettings', get_string('syncsettings', $component));

    $sync->add(new admin_setting_heading(
        'local_rhssosync/mappinghead',
        get_string('mappingheading', $component),
        get_string('mappingheading_desc', $component)
    ));

    $sync->add(new admin_setting_configselect(
        "$component/usernamesource",
        get_string('usernamesource', $component),
        get_string('usernamesource_desc', $component),
        'username',
        [
            'username'  => get_string('usernamesource_username', $component),
            'attribute' => get_string('usernamesource_attribute', $component),
        ]
    ));

    $sync->add(new admin_setting_configtext(
        "$component/usernameattribute",
        get_string('usernameattribute', $component),
        get_string('usernameattribute_desc', $component),
        'sAMAccountName',
        PARAM_RAW_TRIMMED
    ));

    $sync->add(new admin_setting_configtext(
        "$component/firstnameattribute",
        get_string('firstnameattribute', $component),
        get_string('firstnameattribute_desc', $component),
        'firstName',
        PARAM_RAW_TRIMMED
    ));

    $sync->add(new admin_setting_configtext(
        "$component/lastnameattribute",
        get_string('lastnameattribute', $component),
        get_string('lastnameattribute_desc', $component),
        'lastName',
        PARAM_RAW_TRIMMED
    ));

    $sync->add(new admin_setting_configtext(
        "$component/emailattribute",
        get_string('emailattribute', $component),
        get_string('emailattribute_desc', $component),
        'email',
        PARAM_RAW_TRIMMED
    ));

    $sync->add(new admin_setting_heading(
        'local_rhssosync/policyhead',
        get_string('policyheading', $component),
        get_string('policyheading_desc', $component)
    ));

    $sync->add(new admin_setting_configcheckbox(
        "$component/updateexisting",
        get_string('updateexisting', $component),
        get_string('updateexisting_desc', $component),
        1
    ));

    $sync->add(new admin_setting_configcheckbox(
        "$component/updateemail",
        get_string('updateemail', $component),
        get_string('updateemail_desc', $component),
        1
    ));

    $sync->add(new admin_setting_configselect(
        "$component/conflictpolicy",
        get_string('conflictpolicy', $component),
        get_string('conflictpolicy_desc', $component),
        'skip',
        [
            'skip' => get_string('conflictpolicy_skip', $component),
        ]
    ));

    $sync->add(new admin_setting_configtext(
        "$component/maxsuspendpercent",
        get_string('maxsuspendpercent', $component),
        get_string('maxsuspendpercent_desc', $component),
        25,
        PARAM_INT
    ));

    $sync->add(new admin_setting_configtext(
        "$component/logretentiondays",
        get_string('logretentiondays', $component),
        get_string('logretentiondays_desc', $component),
        90,
        PARAM_INT
    ));

    $ADMIN->add('local_rhssosync', $sync);

    // Report / management pages.
    $ADMIN->add('local_rhssosync', new admin_externalpage(
        'local_rhssosync_managed',
        get_string('managedusers', $component),
        new moodle_url('/local/rhssosync/managed.php'),
        'local/rhssosync:viewreports'
    ));

    $ADMIN->add('local_rhssosync', new admin_externalpage(
        'local_rhssosync_conflicts',
        get_string('conflicts', $component),
        new moodle_url('/local/rhssosync/conflicts.php'),
        'local/rhssosync:viewreports'
    ));

    $ADMIN->add('local_rhssosync', new admin_externalpage(
        'local_rhssosync_history',
        get_string('synchistory', $component),
        new moodle_url('/local/rhssosync/history.php'),
        'local/rhssosync:viewreports'
    ));

    // The settings tree is fully described above; suppress the default node.
    $settings = null;
}
