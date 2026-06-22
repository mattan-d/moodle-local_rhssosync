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
 * HTTP transport interface used by the RHSSO client.
 *
 * Implementations must never log request headers or bodies, since they carry
 * the client secret and bearer token.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface client {
    /**
     * Perform a GET request.
     *
     * @param string $url Absolute URL.
     * @param array $headers Header lines, e.g. ['Authorization: Bearer ...'].
     * @return response
     */
    public function get($url, array $headers = []);

    /**
     * Perform a POST request with an application/x-www-form-urlencoded body.
     *
     * @param string $url Absolute URL.
     * @param array $params Key/value form fields.
     * @param array $headers Extra header lines.
     * @return response
     */
    public function post_form($url, array $params, array $headers = []);
}
