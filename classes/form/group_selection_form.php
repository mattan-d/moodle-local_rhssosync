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
 * Group selection form with RHSSO autocomplete (multi-select).
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

        /** @var object[] $current List of {id, path} for currently selected groups. */
        $current = $this->_customdata['current'] ?? [];
        if (!is_array($current)) {
            $current = $current ? [$current] : [];
        }

        // Pre-populate the option list with currently selected groups so labels
        // are rendered before any AJAX search happens.
        $options = [];
        $defaults = [];
        foreach ($current as $group) {
            if (empty($group->id)) {
                continue;
            }
            $options[$group->id] = $group->path !== '' ? $group->path : $group->id;
            $defaults[] = $group->id;
        }

        $mform->addElement('autocomplete', 'groupids', get_string('groups', 'local_rhssosync'), $options, [
            'ajax'              => 'local_rhssosync/groupselector',
            'multiple'          => true,
            'casesensitive'     => false,
            'noselectionstring' => get_string('nogroupselected', 'local_rhssosync'),
            'placeholder'       => get_string('grouptypetosearch', 'local_rhssosync'),
        ]);
        $mform->addHelpButton('groupids', 'groups', 'local_rhssosync');

        if (!empty($defaults)) {
            $mform->setDefault('groupids', $defaults);
        }

        $this->add_action_buttons(true, get_string('savegroups', 'local_rhssosync'));
    }
}
