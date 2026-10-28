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
 * English language strings for local_rhssosync.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'RHSSO user synchronisation';

// Capabilities.
$string['rhssosync:manage'] = 'Configure RHSSO synchronisation and run actions';
$string['rhssosync:viewreports'] = 'View RHSSO synchronisation reports and managed users';

// Navigation / pages.
$string['dashboard'] = 'Dashboard';
$string['connectionsettings'] = 'Connection settings';
$string['groupselection'] = 'Group selection';
$string['syncsettings'] = 'Synchronisation settings';
$string['managedusers'] = 'Managed users';
$string['conflicts'] = 'Conflicts';
$string['synchistory'] = 'Synchronisation history';
$string['rundetails'] = 'Run details';

// Connection settings.
$string['baseurl'] = 'RHSSO base URL';
$string['baseurl_desc'] = 'Base URL of the RHSSO server, for example https://sso.example.org or https://sso.example.org/auth. Do not include the realm. The "/auth" context is not assumed.';
$string['realm'] = 'Realm';
$string['realm_desc'] = 'RHSSO realm that contains the users and groups.';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Client ID of the dedicated service-account client used for the Admin REST API.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_desc'] = 'Client secret of the service-account client. Stored encrypted by Moodle and never logged or displayed in reports.';
$string['tlsverify'] = 'Verify TLS certificate';
$string['tlsverify_desc'] = 'Verify the RHSSO server TLS certificate. Keep enabled in production.';
$string['connecttimeout'] = 'Connection timeout (seconds)';
$string['connecttimeout_desc'] = 'Maximum time to wait when establishing a connection to RHSSO.';
$string['readtimeout'] = 'Read timeout (seconds)';
$string['readtimeout_desc'] = 'Maximum time to wait for an RHSSO response.';
$string['pagesize'] = 'Pagination page size';
$string['pagesize_desc'] = 'Number of group members fetched per RHSSO request.';

// Sync settings: mapping.
$string['mappingheading'] = 'Attribute mapping';
$string['mappingheading_desc'] = 'Map RHSSO user attributes to Moodle user fields. New accounts are always created with the oauth2 authentication method.';
$string['usernamesource'] = 'Username source';
$string['usernamesource_desc'] = 'Where to read the sAMAccountName that becomes the Moodle username.';
$string['usernamesource_username'] = 'RHSSO username field';
$string['usernamesource_attribute'] = 'RHSSO attribute';
$string['usernameattribute'] = 'Username attribute';
$string['usernameattribute_desc'] = 'When the source is "RHSSO attribute", the attribute name holding the sAMAccountName (for example sAMAccountName).';
$string['firstnameattribute'] = 'First name attribute';
$string['firstnameattribute_desc'] = 'RHSSO field or attribute mapped to the Moodle first name (default firstName).';
$string['lastnameattribute'] = 'Last name attribute';
$string['lastnameattribute_desc'] = 'RHSSO field or attribute mapped to the Moodle last name (default lastName).';
$string['emailattribute'] = 'Email attribute';
$string['emailattribute_desc'] = 'RHSSO field or attribute mapped to the Moodle email address (default email).';

// Sync settings: policies.
$string['policyheading'] = 'Update and safety policies';
$string['policyheading_desc'] = 'Control how existing managed users are updated and how suspensions are protected.';
$string['updateexisting'] = 'Update existing managed users';
$string['updateexisting_desc'] = 'When enabled, first and last names of managed users are updated from RHSSO.';
$string['updateemail'] = 'Update email address';
$string['updateemail_desc'] = 'When enabled, the email address of managed users is updated from RHSSO.';
$string['conflictpolicy'] = 'Conflict policy';
$string['conflictpolicy_desc'] = 'How to handle an RHSSO username that already exists in Moodle without a plugin mapping.';
$string['conflictpolicy_skip'] = 'Skip and report conflict';
$string['maxsuspendpercent'] = 'Maximum suspension percentage';
$string['maxsuspendpercent_desc'] = 'If a single run would suspend more than this percentage of the managed users in the group, all suspensions are withheld and the run is flagged for administrator review (default 25).';
$string['logretentiondays'] = 'Log retention (days)';
$string['logretentiondays_desc'] = 'Number of days to keep synchronisation run reports. Set to 0 to keep them indefinitely.';

// Group selection.
$string['group'] = 'RHSSO group';
$string['groups'] = 'RHSSO groups';
$string['groups_help'] = 'Start typing at least two characters to search RHSSO groups. You can select one or more groups. Only the direct members of the selected groups are synchronised; subgroups are not processed. A user who belongs to more than one selected group is synchronised once.';
$string['group_help'] = 'Start typing at least two characters to search RHSSO groups. Only the direct members of the selected group are synchronised; subgroups are not processed.';
$string['groupselection_help'] = 'Select one or more RHSSO groups whose direct members will be synchronised into Moodle. Searching queries RHSSO live through a secure server-side call.';
$string['grouptypetosearch'] = 'Type to search RHSSO groups...';
$string['nogroupselected'] = 'No group selected';
$string['savegroup'] = 'Save group';
$string['savegroups'] = 'Save groups';
$string['groupsaved'] = 'RHSSO group selection saved: {$a}';
$string['groupcleared'] = 'RHSSO group selection cleared.';
$string['grouppathresolvefail'] = 'The group selection was saved but one or more full paths could not be confirmed against RHSSO.';

// Dashboard.
$string['dashstatus'] = 'Status';
$string['configured'] = 'Configured';
$string['notconfigured'] = 'Not configured';
$string['dashnotconfigured'] = 'The RHSSO connection is not configured yet. Complete the connection settings first.';
$string['dashnogroup'] = 'No RHSSO group is selected yet. Choose at least one group before synchronising.';
$string['testconnection'] = 'Test connection';
$string['previewsync'] = 'Preview sync';
$string['syncnow'] = 'Sync now';
$string['lastrun'] = 'Last run';
$string['noruns'] = 'No synchronisation runs recorded yet.';
$string['viewmanagedusers'] = 'View managed users';
$string['viewconflicts'] = 'View conflicts';
$string['viewhistory'] = 'View history';
$string['viewdetails'] = 'View details';
$string['backtodashboard'] = 'Back to dashboard';
$string['backtohistory'] = 'Back to history';

// Actions / results.
$string['testconnectionok'] = 'Connection to RHSSO succeeded.';
$string['testconnectionadminfail'] = 'Authenticated, but the Admin REST API call failed ({$a}). Check the service account permissions.';
$string['syncqueued'] = 'Synchronisation has been queued and will run on the next cron cycle.';
$string['syncerrorsuffix'] = 'Errors: {$a->errors}. {$a->detail}';
$string['previewintro'] = 'A preview performs a full dry run: it contacts RHSSO and computes the changes that would be made, without modifying any user.';
$string['runpreview'] = 'Run preview';
$string['previewnotready'] = 'Configure the connection and select a group before running a preview.';
$string['previewneedsreview'] = 'This run would exceed the maximum suspension percentage, so suspensions would be withheld for administrator review.';
$string['intendedactions'] = 'Intended actions';

// Report fields.
$string['received'] = 'Members received';
$string['created'] = 'Created';
$string['updated'] = 'Updated';
$string['suspended'] = 'Suspended';
$string['reactivated'] = 'Reactivated';
$string['skipped'] = 'Skipped';
$string['unchanged'] = 'Unchanged';
$string['errors'] = 'Errors';
$string['status'] = 'Status';
$string['trigger'] = 'Trigger';
$string['action'] = 'Action';
$string['detail'] = 'Detail';
$string['username'] = 'Username';
$string['reason'] = 'Reason';
$string['moodleuser'] = 'Moodle user';
$string['lastseen'] = 'Last seen';
$string['suspendedbyplugin'] = 'Suspended by plugin';
$string['active'] = 'Active';
$string['duration'] = 'Duration';
$string['needsreview'] = 'Needs review';
$string['preview'] = 'Preview';
$string['peruseractions'] = 'Per-user actions';

// Managed users / conflicts.
$string['nomanagedusers'] = 'No managed users yet.';
$string['noconflicts'] = 'No open conflicts.';
$string['conflicts_help'] = 'These RHSSO users match an existing Moodle username that this plugin does not manage. They are never modified, adopted or suspended. Resolve each one manually, then mark it as reviewed.';
$string['markresolved'] = 'Mark as reviewed';
$string['conflictresolved'] = 'Conflict marked as reviewed.';

// Action labels.
$string['action_create'] = 'Create';
$string['action_update'] = 'Update';
$string['action_suspend'] = 'Suspend';
$string['action_reactivate'] = 'Reactivate';
$string['action_skip'] = 'Skip';
$string['action_unchanged'] = 'Unchanged';
$string['action_conflict'] = 'Conflict';
$string['action_error'] = 'Error';

// Status labels.
$string['status_running'] = 'Running';
$string['status_success'] = 'Success';
$string['status_partial'] = 'Completed with errors';
$string['status_failed'] = 'Failed';
$string['status_aborted'] = 'Aborted';

// Trigger labels.
$string['trigger_scheduled'] = 'Scheduled';
$string['trigger_adhoc'] = 'Sync now';
$string['trigger_preview'] = 'Preview';
$string['trigger_cli'] = 'CLI';

// Conflict reasons.
$string['reason_usernameexists'] = 'Username already exists in Moodle';

// Preview detail.
$string['previewwouldcreate'] = 'Would create a new managed user.';
$string['previewwouldsuspend'] = 'Would suspend (no longer in group).';

// Suspension safety.
$string['suspendwithheld'] = 'Suspension withheld pending administrator review.';
$string['suspendwithheldsummary'] = 'Suspensions withheld: {$a->candidates} of {$a->total} managed users ({$a->max}% threshold exceeded). No users were suspended.';

// Run summary.
$string['syncsummaryline'] = 'Received {$a->received}; created {$a->created}, updated {$a->updated}, suspended {$a->suspended}, reactivated {$a->reactivated}, conflicts {$a->conflicts}.';

// Tasks.
$string['task_scheduledsync'] = 'RHSSO scheduled synchronisation';
$string['task_cleanup'] = 'RHSSO report cleanup';

// CLI.
$string['clihelp'] = "Synchronise RHSSO group members into Moodle.\n\nOptions:\n  -h, --help   Show this help.\n  --dry-run    Preview the changes without modifying any user.\n\nExample:\n  php local/rhssosync/cli/sync.php --dry-run\n";

// Errors.
$string['errornotconfigured'] = 'The RHSSO connection is not configured.';
$string['errornogroup'] = 'No RHSSO group has been selected.';
$string['errorlock'] = 'Another synchronisation is already running. This run was skipped.';
$string['errorretrieval'] = 'RHSSO member retrieval was incomplete; no suspensions were performed. Detail: {$a}';
$string['errortoken'] = 'Could not obtain an access token from RHSSO ({$a}).';
$string['errortokenparse'] = 'The RHSSO token response could not be parsed.';
$string['errortransport'] = 'network/transport error';
$string['errorgroupsearch'] = 'The RHSSO group search failed ({$a}).';
$string['errorgroupfetch'] = 'The RHSSO group could not be fetched ({$a}).';
$string['errormemberspage'] = 'A page of group members could not be retrieved (offset {$a->first}, {$a->status}).';
$string['errormembersparse'] = 'A page of group members could not be parsed (offset {$a->first}).';
$string['errorunmappable'] = 'The RHSSO user is missing an id or username and was skipped.';
$string['errorinvalidemail'] = 'The RHSSO user has no valid email address; the account was not created.';
$string['errorcreatefailed'] = 'Creating the Moodle user failed: {$a}';
$string['errorupdatefailed'] = 'Updating the Moodle user failed: {$a}';
$string['errormappinguserdeleted'] = 'The mapped Moodle user no longer exists.';
$string['errorsyncfailed'] = 'The synchronisation run failed.';
$string['errorrunnotfound'] = 'The requested synchronisation run was not found.';

// Privacy.
$string['privacy:metadata:local_rhssosync_users'] = 'Mapping between RHSSO users and the Moodle users managed by this plugin.';
$string['privacy:metadata:local_rhssosync_users:userid'] = 'The Moodle user managed by the plugin.';
$string['privacy:metadata:local_rhssosync_users:rhssouserid'] = 'The RHSSO user identifier linked to the Moodle user.';
$string['privacy:metadata:local_rhssosync_users:status'] = 'Whether the managed user is active or suspended.';
$string['privacy:metadata:local_rhssosync_users:lastseen'] = 'When the user was last seen as a group member.';
$string['privacy:metadata:local_rhssosync_log'] = 'Per-user actions recorded during synchronisation runs.';
$string['privacy:metadata:local_rhssosync_log:userid'] = 'The Moodle user affected by the action.';
$string['privacy:metadata:local_rhssosync_log:username'] = 'The username recorded for the action.';
$string['privacy:metadata:local_rhssosync_log:rhssouserid'] = 'The RHSSO user identifier recorded for the action.';
$string['privacy:metadata:local_rhssosync_log:action'] = 'The action that was performed.';
$string['privacy:metadata:local_rhssosync_log:timecreated'] = 'When the action was recorded.';
$string['privacy:metadata:local_rhssosync_conflicts'] = 'Existing Moodle users that conflict with an RHSSO username.';
$string['privacy:metadata:local_rhssosync_conflicts:moodleuserid'] = 'The existing Moodle user involved in the conflict.';
$string['privacy:metadata:local_rhssosync_conflicts:username'] = 'The conflicting username.';
$string['privacy:metadata:local_rhssosync_conflicts:rhssouserid'] = 'The RHSSO user identifier involved in the conflict.';
$string['privacy:metadata:rhsso'] = 'In order to identify directory accounts, the username is sent to the RHSSO server during synchronisation.';
$string['privacy:metadata:rhsso:username'] = 'The username (sAMAccountName) used to match the RHSSO account.';
