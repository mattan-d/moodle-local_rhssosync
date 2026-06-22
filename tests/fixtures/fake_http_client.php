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

use local_rhssosync\http\client;
use local_rhssosync\http\response;

/**
 * Scriptable fake HTTP client for unit tests.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_http_client implements client {
    /** @var response Response returned for token (post_form) calls. */
    public $tokenresponse;

    /** @var response[] FIFO queue of responses for get() calls. */
    public $getqueue = [];

    /** @var array Captured GET URLs. */
    public $geturls = [];

    /**
     * Constructor: default to a valid token response.
     */
    public function __construct() {
        $this->tokenresponse = new response(200, json_encode(['access_token' => 'test-token', 'expires_in' => 60]));
    }

    /**
     * Queue a JSON GET response.
     *
     * @param mixed $data
     * @param int $status
     * @return void
     */
    public function queue_json($data, $status = 200) {
        $this->getqueue[] = new response($status, json_encode($data));
    }

    /**
     * Queue a raw GET response.
     *
     * @param response $response
     * @return void
     */
    public function queue_response(response $response) {
        $this->getqueue[] = $response;
    }

    /**
     * Return the next queued GET response.
     *
     * @param string $url
     * @param array $headers
     * @return response
     */
    public function get($url, array $headers = []) {
        $this->geturls[] = $url;
        if (empty($this->getqueue)) {
            return new response(200, json_encode([]));
        }
        return array_shift($this->getqueue);
    }

    /**
     * Return the canned token response.
     *
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return response
     */
    public function post_form($url, array $params, array $headers = []) {
        return $this->tokenresponse;
    }
}
