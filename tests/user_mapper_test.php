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

use local_rhssosync\local\config;
use local_rhssosync\local\user_mapper;

/**
 * Tests for the attribute mapping.
 *
 * @package    local_rhssosync
 * @covers     \local_rhssosync\local\user_mapper
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_mapper_test extends \advanced_testcase {
    /**
     * Map using the RHSSO username field.
     */
    public function test_map_from_username_field(): void {
        $this->resetAfterTest();
        $mapper = new user_mapper(new config([
            'usernamesource' => 'username',
        ]));

        $mapped = $mapper->map([
            'id'        => 'uuid-1',
            'username'  => 'JDOE',
            'firstName' => 'Jane',
            'lastName'  => 'Doe',
            'email'     => 'Jane.Doe@example.org',
        ]);

        $this->assertTrue($mapped->valid);
        $this->assertSame('uuid-1', $mapped->rhssouserid);
        $this->assertSame('jdoe', $mapped->username);
        $this->assertSame('Jane', $mapped->firstname);
        $this->assertSame('Doe', $mapped->lastname);
        $this->assertSame('jane.doe@example.org', $mapped->email);
    }

    /**
     * Map using a named attribute (case-insensitive) for the username.
     */
    public function test_map_from_attribute(): void {
        $this->resetAfterTest();
        $mapper = new user_mapper(new config([
            'usernamesource'    => 'attribute',
            'usernameattribute' => 'sAMAccountName',
        ]));

        $mapped = $mapper->map([
            'id'         => 'uuid-2',
            'username'   => 'ignored-keycloak-name',
            'firstName'  => 'Bob',
            'lastName'   => 'Smith',
            'email'      => 'bob@example.org',
            'attributes' => [
                'samaccountname' => ['BSMITH'],
            ],
        ]);

        $this->assertTrue($mapped->valid);
        $this->assertSame('bsmith', $mapped->username);
    }

    /**
     * Missing username makes the mapping invalid.
     */
    public function test_missing_username_invalid(): void {
        $this->resetAfterTest();
        $mapper = new user_mapper(new config([
            'usernamesource'    => 'attribute',
            'usernameattribute' => 'sAMAccountName',
        ]));

        $mapped = $mapper->map([
            'id'    => 'uuid-3',
            'email' => 'noone@example.org',
        ]);

        $this->assertFalse($mapped->valid);
        $this->assertContains('nousername', $mapped->errors);
    }

    /**
     * Invalid email is flagged but names fall back to the username.
     */
    public function test_invalid_email_and_name_fallback(): void {
        $this->resetAfterTest();
        $mapper = new user_mapper(new config(['usernamesource' => 'username']));

        $mapped = $mapper->map([
            'id'       => 'uuid-4',
            'username' => 'nomail',
            'email'    => 'not-an-email',
        ]);

        $this->assertFalse($mapped->valid);
        $this->assertContains('invalidemail', $mapped->errors);
        $this->assertSame('nomail', $mapped->firstname);
        $this->assertSame('nomail', $mapped->lastname);
    }
}
