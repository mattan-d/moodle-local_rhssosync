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
 * Autocomplete AJAX source for selecting an RHSSO group.
 *
 * Implements the transport/processResults contract expected by
 * core/form-autocomplete. The browser only calls the Moodle external function;
 * Moodle talks to RHSSO server-side, so no credentials are exposed.
 *
 * @module     local_rhssosync/groupselector
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    return {
        /**
         * Send the query to the Moodle external function.
         *
         * @param {String} selector The autocomplete selector (unused).
         * @param {String} query The current search text.
         * @param {Function} success Callback invoked with the raw response.
         * @param {Function} failure Callback invoked on error.
         */
        transport: function(selector, query, success, failure) {
            if (query.length < 2) {
                success({groups: []});
                return;
            }
            Ajax.call([{
                methodname: 'local_rhssosync_search_groups',
                args: {query: query}
            }])[0].then(success).catch(failure);
        },

        /**
         * Convert the response into autocomplete options.
         *
         * @param {String} selector The autocomplete selector (unused).
         * @param {Object} response The external function response.
         * @return {Array} Options with value and label keys.
         */
        processResults: function(selector, response) {
            var options = [];
            if (response && response.groups) {
                response.groups.forEach(function(group) {
                    options.push({
                        value: group.id,
                        label: group.path
                    });
                });
            }
            return options;
        }
    };
});
