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

namespace local_rhssosync\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Group selection form with RHSSO autocomplete.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_selection_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $current = $this->_customdata['current'] ?? null;

        // Pre-populate the option list with the currently selected group so the
        // label is rendered before any AJAX search happens.
        $options = [];
        if ($current && !empty($current->id)) {
            $options[$current->id] = $current->path;
        }

        $mform->addElement('autocomplete', 'groupid', get_string('group', 'local_rhssosync'), $options, [
            'ajax'              => 'local_rhssosync/groupselector',
            'multiple'          => false,
            'casesensitive'     => false,
            'noselectionstring' => get_string('nogroupselected', 'local_rhssosync'),
            'placeholder'       => get_string('grouptypetosearch', 'local_rhssosync'),
        ]);
        $mform->addHelpButton('groupid', 'group', 'local_rhssosync');

        if ($current && !empty($current->id)) {
            $mform->setDefault('groupid', $current->id);
        }

        $this->add_action_buttons(true, get_string('savegroup', 'local_rhssosync'));
    }
}
