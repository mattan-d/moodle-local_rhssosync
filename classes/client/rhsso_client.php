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

namespace local_rhssosync\client;

use local_rhssosync\http\client;
use local_rhssosync\http\curl_client;
use local_rhssosync\local\config;
use local_rhssosync\local\sync_exception;

/**
 * Thin wrapper around the RHSSO (Keycloak) Admin REST API.
 *
 * Authenticates with the Client Credentials grant against a dedicated
 * service-account client. The base URL is fully configurable, so the "/auth"
 * context is never assumed. The client secret, access token and Authorization
 * header are never logged or returned to callers.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rhsso_client {
    /** @var config */
    protected $config;

    /** @var client */
    protected $http;

    /** @var string|null Cached bearer token for the lifetime of this object. */
    protected $token = null;

    /**
     * Constructor.
     *
     * @param config $config
     * @param client|null $http Optional transport, defaults to Moodle curl.
     */
    public function __construct(config $config, ?client $http = null) {
        $this->config = $config;
        $this->http = $http ?? new curl_client($config);
    }

    /**
     * Base URL for the OpenID Connect realm endpoints.
     *
     * @return string
     */
    protected function realm_base() {
        return $this->config->get_base_url() . '/realms/' . rawurlencode($this->config->get_realm());
    }

    /**
     * Base URL for the Admin REST API of the realm.
     *
     * @return string
     */
    protected function admin_base() {
        return $this->config->get_base_url() . '/admin/realms/' . rawurlencode($this->config->get_realm());
    }

    /**
     * Obtain (and cache) an access token using the client credentials grant.
     *
     * @return string The bearer token.
     * @throws sync_exception
     */
    public function get_access_token() {
        if ($this->token !== null) {
            return $this->token;
        }
        if (!$this->config->is_connection_configured()) {
            throw new sync_exception('errornotconfigured');
        }

        $url = $this->realm_base() . '/protocol/openid-connect/token';
        $response = $this->http->post_form($url, [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->config->get_client_id(),
            'client_secret' => $this->config->get_client_secret(),
        ]);

        if (!$response->is_success()) {
            // Surface the status only; never the request body or token payload.
            throw new sync_exception('errortoken', $this->safe_status($response));
        }

        $data = $response->json();
        if (!is_array($data) || empty($data['access_token'])) {
            throw new sync_exception('errortokenparse');
        }

        $this->token = (string) $data['access_token'];
        return $this->token;
    }

    /**
     * Authorization header line for admin requests.
     *
     * @return array
     * @throws sync_exception
     */
    protected function auth_headers() {
        return ['Authorization: Bearer ' . $this->get_access_token()];
    }

    /**
     * Lightweight connectivity check used by the "Test connection" action.
     *
     * @return \stdClass Object with bool $success and string $message.
     */
    public function test_connection() {
        $result = new \stdClass();
        try {
            $this->get_access_token();
            // A minimal admin call confirms the service account has access.
            $url = $this->admin_base() . '/groups?first=0&max=1&briefRepresentation=true';
            $response = $this->http->get($url, $this->auth_headers());
            if (!$response->is_success()) {
                $result->success = false;
                $result->message = get_string(
                    'testconnectionadminfail',
                    'local_rhssosync',
                    $this->safe_status($response)
                );
                return $result;
            }
            $result->success = true;
            $result->message = get_string('testconnectionok', 'local_rhssosync');
        } catch (\moodle_exception $e) {
            $result->success = false;
            $result->message = $e->getMessage();
        }
        return $result;
    }

    /**
     * Search RHSSO groups by name for the autocomplete element.
     *
     * @param string $query Free text query (already length-validated by caller).
     * @param int $max Maximum number of results to return.
     * @return array List of objects with id, name and path.
     * @throws sync_exception
     */
    public function search_groups($query, $max = 20) {
        $url = $this->admin_base() . '/groups?' . http_build_query([
            'search'              => $query,
            'briefRepresentation' => 'true',
            'first'               => 0,
            'max'                 => max(1, (int) $max),
        ], '', '&');

        $response = $this->http->get($url, $this->auth_headers());
        if (!$response->is_success()) {
            throw new sync_exception('errorgroupsearch', $this->safe_status($response));
        }

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $flat = [];
        $this->flatten_groups($data, $flat);

        // Filter on the query (Keycloak returns the matching branch, including
        // ancestors), and cap the number of results.
        $needle = \core_text::strtolower($query);
        $results = [];
        foreach ($flat as $group) {
            $haystack = \core_text::strtolower($group->name . ' ' . $group->path);
            if ($needle === '' || strpos($haystack, $needle) !== false) {
                $results[] = $group;
            }
            if (count($results) >= $max) {
                break;
            }
        }
        return $results;
    }

    /**
     * Recursively flatten a Keycloak group tree into a flat list.
     *
     * @param array $groups Group representations.
     * @param array $out Accumulator (passed by reference).
     * @return void
     */
    protected function flatten_groups(array $groups, array &$out) {
        foreach ($groups as $group) {
            if (!is_array($group) || empty($group['id'])) {
                continue;
            }
            $entry = new \stdClass();
            $entry->id = (string) $group['id'];
            $entry->name = isset($group['name']) ? (string) $group['name'] : '';
            $entry->path = isset($group['path']) ? (string) $group['path'] : ('/' . $entry->name);
            $out[] = $entry;

            if (!empty($group['subGroups']) && is_array($group['subGroups'])) {
                $this->flatten_groups($group['subGroups'], $out);
            }
        }
    }

    /**
     * Fetch a single group representation by id.
     *
     * @param string $groupid
     * @return \stdClass|null Object with id, name and path, or null if missing.
     * @throws sync_exception
     */
    public function get_group($groupid) {
        $url = $this->admin_base() . '/groups/' . rawurlencode($groupid);
        $response = $this->http->get($url, $this->auth_headers());
        if ($response->status === 404) {
            return null;
        }
        if (!$response->is_success()) {
            throw new sync_exception('errorgroupfetch', $this->safe_status($response));
        }
        $group = $response->json();
        if (!is_array($group) || empty($group['id'])) {
            return null;
        }
        $entry = new \stdClass();
        $entry->id = (string) $group['id'];
        $entry->name = isset($group['name']) ? (string) $group['name'] : '';
        $entry->path = isset($group['path']) ? (string) $group['path'] : ('/' . $entry->name);
        return $entry;
    }

    /**
     * Retrieve the direct members of a group, fully paginated.
     *
     * Subgroups are NOT processed. If any page fails the whole call throws, so
     * the caller can treat the member list as incomplete and avoid suspensions.
     *
     * @param string $groupid RHSSO group id.
     * @param int|null $pagesize Override page size (defaults to plugin config).
     * @return array List of raw user representations (associative arrays).
     * @throws sync_exception
     */
    public function get_group_members($groupid, $pagesize = null) {
        $pagesize = $pagesize ? (int) $pagesize : $this->config->get_page_size();
        if ($pagesize < 1) {
            $pagesize = 100;
        }

        $members = [];
        $first = 0;
        $headers = $this->auth_headers();

        // Safety cap to avoid an unbounded loop if the server keeps returning
        // full pages (defensive only; normal completion is the short final page).
        $maxpages = 100000;
        $page = 0;

        do {
            $url = $this->admin_base() . '/groups/' . rawurlencode($groupid) . '/members?' . http_build_query([
                'first'               => $first,
                'max'                 => $pagesize,
                'briefRepresentation' => 'false',
            ], '', '&');

            $response = $this->http->get($url, $headers);
            if (!$response->is_success()) {
                throw new sync_exception('errormemberspage', (object) [
                    'first'  => $first,
                    'status' => $this->safe_status($response),
                ]);
            }

            $batch = $response->json();
            if (!is_array($batch)) {
                throw new sync_exception('errormembersparse', (object) ['first' => $first]);
            }

            foreach ($batch as $member) {
                if (is_array($member)) {
                    $members[] = $member;
                }
            }

            $count = count($batch);
            $first += $pagesize;
            $page++;
        } while ($count === $pagesize && $page < $maxpages);

        return $members;
    }

    /**
     * Produce a non-sensitive status descriptor for error messages.
     *
     * @param \local_rhssosync\http\response $response
     * @return string
     */
    protected function safe_status($response) {
        if ($response->error !== '') {
            return get_string('errortransport', 'local_rhssosync');
        }
        return 'HTTP ' . (int) $response->status;
    }
}
