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

namespace local_rhssosync\local;

/**
 * Typed accessor for the plugin configuration.
 *
 * Centralises reading of config_plugins values for local_rhssosync so the
 * rest of the code never references raw config keys. The client secret is
 * intentionally only exposed through {@see config::get_client_secret()} and is
 * never returned by the generic exporters.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {
    /** @var string Plugin frankenstyle component / config plugin name. */
    const COMPONENT = 'local_rhssosync';

    /** @var \stdClass|null Cached config object. */
    protected $raw;

    /**
     * Constructor.
     *
     * @param \stdClass|array|null $override Optional config used for unit testing.
     */
    public function __construct($override = null) {
        if ($override !== null) {
            $this->raw = (object) $override;
        } else {
            $this->raw = get_config(self::COMPONENT);
        }
    }

    /**
     * Read a single value with a default.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function get($name, $default = null) {
        if (isset($this->raw->$name) && $this->raw->$name !== '') {
            return $this->raw->$name;
        }
        return $default;
    }

    /**
     * Base URL of the RHSSO server (no trailing slash, may include /auth).
     *
     * @return string
     */
    public function get_base_url() {
        return rtrim((string) $this->get('baseurl', ''), '/');
    }

    /**
     * Realm name.
     *
     * @return string Realm name.
     */
    public function get_realm() {
        return (string) $this->get('realm', 'ADUsers');
    }

    /**
     * Service account client id.
     *
     * @return string Service account client id.
     */
    public function get_client_id() {
        return (string) $this->get('clientid', '');
    }

    /**
     * The client secret. Never log or export this value.
     *
     * @return string
     */
    public function get_client_secret() {
        return (string) $this->get('clientsecret', '');
    }

    /**
     * Whether to verify TLS certificates.
     *
     * @return bool Whether to verify TLS certificates.
     */
    public function get_tls_verify() {
        return (bool) $this->get('tlsverify', 1);
    }

    /**
     * Connection timeout in seconds.
     *
     * @return int Connection timeout in seconds.
     */
    public function get_connect_timeout() {
        return (int) $this->get('connecttimeout', 10);
    }

    /**
     * Read timeout in seconds.
     *
     * @return int Read timeout in seconds.
     */
    public function get_read_timeout() {
        return (int) $this->get('readtimeout', 30);
    }

    /**
     * Pagination page size, clamped to a sane range.
     *
     * @return int Pagination page size, clamped to a sane range.
     */
    public function get_page_size() {
        $size = (int) $this->get('pagesize', 100);
        if ($size < 1) {
            $size = 100;
        }
        if ($size > 1000) {
            $size = 1000;
        }
        return $size;
    }

    /**
     * Where to read the username from.
     *
     * @return string Either 'username' or 'attribute'.
     */
    public function get_username_source() {
        return (string) $this->get('usernamesource', 'username');
    }

    /**
     * RHSSO attribute name holding the sAMAccountName.
     *
     * @return string RHSSO attribute name holding the sAMAccountName.
     */
    public function get_username_attribute() {
        return (string) $this->get('usernameattribute', 'sAMAccountName');
    }

    /**
     * RHSSO attribute mapped to the Moodle first name.
     *
     * @return string
     */
    public function get_firstname_attribute() {
        return (string) $this->get('firstnameattribute', 'firstName');
    }

    /**
     * RHSSO attribute mapped to the Moodle last name.
     *
     * @return string
     */
    public function get_lastname_attribute() {
        return (string) $this->get('lastnameattribute', 'lastName');
    }

    /**
     * RHSSO attribute mapped to the Moodle email address.
     *
     * @return string
     */
    public function get_email_attribute() {
        return (string) $this->get('emailattribute', 'email');
    }

    /**
     * Whether existing managed users are updated.
     *
     * @return bool Whether existing managed users are updated.
     */
    public function get_update_existing() {
        return (bool) $this->get('updateexisting', 1);
    }

    /**
     * Whether the email address is updated on existing users.
     *
     * @return bool Whether the email address is updated on existing users.
     */
    public function get_update_email() {
        return (bool) $this->get('updateemail', 1);
    }

    /**
     * Conflict policy, currently only 'skip'.
     *
     * @return string Conflict policy, currently only 'skip'.
     */
    public function get_conflict_policy() {
        return (string) $this->get('conflictpolicy', 'skip');
    }

    /**
     * Maximum percentage of managed users that may be suspended.
     *
     * @return int Maximum percentage of managed users that may be suspended.
     */
    public function get_max_suspend_percent() {
        $value = (int) $this->get('maxsuspendpercent', 25);
        if ($value < 0) {
            $value = 0;
        }
        if ($value > 100) {
            $value = 100;
        }
        return $value;
    }

    /**
     * Log retention in days (0 = keep forever).
     *
     * @return int Log retention in days (0 = keep forever).
     */
    public function get_log_retention_days() {
        return (int) $this->get('logretentiondays', 90);
    }

    /**
     * Selected RHSSO group ids (one or more).
     *
     * Reads the JSON list when present; falls back to the legacy single-group
     * config keys so existing installations keep working.
     *
     * @return string[]
     */
    public function get_group_ids() {
        $decoded = $this->decode_group_list($this->get('selectedgroupids', ''));
        if (!empty($decoded)) {
            return $decoded;
        }
        $legacy = (string) $this->get('selectedgroupid', '');
        return $legacy !== '' ? [$legacy] : [];
    }

    /**
     * Selected RHSSO group paths, parallel to {@see get_group_ids()}.
     *
     * @return string[]
     */
    public function get_group_paths() {
        $ids = $this->get_group_ids();
        if (empty($ids)) {
            return [];
        }
        $paths = $this->decode_group_list($this->get('selectedgrouppaths', ''));
        if (count($paths) === count($ids)) {
            return $paths;
        }
        // Legacy single path, or mismatched lists: pad with ids.
        $legacy = (string) $this->get('selectedgrouppath', '');
        $result = [];
        foreach ($ids as $i => $id) {
            if (isset($paths[$i]) && $paths[$i] !== '') {
                $result[] = $paths[$i];
            } else if ($i === 0 && $legacy !== '') {
                $result[] = $legacy;
            } else {
                $result[] = $id;
            }
        }
        return $result;
    }

    /**
     * First selected RHSSO group id (legacy helper).
     *
     * @return string
     */
    public function get_group_id() {
        $ids = $this->get_group_ids();
        return $ids[0] ?? '';
    }

    /**
     * Display string for selected group path(s).
     *
     * @return string Comma-separated paths, or empty when none selected.
     */
    public function get_group_path() {
        return implode(', ', $this->get_group_paths());
    }

    /**
     * Persist one or more selected groups.
     *
     * @param array $groups Map of group id => path (path may equal id as fallback).
     * @return void
     */
    public function set_groups(array $groups) {
        $ids = [];
        $paths = [];
        foreach ($groups as $id => $path) {
            $id = (string) $id;
            if ($id === '') {
                continue;
            }
            $ids[] = $id;
            $paths[] = (string) ($path !== '' ? $path : $id);
        }

        $idsjson = json_encode(array_values($ids));
        $pathsjson = json_encode(array_values($paths));
        set_config('selectedgroupids', $idsjson, self::COMPONENT);
        set_config('selectedgrouppaths', $pathsjson, self::COMPONENT);
        $this->raw->selectedgroupids = $idsjson;
        $this->raw->selectedgrouppaths = $pathsjson;

        // Keep legacy scalars in sync (first group, or empty when cleared).
        $firstid = $ids[0] ?? '';
        $firstpath = $paths[0] ?? '';
        set_config('selectedgroupid', $firstid, self::COMPONENT);
        set_config('selectedgrouppath', $firstpath, self::COMPONENT);
        $this->raw->selectedgroupid = $firstid;
        $this->raw->selectedgrouppath = $firstpath;
    }

    /**
     * Persist a single selected group id and path (legacy wrapper).
     *
     * @param string $groupid
     * @param string $grouppath
     * @return void
     */
    public function set_group($groupid, $grouppath) {
        if ($groupid === '') {
            $this->set_groups([]);
            return;
        }
        $this->set_groups([(string) $groupid => (string) $grouppath]);
    }

    /**
     * Whether the minimum connection configuration is present.
     *
     * @return bool
     */
    public function is_connection_configured() {
        return $this->get_base_url() !== ''
            && $this->get_realm() !== ''
            && $this->get_client_id() !== ''
            && $this->get_client_secret() !== '';
    }

    /**
     * Whether at least one group has been selected.
     *
     * @return bool
     */
    public function is_group_selected() {
        return !empty($this->get_group_ids());
    }

    /**
     * Decode a JSON list of group ids or paths from config.
     *
     * @param string $raw
     * @return string[]
     */
    protected function decode_group_list($raw) {
        if ($raw === '' || $raw === null) {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $result = [];
        foreach ($decoded as $value) {
            $value = (string) $value;
            if ($value !== '') {
                $result[] = $value;
            }
        }
        return $result;
    }
}
