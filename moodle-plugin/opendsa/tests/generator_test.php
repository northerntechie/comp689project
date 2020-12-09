<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Generator tests.
 *
 * @package    mod_opendsa
 * @copyright  2013 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generator tests class.
 *
 * @package    mod_opendsa
 * @copyright  2013 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_opendsa_generator_testcase extends advanced_testcase {

    public function test_create_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('opendsa', array('course' => $course->id)));
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));
        $this->assertEquals(1, $DB->count_records('opendsa', array('course' => $course->id)));
        $this->assertTrue($DB->record_exists('opendsa', array('course' => $course->id)));
        $this->assertTrue($DB->record_exists('opendsa', array('id' => $opendsa->id)));

        $params = array('course' => $course->id, 'name' => 'One more opendsa');
        $opendsa = $this->getDataGenerator()->create_module('opendsa', $params);
        $this->assertEquals(2, $DB->count_records('opendsa', array('course' => $course->id)));
        $this->assertEquals('One more opendsa', $DB->get_field_select('opendsa', 'name', 'id = :id', array('id' => $opendsa->id)));

        $params = new stdClass();
        $params->course = $course->id;
        $params->option = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $opendsa = $this->getDataGenerator()->create_module('opendsa', $params);
        $this->assertEquals(5, $DB->count_records('opendsa_options', array('opendsaid' => $opendsa->id)));
    }
}
