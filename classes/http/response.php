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

namespace local_rhssosync\http;

/**
 * Immutable HTTP response value object.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response {
    /** @var int HTTP status code (0 when a transport error occurred). */
    public $status;

    /** @var string Raw response body. */
    public $body;

    /** @var string Transport error message, empty when none. */
    public $error;

    /**
     * Constructor.
     *
     * @param int $status
     * @param string $body
     * @param string $error
     */
    public function __construct($status, $body = '', $error = '') {
        $this->status = (int) $status;
        $this->body = (string) $body;
        $this->error = (string) $error;
    }

    /**
     * Whether the response represents success.
     *
     * @return bool True for 2xx responses with no transport error.
     */
    public function is_success() {
        return $this->error === '' && $this->status >= 200 && $this->status < 300;
    }

    /**
     * Decode the body as JSON.
     *
     * @return mixed Decoded value (array/object/scalar) or null on failure.
     */
    public function json() {
        if ($this->body === '') {
            return null;
        }
        return json_decode($this->body, true);
    }
}
