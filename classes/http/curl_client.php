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

use local_rhssosync\local\config;

/**
 * HTTP transport backed by the Moodle curl wrapper (lib/filelib.php).
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl_client implements client {
    /** @var bool */
    protected $tlsverify;

    /** @var int */
    protected $connecttimeout;

    /** @var int */
    protected $readtimeout;

    /**
     * Constructor.
     *
     * @param config $config
     */
    public function __construct(config $config) {
        $this->tlsverify = $config->get_tls_verify();
        $this->connecttimeout = $config->get_connect_timeout();
        $this->readtimeout = $config->get_read_timeout();
    }

    /**
     * Shared curl options for every request.
     *
     * @return array
     */
    protected function base_options() {
        return [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_CONNECTTIMEOUT' => $this->connecttimeout,
            'CURLOPT_TIMEOUT'        => $this->readtimeout,
            'CURLOPT_SSL_VERIFYPEER' => $this->tlsverify,
            'CURLOPT_SSL_VERIFYHOST' => $this->tlsverify ? 2 : 0,
        ];
    }

    /**
     * Build a configured Moodle curl instance.
     *
     * @param array $headers
     * @return \curl
     */
    protected function make_curl(array $headers) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Allow requests to the configured RHSSO host even when it is internal
        // to the OpenShift cluster (the admin controls the base URL).
        $curl = new \curl(['ignoresecurity' => true]);
        $defaultheaders = ['Accept: application/json'];
        $curl->setHeader(array_merge($defaultheaders, $headers));
        return $curl;
    }

    /**
     * Translate a completed curl call into a response object.
     *
     * @param \curl $curl
     * @param string $body
     * @return response
     */
    protected function to_response(\curl $curl, $body) {
        if ($curl->get_errno()) {
            // Deliberately do not include headers; only the transport message.
            return new response(0, '', $curl->error);
        }
        $info = $curl->get_info();
        $status = isset($info['http_code']) ? (int) $info['http_code'] : 0;
        return new response($status, (string) $body, '');
    }

    /**
     * Perform a GET request via the Moodle curl wrapper.
     *
     * @param string $url
     * @param array $headers
     * @return response
     */
    public function get($url, array $headers = []) {
        $curl = $this->make_curl($headers);
        $body = $curl->get($url, [], $this->base_options());
        return $this->to_response($curl, $body);
    }

    /**
     * Perform a form-encoded POST request via the Moodle curl wrapper.
     *
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return response
     */
    public function post_form($url, array $params, array $headers = []) {
        $curl = $this->make_curl(array_merge(
            ['Content-Type: application/x-www-form-urlencoded'],
            $headers
        ));
        $body = $curl->post($url, http_build_query($params, '', '&'), $this->base_options());
        return $this->to_response($curl, $body);
    }
}
