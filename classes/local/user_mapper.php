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
 * Maps a raw RHSSO user representation to Moodle user fields.
 *
 * The mapping is driven entirely by configuration so that the sAMAccountName
 * may come from the RHSSO username or from a named attribute.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_mapper {
    /** @var config */
    protected $config;

    /**
     * Constructor.
     *
     * @param config $config
     */
    public function __construct(config $config) {
        $this->config = $config;
    }

    /**
     * Map a single RHSSO user representation.
     *
     * @param array $rep Raw user representation (associative array).
     * @return \stdClass Object with rhssouserid, username, firstname, lastname,
     *                   email and a boolean valid flag plus errors array.
     */
    public function map(array $rep) {
        $mapped = new \stdClass();
        $mapped->rhssouserid = isset($rep['id']) ? (string) $rep['id'] : '';
        $mapped->username = $this->normalise_username($this->resolve_username($rep));
        $firstname = $this->resolve_attribute($rep, $this->config->get_firstname_attribute());
        $lastname = $this->resolve_attribute($rep, $this->config->get_lastname_attribute());
        $email = $this->resolve_attribute($rep, $this->config->get_email_attribute());
        $mapped->firstname = trim((string) $firstname);
        $mapped->lastname = trim((string) $lastname);
        $mapped->email = \core_text::strtolower(trim((string) $email));
        $mapped->errors = [];

        if ($mapped->rhssouserid === '') {
            $mapped->errors[] = 'norhssoid';
        }
        if ($mapped->username === '') {
            $mapped->errors[] = 'nousername';
        }
        if ($mapped->email === '' || !validate_email($mapped->email)) {
            $mapped->errors[] = 'invalidemail';
        }
        // Provide non-empty names so user_create_user does not fail; fall back
        // to the username when the directory has no value.
        if ($mapped->firstname === '') {
            $mapped->firstname = $mapped->username;
        }
        if ($mapped->lastname === '') {
            $mapped->lastname = $mapped->username;
        }

        $mapped->valid = empty($mapped->errors);
        return $mapped;
    }

    /**
     * Resolve the username according to the configured source.
     *
     * @param array $rep
     * @return string
     */
    protected function resolve_username(array $rep) {
        if ($this->config->get_username_source() === 'attribute') {
            return (string) $this->resolve_attribute($rep, $this->config->get_username_attribute());
        }
        return isset($rep['username']) ? (string) $rep['username'] : '';
    }

    /**
     * Resolve a value by name from top-level fields or the attributes map.
     *
     * Lookup order: exact top-level key, case-insensitive top-level key,
     * case-insensitive attributes key (first value).
     *
     * @param array $rep
     * @param string $name
     * @return string
     */
    protected function resolve_attribute(array $rep, $name) {
        if ($name === '') {
            return '';
        }

        // Exact top-level match (e.g. firstName, lastName, email, username).
        if (array_key_exists($name, $rep) && is_scalar($rep[$name])) {
            return (string) $rep[$name];
        }

        // Case-insensitive top-level match.
        $lower = \core_text::strtolower($name);
        foreach ($rep as $key => $value) {
            if (is_scalar($value) && \core_text::strtolower((string) $key) === $lower) {
                return (string) $value;
            }
        }

        // Attributes map: name => [values...].
        if (!empty($rep['attributes']) && is_array($rep['attributes'])) {
            foreach ($rep['attributes'] as $key => $values) {
                if (\core_text::strtolower((string) $key) === $lower) {
                    if (is_array($values)) {
                        return isset($values[0]) ? (string) $values[0] : '';
                    }
                    return (string) $values;
                }
            }
        }

        return '';
    }

    /**
     * Normalise a username to the Moodle convention.
     *
     * @param string $username
     * @return string
     */
    protected function normalise_username($username) {
        global $CFG;
        $username = trim((string) $username);
        if ($username === '') {
            return '';
        }
        // Match Moodle's default behaviour: lowercase unless extended chars are
        // explicitly allowed in a way that preserves case.
        if (empty($CFG->extendedusernamechars)) {
            $username = \core_text::strtolower($username);
        }
        return $username;
    }
}
