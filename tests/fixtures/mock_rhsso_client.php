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

namespace local_rhssosync;

use local_rhssosync\client\rhsso_client;
use local_rhssosync\local\sync_exception;

/**
 * Mock RHSSO client used to drive synchronizer tests without HTTP.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_rhsso_client extends rhsso_client {
    /** @var array Member representations to return. */
    public $members = [];

    /** @var bool When true, simulate an incomplete retrieval by throwing. */
    public $throw = false;

    /**
     * Bypass the parent constructor; no config or transport needed.
     */
    public function __construct() {
        // Intentionally empty: the mock returns canned data only.
    }

    /**
     * Return the canned member list or simulate an incomplete retrieval.
     *
     * @param string $groupid
     * @param int|null $pagesize
     * @return array
     */
    public function get_group_members($groupid, $pagesize = null) {
        if ($this->throw) {
            throw new sync_exception('errormemberspage', (object) ['first' => 0, 'status' => 'HTTP 500']);
        }
        return $this->members;
    }
}
