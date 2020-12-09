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
 * OpenDSA module library functions tests
 *
 * @package    mod_opendsa
 * @category   test
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/opendsa/lib.php');

/**
 * OpenDSA module library functions tests
 *
 * @package    mod_opendsa
 * @category   test
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_opendsa_lib_testcase extends externallib_advanced_testcase {

    /**
     * Test opendsa_view
     * @return void
     */
    public function test_opendsa_view() {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));
        $context = context_module::instance($opendsa->cmid);
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        opendsa_view($opendsa, $course, $cm, $context);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_opendsa\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/opendsa/view.php', array('id' => $cm->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test opendsa_can_view_results
     * @return void
     */
    public function test_opendsa_can_view_results() {
        global $DB, $USER;

        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));
        $context = context_module::instance($opendsa->cmid);
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Default values are false, user cannot view results.
        $canview = opendsa_can_view_results($opendsa);
        $this->assertFalse($canview);

        // Show results forced.
        $opendsa->showresults = OPENDSA_SHOWRESULTS_ALWAYS;
        $DB->update_record('opendsa', $opendsa);
        $canview = opendsa_can_view_results($opendsa);
        $this->assertTrue($canview);

        // Add a time restriction (opendsa not open yet).
        $opendsa->timeopen = time() + YEARSECS;
        $DB->update_record('opendsa', $opendsa);
        $canview = opendsa_can_view_results($opendsa);
        $this->assertFalse($canview);

        // Show results after closing.
        $opendsa->timeopen = 0;
        $opendsa->showresults = OPENDSA_SHOWRESULTS_AFTER_CLOSE;
        $DB->update_record('opendsa', $opendsa);
        $canview = opendsa_can_view_results($opendsa);
        $this->assertFalse($canview);

        $opendsa->timeclose = time() - HOURSECS;
        $DB->update_record('opendsa', $opendsa);
        $canview = opendsa_can_view_results($opendsa);
        $this->assertTrue($canview);

        // Show results after answering.
        $opendsa->timeclose = 0;
        $opendsa->showresults = OPENDSA_SHOWRESULTS_AFTER_ANSWER;
        $DB->update_record('opendsa', $opendsa);
        $canview = opendsa_can_view_results($opendsa);
        $this->assertFalse($canview);

        // Get the first option.
        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[0], $opendsa, $USER->id, $course, $cm);

        $canview = opendsa_can_view_results($opendsa);
        $this->assertTrue($canview);

    }

    /**
     * @expectedException moodle_exception
     */
    public function test_opendsa_user_submit_response_validation() {
        global $USER;

        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $opendsa1 = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));
        $opendsa2 = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('opendsa', $opendsa1->id);

        $opendsawithoptions1 = opendsa_get_opendsa($opendsa1->id);
        $opendsawithoptions2 = opendsa_get_opendsa($opendsa2->id);
        $optionids1 = array_keys($opendsawithoptions1->option);
        $optionids2 = array_keys($opendsawithoptions2->option);

        // Make sure we cannot submit options from a different opendsa instance.
        opendsa_user_submit_response($optionids2[0], $opendsa1, $USER->id, $course, $cm);
    }

    /**
     * Test opendsa_get_user_response
     * @return void
     */
    public function test_opendsa_get_user_response() {
        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[0], $opendsa, $student->id, $course, $cm);
        $responses = opendsa_get_user_response($opendsa, $student->id);
        $this->assertCount(1, $responses);
        $response = array_shift($responses);
        $this->assertEquals($optionids[0], $response->optionid);

        // Multiple responses.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id, 'allowmultiple' => 1));
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        // Submit a response with the options reversed.
        $selections = $optionids;
        rsort($selections);
        opendsa_user_submit_response($selections, $opendsa, $student->id, $course, $cm);
        $responses = opendsa_get_user_response($opendsa, $student->id);
        $this->assertCount(count($optionids), $responses);
        foreach ($responses as $resp) {
            $this->assertEquals(array_shift($optionids), $resp->optionid);
        }
    }

    /**
     * Test opendsa_get_my_response
     * @return void
     */
    public function test_opendsa_get_my_response() {
        global $USER;

        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[0], $opendsa, $USER->id, $course, $cm);
        $responses = opendsa_get_my_response($opendsa);
        $this->assertCount(1, $responses);
        $response = array_shift($responses);
        $this->assertEquals($optionids[0], $response->optionid);

        // Multiple responses.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id, 'allowmultiple' => 1));
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        // Submit a response with the options reversed.
        $selections = $optionids;
        rsort($selections);
        opendsa_user_submit_response($selections, $opendsa, $USER->id, $course, $cm);
        $responses = opendsa_get_my_response($opendsa);
        $this->assertCount(count($optionids), $responses);
        foreach ($responses as $resp) {
            $this->assertEquals(array_shift($optionids), $resp->optionid);
        }
    }

    /**
     * Test opendsa_get_availability_status
     * @return void
     */
    public function test_opendsa_get_availability_status() {
        global $USER;

        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));

        // No time restrictions and updates allowed.
        list($status, $warnings) = opendsa_get_availability_status($opendsa, false);
        $this->assertEquals(true, $status);
        $this->assertCount(0, $warnings);

        // No updates allowed, but haven't answered yet.
        $opendsa->allowupdate = false;
        list($status, $warnings) = opendsa_get_availability_status($opendsa, false);
        $this->assertEquals(true, $status);
        $this->assertCount(0, $warnings);

        // No updates allowed and have answered.
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);
        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);
        opendsa_user_submit_response($optionids[0], $opendsa, $USER->id, $course, $cm);
        list($status, $warnings) = opendsa_get_availability_status($opendsa, false);
        $this->assertEquals(false, $status);
        $this->assertCount(1, $warnings);
        $this->assertEquals('opendsasaved', array_keys($warnings)[0]);

        $opendsa->allowupdate = true;

        // With time restrictions, still open.
        $opendsa->timeopen = time() - DAYSECS;
        $opendsa->timeclose = time() + DAYSECS;
        list($status, $warnings) = opendsa_get_availability_status($opendsa, false);
        $this->assertEquals(true, $status);
        $this->assertCount(0, $warnings);

        // OpenDSA not open yet.
        $opendsa->timeopen = time() + DAYSECS;
        $opendsa->timeclose = $opendsa->timeopen + DAYSECS;
        list($status, $warnings) = opendsa_get_availability_status($opendsa, false);
        $this->assertEquals(false, $status);
        $this->assertCount(1, $warnings);
        $this->assertEquals('notopenyet', array_keys($warnings)[0]);

        // OpenDSA closed.
        $opendsa->timeopen = time() - DAYSECS;
        $opendsa->timeclose = time() - 1;
        list($status, $warnings) = opendsa_get_availability_status($opendsa, false);
        $this->assertEquals(false, $status);
        $this->assertCount(1, $warnings);
        $this->assertEquals('expired', array_keys($warnings)[0]);
    }

    /*
     * The opendsa's event should not be shown to a user when the user cannot view the opendsa activity at all.
     */
    public function test_opendsa_core_calendar_provide_event_action_in_hidden_section() {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
                'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN);

        // Set sections 0 as hidden.
        set_section_visible($course->id, 0, 0);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event is not shown at all.
        $this->assertNull($actionevent);
    }

    /*
     * The opendsa's event should not be shown to a user who does not have permission to view the opendsa.
     */
    public function test_opendsa_core_calendar_provide_event_action_for_non_user() {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
                'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory);

        // Confirm the event is not shown at all.
        $this->assertNull($actionevent);
    }

    public function test_opendsa_core_calendar_provide_event_action_open() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('viewopendsas', 'opendsa'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_opendsa_core_calendar_provide_event_action_open_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('viewopendsas', 'opendsa'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    /**
     * An event should not have an action if the user has already submitted a response
     * to the opendsa activity.
     */
    public function test_opendsa_core_calendar_provide_event_action_already_submitted() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[0], $opendsa, $student->id, $course, $cm);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        $this->setUser($student);

        // Decorate action event.
        $action = mod_opendsa_core_calendar_provide_event_action($event, $factory);

        // Confirm no action was returned if the user has already submitted the
        // opendsa activity.
        $this->assertNull($action);
    }

    /**
     * An event should not have an action if the user has already submitted a response
     * to the opendsa activity.
     */
    public function test_opendsa_core_calendar_provide_event_action_already_submitted_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[0], $opendsa, $student->id, $course, $cm);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $action = mod_opendsa_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm no action was returned if the user has already submitted the
        // opendsa activity.
        $this->assertNull($action);
    }

    public function test_opendsa_core_calendar_provide_event_action_closed() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        $timeclose = time() - DAYSECS;
        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
            'timeclose' => $timeclose));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN, $timeclose - 1);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $action = mod_opendsa_core_calendar_provide_event_action($event, $factory);

        // Confirm not action was provided for a closed activity.
        $this->assertNull($action);
    }

    public function test_opendsa_core_calendar_provide_event_action_closed_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $timeclose = time() - DAYSECS;
        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
            'timeclose' => $timeclose));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN, $timeclose - 1);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $action = mod_opendsa_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm not action was provided for a closed activity.
        $this->assertNull($action);
    }

    public function test_opendsa_core_calendar_provide_event_action_open_in_future() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        $timeopen = time() + DAYSECS;
        $timeclose = $timeopen + DAYSECS;

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
            'timeopen' => $timeopen, 'timeclose' => $timeclose));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN, $timeopen);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('viewopendsas', 'opendsa'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_opendsa_core_calendar_provide_event_action_open_in_future_for_user() {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $timeopen = time() + DAYSECS;
        $timeclose = $timeopen + DAYSECS;

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id,
            'timeopen' => $timeopen, 'timeclose' => $timeclose));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN, $timeopen);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('viewopendsas', 'opendsa'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_opendsa_core_calendar_provide_event_action_no_time_specified() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('viewopendsas', 'opendsa'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_opendsa_core_calendar_provide_event_action_no_time_specified_for_user() {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa.
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id, OPENDSA_EVENT_TYPE_OPEN);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('viewopendsas', 'opendsa'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_opendsa_core_calendar_provide_event_action_already_completed() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Get some additional data.
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_opendsa_core_calendar_provide_event_action_already_completed_for_user() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $opendsa = $this->getDataGenerator()->create_module('opendsa', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Get some additional data.
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed for the student.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm, $student->id);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_opendsa_core_calendar_provide_event_action($event, $factory, $student->id);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid
     * @param int $instanceid The opendsa id.
     * @param string $eventtype The event type. eg. OPENDSA_EVENT_TYPE_OPEN.
     * @param int|null $timestart The start timestamp for the event
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype, $timestart = null) {
        $event = new stdClass();
        $event->name = 'Calendar event';
        $event->modulename = 'opendsa';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;

        if ($timestart) {
            $event->timestart = $timestart;
        } else {
            $event->timestart = time();
        }

        return calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_opendsa_completion_get_active_rule_descriptions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionsubmit' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $opendsa1 = $this->getDataGenerator()->create_module('opendsa', [
            'course' => $course->id,
            'completion' => 2,
            'completionsubmit' => 1
        ]);
        $opendsa2 = $this->getDataGenerator()->create_module('opendsa', [
            'course' => $course->id,
            'completion' => 2,
            'completionsubmit' => 0
        ]);
        $cm1 = cm_info::create(get_coursemodule_from_instance('opendsa', $opendsa1->id));
        $cm2 = cm_info::create(get_coursemodule_from_instance('opendsa', $opendsa2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new stdClass();
        $moddefaults->customdata = ['customcompletionrules' => ['completionsubmit' => 1]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [get_string('completionsubmit', 'opendsa')];
        $this->assertEquals(mod_opendsa_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_opendsa_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_opendsa_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_opendsa_get_completion_active_rule_descriptions(new stdClass()), []);
    }

    /**
     * An unkown event type should not change the opendsa instance.
     */
    public function test_mod_opendsa_core_calendar_event_timestart_updated_unknown_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $opendsagenerator = $generator->get_plugin_generator('mod_opendsa');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $opendsa = $opendsagenerator->create_instance(['course' => $course->id]);
        $opendsa->timeopen = $timeopen;
        $opendsa->timeclose = $timeclose;
        $DB->update_record('opendsa', $opendsa);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa',
            'instance' => $opendsa->id,
            'eventtype' => OPENDSA_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        mod_opendsa_core_calendar_event_timestart_updated($event, $opendsa);

        $opendsa = $DB->get_record('opendsa', ['id' => $opendsa->id]);
        $this->assertEquals($timeopen, $opendsa->timeopen);
        $this->assertEquals($timeclose, $opendsa->timeclose);
    }

    /**
     * A OPENDSA_EVENT_TYPE_OPEN event should update the timeopen property of
     * the opendsa activity.
     */
    public function test_mod_opendsa_core_calendar_event_timestart_updated_open_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $opendsagenerator = $generator->get_plugin_generator('mod_opendsa');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $opendsa = $opendsagenerator->create_instance(['course' => $course->id]);
        $opendsa->timeopen = $timeopen;
        $opendsa->timeclose = $timeclose;
        $opendsa->timemodified = $timemodified;
        $DB->update_record('opendsa', $opendsa);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa',
            'instance' => $opendsa->id,
            'eventtype' => OPENDSA_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // Trigger and capture the event when adding a contact.
        $sink = $this->redirectEvents();

        mod_opendsa_core_calendar_event_timestart_updated($event, $opendsa);

        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });

        $opendsa = $DB->get_record('opendsa', ['id' => $opendsa->id]);
        // Ensure the timeopen property matches the event timestart.
        $this->assertEquals($newtimeopen, $opendsa->timeopen);
        // Ensure the timeclose isn't changed.
        $this->assertEquals($timeclose, $opendsa->timeclose);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $opendsa->timemodified);
        // Confirm that a module updated event is fired when the module
        // is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * A OPENDSA_EVENT_TYPE_CLOSE event should update the timeclose property of
     * the opendsa activity.
     */
    public function test_mod_opendsa_core_calendar_event_timestart_updated_close_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $opendsagenerator = $generator->get_plugin_generator('mod_opendsa');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $opendsa = $opendsagenerator->create_instance(['course' => $course->id]);
        $opendsa->timeopen = $timeopen;
        $opendsa->timeclose = $timeclose;
        $opendsa->timemodified = $timemodified;
        $DB->update_record('opendsa', $opendsa);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa',
            'instance' => $opendsa->id,
            'eventtype' => OPENDSA_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // Trigger and capture the event when adding a contact.
        $sink = $this->redirectEvents();

        mod_opendsa_core_calendar_event_timestart_updated($event, $opendsa);

        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });

        $opendsa = $DB->get_record('opendsa', ['id' => $opendsa->id]);
        // Ensure the timeclose property matches the event timestart.
        $this->assertEquals($newtimeclose, $opendsa->timeclose);
        // Ensure the timeopen isn't changed.
        $this->assertEquals($timeopen, $opendsa->timeopen);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $opendsa->timemodified);
        // Confirm that a module updated event is fired when the module
        // is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * An unkown event type should not have any limits
     */
    public function test_mod_opendsa_core_calendar_get_valid_event_timestart_range_unknown_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $opendsa = new \stdClass();
        $opendsa->timeopen = $timeopen;
        $opendsa->timeclose = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa',
            'instance' => 1,
            'eventtype' => OPENDSA_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list ($min, $max) = mod_opendsa_core_calendar_get_valid_event_timestart_range($event, $opendsa);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The open event should be limited by the opendsa's timeclose property, if it's set.
     */
    public function test_mod_opendsa_core_calendar_get_valid_event_timestart_range_open_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $opendsa = new \stdClass();
        $opendsa->timeopen = $timeopen;
        $opendsa->timeclose = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa',
            'instance' => 1,
            'eventtype' => OPENDSA_EVENT_TYPE_OPEN,
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_opendsa_core_calendar_get_valid_event_timestart_range($event, $opendsa);

        $this->assertNull($min);
        $this->assertEquals($timeclose, $max[0]);

        // No timeclose value should result in no upper limit.
        $opendsa->timeclose = 0;
        list ($min, $max) = mod_opendsa_core_calendar_get_valid_event_timestart_range($event, $opendsa);

        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The close event should be limited by the opendsa's timeopen property, if it's set.
     */
    public function test_mod_opendsa_core_calendar_get_valid_event_timestart_range_close_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $opendsa = new \stdClass();
        $opendsa->timeopen = $timeopen;
        $opendsa->timeclose = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa',
            'instance' => 1,
            'eventtype' => OPENDSA_EVENT_TYPE_CLOSE,
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_opendsa_core_calendar_get_valid_event_timestart_range($event, $opendsa);

        $this->assertEquals($timeopen, $min[0]);
        $this->assertNull($max);

        // No timeclose value should result in no upper limit.
        $opendsa->timeopen = 0;
        list ($min, $max) = mod_opendsa_core_calendar_get_valid_event_timestart_range($event, $opendsa);

        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * Test opendsa_user_submit_response for a opendsa with specific options.
     * Options:
     * allowmultiple: false
     * limitanswers: false
     */
    public function test_opendsa_user_submit_response_no_multiple_no_limits() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $user2 = $generator->create_user();

        // User must be enrolled in the course for opendsa limits to be honoured properly.
        $role = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);

        // Create opendsa, with updates allowed and a two options both limited to 1 response each.
        $opendsa = $generator->get_plugin_generator('mod_opendsa')->create_instance([
            'course' => $course->id,
            'allowupdate' => false,
            'limitanswers' => false,
            'allowmultiple' => false,
            'option' => ['red', 'green'],
        ]);
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Get the opendsa, with options and limits included.
        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        // Now, save an response which includes the first option.
        $this->assertNull(opendsa_user_submit_response($optionids[0], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that saving again without changing the selected option will not throw a 'opendsa full' exception.
        $this->assertNull(opendsa_user_submit_response($optionids[1], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that saving a response for student 2 including the first option is allowed.
        $this->assertNull(opendsa_user_submit_response($optionids[0], $opendsawithoptions, $user2->id, $course, $cm));

        // Confirm that trying to save multiple options results in an exception.
        $this->expectException('moodle_exception');
        opendsa_user_submit_response([$optionids[1], $optionids[1]], $opendsawithoptions, $user->id, $course, $cm);
    }

    /**
     * Test opendsa_user_submit_response for a opendsa with specific options.
     * Options:
     * allowmultiple: true
     * limitanswers: false
     */
    public function test_opendsa_user_submit_response_multiples_no_limits() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $user2 = $generator->create_user();

        // User must be enrolled in the course for opendsa limits to be honoured properly.
        $role = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);

        // Create opendsa, with updates allowed and a two options both limited to 1 response each.
        $opendsa = $generator->get_plugin_generator('mod_opendsa')->create_instance([
            'course' => $course->id,
            'allowupdate' => false,
            'allowmultiple' => true,
            'limitanswers' => false,
            'option' => ['red', 'green'],
        ]);
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Get the opendsa, with options and limits included.
        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        // Save a response which includes the first option only.
        $this->assertNull(opendsa_user_submit_response([$optionids[0]], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that adding an option to the response is allowed.
        $this->assertNull(opendsa_user_submit_response([$optionids[0], $optionids[1]], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that saving a response for student 2 including the first option is allowed.
        $this->assertNull(opendsa_user_submit_response($optionids[0], $opendsawithoptions, $user2->id, $course, $cm));

        // Confirm that removing an option from the response is allowed.
        $this->assertNull(opendsa_user_submit_response([$optionids[0]], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that removing all options from the response is not allowed via this method.
        $this->expectException('moodle_exception');
        opendsa_user_submit_response([], $opendsawithoptions, $user->id, $course, $cm);
    }

    /**
     * Test opendsa_user_submit_response for a opendsa with specific options.
     * Options:
     * allowmultiple: false
     * limitanswers: true
     */
    public function test_opendsa_user_submit_response_no_multiples_limits() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $user2 = $generator->create_user();

        // User must be enrolled in the course for opendsa limits to be honoured properly.
        $role = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);

        // Create opendsa, with updates allowed and a two options both limited to 1 response each.
        $opendsa = $generator->get_plugin_generator('mod_opendsa')->create_instance([
            'course' => $course->id,
            'allowupdate' => false,
            'allowmultiple' => false,
            'limitanswers' => true,
            'option' => ['red', 'green'],
            'limit' => [1, 1]
        ]);
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Get the opendsa, with options and limits included.
        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        // Save a response which includes the first option only.
        $this->assertNull(opendsa_user_submit_response($optionids[0], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that changing the option in the response is allowed.
        $this->assertNull(opendsa_user_submit_response($optionids[1], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that limits are respected by trying to save the same option as another user.
        $this->expectException('moodle_exception');
        opendsa_user_submit_response($optionids[1], $opendsawithoptions, $user2->id, $course, $cm);
    }

    /**
     * Test opendsa_user_submit_response for a opendsa with specific options.
     * Options:
     * allowmultiple: true
     * limitanswers: true
     */
    public function test_opendsa_user_submit_response_multiples_limits() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $user2 = $generator->create_user();

        // User must be enrolled in the course for opendsa limits to be honoured properly.
        $role = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);

        // Create opendsa, with updates allowed and a two options both limited to 1 response each.
        $opendsa = $generator->get_plugin_generator('mod_opendsa')->create_instance([
            'course' => $course->id,
            'allowupdate' => false,
            'allowmultiple' => true,
            'limitanswers' => true,
            'option' => ['red', 'green'],
            'limit' => [1, 1]
        ]);
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Get the opendsa, with options and limits included.
        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        // Now, save a response which includes the first option only.
        $this->assertNull(opendsa_user_submit_response([$optionids[0]], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that changing the option in the response is allowed.
        $this->assertNull(opendsa_user_submit_response([$optionids[1]], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that adding an option to the response is allowed.
        $this->assertNull(opendsa_user_submit_response([$optionids[0], $optionids[1]], $opendsawithoptions, $user->id, $course, $cm));

        // Confirm that limits are respected by trying to save the same option as another user.
        $this->expectException('moodle_exception');
        opendsa_user_submit_response($optionids[1], $opendsawithoptions, $user2->id, $course, $cm);
    }

    /**
     * A user who does not have capabilities to add events to the calendar should be able to create an opendsa.
     */
    public function test_creation_with_no_calendar_capabilities() {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/calendar:manageentries', CAP_PROHIBIT, $roleid, $context, true);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_opendsa');
        // Create an instance as a user without the calendar capabilities.
        $this->setUser($user);
        $time = time();
        $params = array(
            'course' => $course->id,
            'timeopen' => $time + 200,
            'timeclose' => $time + 500,
        );
        $generator->create_instance($params);
    }
}
