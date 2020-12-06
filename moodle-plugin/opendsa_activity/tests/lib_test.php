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
 * Unit tests for mod/opendsa_activity/lib.php.
 *
 * @package    mod_opendsa_activity
 * @category   test
 * @copyright  2017 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/opendsa_activity/lib.php');

/**
 * Unit tests for mod/opendsa_activity/lib.php.
 *
 * @copyright  2017 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class mod_opendsa_activity_lib_testcase extends advanced_testcase {
    /**
     * Test for _get_group_override_priorities().
     */
    public function test_opendsa_activity_get_group_override_priorities() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $opendsa_activitymodule = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id));

        $this->assertNull(opendsa_activity_get_group_override_priorities($opendsa_activitymodule->id));

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        $now = 100;
        $override1 = (object)[
            'opendsa_activity_id' => $opendsa_activitymodule->id,
            'groupid' => $group1->id,
            'available' => $now,
            'deadline' => $now + 20
        ];
        $DB->insert_record('opendsa_activity_overrides', $override1);

        $override2 = (object)[
            'opendsa_activity_id' => $opendsa_activitymodule->id,
            'groupid' => $group2->id,
            'available' => $now - 10,
            'deadline' => $now + 10
        ];
        $DB->insert_record('opendsa_activity_overrides', $override2);

        $priorities = opendsa_activity_get_group_override_priorities($opendsa_activitymodule->id);
        $this->assertNotEmpty($priorities);

        $openpriorities = $priorities['open'];
        // Override 2's time open has higher priority since it is sooner than override 1's.
        $this->assertEquals(2, $openpriorities[$override1->available]);
        $this->assertEquals(1, $openpriorities[$override2->available]);

        $closepriorities = $priorities['close'];
        // Override 1's time close has higher priority since it is later than override 2's.
        $this->assertEquals(1, $closepriorities[$override1->deadline]);
        $this->assertEquals(2, $closepriorities[$override2->deadline]);
    }

    /**
     * Test check_updates_since callback.
     */
    public function test_check_updates_since() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = new stdClass();
        $course->groupmode = SEPARATEGROUPS;
        $course->groupmodeforce = true;
        $course = $this->getDataGenerator()->create_course($course);

        // Create user.
        $studentg1 = self::getDataGenerator()->create_user();
        $teacherg1 = self::getDataGenerator()->create_user();
        $studentg2 = self::getDataGenerator()->create_user();

        // User enrolment.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($studentg1->id, $course->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($teacherg1->id, $course->id, $teacherrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($studentg2->id, $course->id, $studentrole->id, 'manual');

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group1, $studentg1);
        groups_add_member($group2, $studentg2);

        $this->setCurrentTimeStart();
        $record = array(
            'course' => $course->id,
            'custom' => 0,
            'feedback' => 1,
        );
        $opendsa_activitymodule = $this->getDataGenerator()->create_module('opendsa_activity', $record);
        // Convert to a opendsa_activity object.
        $opendsa_activity = new opendsa_activity($opendsa_activitymodule);
        $cm = $opendsa_activity->cm;
        $cm = cm_info::create($cm);

        // Check that upon creation, the updates are only about the new configuration created.
        $onehourago = time() - HOURSECS;
        $updates = opendsa_activity_check_updates_since($cm, $onehourago);
        foreach ($updates as $el => $val) {
            if ($el == 'configuration') {
                $this->assertTrue($val->updated);
                $this->assertTimeCurrent($val->timeupdated);
            } else {
                $this->assertFalse($val->updated);
            }
        }

        // Set up a generator to create content.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');
        $tfrecord = $generator->create_question_truefalse($opendsa_activity);

        // Check now for pages and answers.
        $updates = opendsa_activity_check_updates_since($cm, $onehourago);
        $this->assertTrue($updates->pages->updated);
        $this->assertCount(1, $updates->pages->itemids);

        $this->assertTrue($updates->answers->updated);
        $this->assertCount(2, $updates->answers->itemids);

        // Now, do something in the opendsa_activity with the two users.
        $this->setUser($studentg1);
        mod_opendsa_activity_external::launch_attempt($opendsa_activity->id);
        $data = array(
            array(
                'name' => 'answerid',
                'value' => $DB->get_field('opendsa_activity_answers', 'id', array('pageid' => $tfrecord->id, 'jumpto' => -1)),
            ),
            array(
                'name' => '_qf__opendsa_activity_display_answer_form_truefalse',
                'value' => 1,
            )
        );
        mod_opendsa_activity_external::process_page($opendsa_activity->id, $tfrecord->id, $data);
        mod_opendsa_activity_external::finish_attempt($opendsa_activity->id);

        $this->setUser($studentg2);
        mod_opendsa_activity_external::launch_attempt($opendsa_activity->id);
        $data = array(
            array(
                'name' => 'answerid',
                'value' => $DB->get_field('opendsa_activity_answers', 'id', array('pageid' => $tfrecord->id, 'jumpto' => -1)),
            ),
            array(
                'name' => '_qf__opendsa_activity_display_answer_form_truefalse',
                'value' => 1,
            )
        );
        mod_opendsa_activity_external::process_page($opendsa_activity->id, $tfrecord->id, $data);
        mod_opendsa_activity_external::finish_attempt($opendsa_activity->id);

        $this->setUser($studentg1);
        $updates = opendsa_activity_check_updates_since($cm, $onehourago);

        // Check question attempts, timers and new grades.
        $this->assertTrue($updates->questionattempts->updated);
        $this->assertCount(1, $updates->questionattempts->itemids);

        $this->assertTrue($updates->grades->updated);
        $this->assertCount(1, $updates->grades->itemids);

        $this->assertTrue($updates->timers->updated);
        $this->assertCount(1, $updates->timers->itemids);

        // Now, as teacher, check that I can see the two users (even in separate groups).
        $this->setUser($teacherg1);
        $updates = opendsa_activity_check_updates_since($cm, $onehourago);
        $this->assertTrue($updates->userquestionattempts->updated);
        $this->assertCount(2, $updates->userquestionattempts->itemids);

        $this->assertTrue($updates->usergrades->updated);
        $this->assertCount(2, $updates->usergrades->itemids);

        $this->assertTrue($updates->usertimers->updated);
        $this->assertCount(2, $updates->usertimers->itemids);

        // Now, teacher can't access all groups.
        groups_add_member($group1, $teacherg1);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacherrole->id, context_module::instance($cm->id));
        accesslib_clear_all_caches_for_unit_testing();
        $updates = opendsa_activity_check_updates_since($cm, $onehourago);
        // I will see only the studentg1 updates.
        $this->assertTrue($updates->userquestionattempts->updated);
        $this->assertCount(1, $updates->userquestionattempts->itemids);

        $this->assertTrue($updates->usergrades->updated);
        $this->assertCount(1, $updates->usergrades->itemids);

        $this->assertTrue($updates->usertimers->updated);
        $this->assertCount(1, $updates->usertimers->itemids);
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_open() {
        $this->resetAfterTest();
        $this->setAdminUser();
        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a teacher and enrol into the course.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id,
            'available' => time() - DAYSECS, 'deadline' => time() + DAYSECS));
        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Log in as the teacher.
        $this->setUser($teacher);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();
        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory);
        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('startopendsa_activity', 'opendsa_activity'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_open_as_non_user() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id,
                'available' => time() - DAYSECS, 'deadline' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory);

        // Confirm the event is not shown at all.
        $this->assertNull($actionevent);
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_open_for_user() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id,
                'available' => time() - DAYSECS, 'deadline' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('startopendsa_activity', 'opendsa_activity'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_open_in_hidden_section() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id,
                'available' => time() - DAYSECS, 'deadline' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Set sections 0 as hidden.
        set_section_visible($course->id, 0, 0);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event is not shown at all.
        $this->assertNull($actionevent);
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_closed() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a teacher and enrol.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id,
            'deadline' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Now, log in as teacher.
        $this->setUser($teacher);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('startopendsa_activity', 'opendsa_activity'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_closed_for_user() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id,
                'deadline' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('startopendsa_activity', 'opendsa_activity'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_open_in_future() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a teacher and enrol into the course.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id,
            'available' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Now, log in as teacher.
        $this->setUser($teacher);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('startopendsa_activity', 'opendsa_activity'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_open_in_future_for_user() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id,
                'available' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('startopendsa_activity', 'opendsa_activity'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_no_time_specified() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a teacher and enrol into the course.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);
        // Now, log in as teacher.
        $this->setUser($teacher);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('startopendsa_activity', 'opendsa_activity'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_no_time_specified_for_user() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('startopendsa_activity', 'opendsa_activity'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_after_attempt() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create user.
        $student = self::getDataGenerator()->create_user();

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');
        $tfrecord = $generator->create_question_truefalse($opendsa_activity);

        // Now, do something in the opendsa_activity.
        $this->setUser($student);
        mod_opendsa_activity_external::launch_attempt($opendsa_activity->id);
        $data = array(
            array(
                'name' => 'answerid',
                'value' => $DB->get_field('opendsa_activity_answers', 'id', array('pageid' => $tfrecord->id, 'jumpto' => -1)),
            ),
            array(
                'name' => '_qf__opendsa_activity_display_answer_form_truefalse',
                'value' => 1,
            )
        );
        mod_opendsa_activity_external::process_page($opendsa_activity->id, $tfrecord->id, $data);
        mod_opendsa_activity_external::finish_attempt($opendsa_activity->id);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $action = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory);

        // Confirm there was no action for the user.
        $this->assertNull($action);
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_after_attempt_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create 2 students in the course.
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a opendsa_activity activity.
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id, OPENDSA_ACTIVITY_EVENT_TYPE_OPEN);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');
        $tfrecord = $generator->create_question_truefalse($opendsa_activity);

        // Now, do something in the opendsa_activity as student1.
        $this->setUser($student1);
        mod_opendsa_activity_external::launch_attempt($opendsa_activity->id);
        $data = array(
            array(
                'name' => 'answerid',
                'value' => $DB->get_field('opendsa_activity_answers', 'id', array('pageid' => $tfrecord->id, 'jumpto' => -1)),
            ),
            array(
                'name' => '_qf__opendsa_activity_display_answer_form_truefalse',
                'value' => 1,
            )
        );
        mod_opendsa_activity_external::process_page($opendsa_activity->id, $tfrecord->id, $data);
        mod_opendsa_activity_external::finish_attempt($opendsa_activity->id);

        // Now, log in as the other student.
        $this->setUser($student2);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $action = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory, $student1->id);

        // Confirm there was no action for the user.
        $this->assertNull($action);
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_already_completed() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Get some additional data.
        $cm = get_coursemodule_from_instance('opendsa_activity', $opendsa_activity->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_opendsa_activity_core_calendar_provide_event_action_already_completed_for_user() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Get some additional data.
        $cm = get_coursemodule_from_instance('opendsa_activity', $opendsa_activity->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $opendsa_activity->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed for the student.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm, $student->id);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_opendsa_activity_core_calendar_provide_event_action($event, $factory, $student->id);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid
     * @param int $instanceid The opendsa_activity id.
     * @param string $eventtype The event type. eg. OPENDSA_ACTIVITY_EVENT_TYPE_OPEN.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'opendsa_activity';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();
        return calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_opendsa_activity_completion_get_active_rule_descriptions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionsubmit' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 2]);
        $opendsa_activity1 = $this->getDataGenerator()->create_module('opendsa_activity', [
            'course' => $course->id,
            'completion' => 2,
            'completionendreached' => 1,
            'completiontimespent' => 3600
        ]);
        $opendsa_activity2 = $this->getDataGenerator()->create_module('opendsa_activity', [
            'course' => $course->id,
            'completion' => 2,
            'completionendreached' => 0,
            'completiontimespent' => 0
        ]);
        $cm1 = cm_info::create(get_coursemodule_from_instance('opendsa_activity', $opendsa_activity1->id));
        $cm2 = cm_info::create(get_coursemodule_from_instance('opendsa_activity', $opendsa_activity2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new stdClass();
        $moddefaults->customdata = ['customcompletionrules' => [
            'completionendreached' => 1,
            'completiontimespent' => 3600
        ]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [
            get_string('completionendreached_desc', 'opendsa_activity'),
            get_string('completiontimespentdesc', 'opendsa_activity', format_time(3600)),
        ];
        $this->assertEquals(mod_opendsa_activity_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_opendsa_activity_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_opendsa_activity_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_opendsa_activity_get_completion_active_rule_descriptions(new stdClass()), []);
    }

    /**
     * An unknown event type should not change the opendsa_activity instance.
     */
    public function test_mod_opendsa_activity_core_calendar_event_timestart_updated_unknown_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $opendsa_activitygenerator = $generator->get_plugin_generator('mod_opendsa_activity');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $opendsa_activity = $opendsa_activitygenerator->create_instance(['course' => $course->id]);
        $opendsa_activity->available = $timeopen;
        $opendsa_activity->deadline = $timeclose;
        $DB->update_record('opendsa_activity', $opendsa_activity);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa_activity',
            'instance' => $opendsa_activity->id,
            'eventtype' => OPENDSA_ACTIVITY_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        mod_opendsa_activity_core_calendar_event_timestart_updated($event, $opendsa_activity);
        $opendsa_activity = $DB->get_record('opendsa_activity', ['id' => $opendsa_activity->id]);
        $this->assertEquals($timeopen, $opendsa_activity->available);
        $this->assertEquals($timeclose, $opendsa_activity->deadline);
    }

    /**
     * A OPENDSA_ACTIVITY_EVENT_TYPE_OPEN event should update the available property of the opendsa_activity activity.
     */
    public function test_mod_opendsa_activity_core_calendar_event_timestart_updated_open_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $opendsa_activitygenerator = $generator->get_plugin_generator('mod_opendsa_activity');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $opendsa_activity = $opendsa_activitygenerator->create_instance(['course' => $course->id]);
        $opendsa_activity->available = $timeopen;
        $opendsa_activity->deadline = $timeclose;
        $opendsa_activity->timemodified = $timemodified;
        $DB->update_record('opendsa_activity', $opendsa_activity);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa_activity',
            'instance' => $opendsa_activity->id,
            'eventtype' => OPENDSA_ACTIVITY_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // Trigger and capture the event when adding a contact.
        $sink = $this->redirectEvents();
        mod_opendsa_activity_core_calendar_event_timestart_updated($event, $opendsa_activity);
        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });
        $opendsa_activity = $DB->get_record('opendsa_activity', ['id' => $opendsa_activity->id]);

        // Ensure the available property matches the event timestart.
        $this->assertEquals($newtimeopen, $opendsa_activity->available);

        // Ensure the deadline isn't changed.
        $this->assertEquals($timeclose, $opendsa_activity->deadline);

        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $opendsa_activity->timemodified);

        // Confirm that a module updated event is fired when the module is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * A OPENDSA_ACTIVITY_EVENT_TYPE_CLOSE event should update the deadline property of the opendsa_activity activity.
     */
    public function test_mod_opendsa_activity_core_calendar_event_timestart_updated_close_event() {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $opendsa_activitygenerator = $generator->get_plugin_generator('mod_opendsa_activity');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $opendsa_activity = $opendsa_activitygenerator->create_instance(['course' => $course->id]);
        $opendsa_activity->available = $timeopen;
        $opendsa_activity->deadline = $timeclose;
        $opendsa_activity->timemodified = $timemodified;
        $DB->update_record('opendsa_activity', $opendsa_activity);
        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa_activity',
            'instance' => $opendsa_activity->id,
            'eventtype' => OPENDSA_ACTIVITY_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose,
            'timeduration' => 86400,
            'visible' => 1
        ]);
        // Trigger and capture the event when adding a contact.
        $sink = $this->redirectEvents();
        mod_opendsa_activity_core_calendar_event_timestart_updated($event, $opendsa_activity);
        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });
        $opendsa_activity = $DB->get_record('opendsa_activity', ['id' => $opendsa_activity->id]);
        // Ensure the deadline property matches the event timestart.
        $this->assertEquals($newtimeclose, $opendsa_activity->deadline);
        // Ensure the available isn't changed.
        $this->assertEquals($timeopen, $opendsa_activity->available);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $opendsa_activity->timemodified);
        // Confirm that a module updated event is fired when the module is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * An unknown event type should not have any limits.
     */
    public function test_mod_opendsa_activity_core_calendar_get_valid_event_timestart_range_unknown_event() {
        global $CFG;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $opendsa_activity = new \stdClass();
        $opendsa_activity->available = $timeopen;
        $opendsa_activity->deadline = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa_activity',
            'instance' => 1,
            'eventtype' => OPENDSA_ACTIVITY_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list ($min, $max) = mod_opendsa_activity_core_calendar_get_valid_event_timestart_range($event, $opendsa_activity);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The open event should be limited by the opendsa_activity's deadline property, if it's set.
     */
    public function test_mod_opendsa_activity_core_calendar_get_valid_event_timestart_range_open_event() {
        global $CFG;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $opendsa_activity = new \stdClass();
        $opendsa_activity->available = $timeopen;
        $opendsa_activity->deadline = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa_activity',
            'instance' => 1,
            'eventtype' => OPENDSA_ACTIVITY_EVENT_TYPE_OPEN,
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_opendsa_activity_core_calendar_get_valid_event_timestart_range($event, $opendsa_activity);
        $this->assertNull($min);
        $this->assertEquals($timeclose, $max[0]);

        // No timeclose value should result in no upper limit.
        $opendsa_activity->deadline = 0;
        list ($min, $max) = mod_opendsa_activity_core_calendar_get_valid_event_timestart_range($event, $opendsa_activity);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The close event should be limited by the opendsa_activity's available property, if it's set.
     */
    public function test_mod_opendsa_activity_core_calendar_get_valid_event_timestart_range_close_event() {
        global $CFG;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $opendsa_activity = new \stdClass();
        $opendsa_activity->available = $timeopen;
        $opendsa_activity->deadline = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'opendsa_activity',
            'instance' => 1,
            'eventtype' => OPENDSA_ACTIVITY_EVENT_TYPE_CLOSE,
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_opendsa_activity_core_calendar_get_valid_event_timestart_range($event, $opendsa_activity);
        $this->assertEquals($timeopen, $min[0]);
        $this->assertNull($max);

        // No deadline value should result in no upper limit.
        $opendsa_activity->available = 0;
        list ($min, $max) = mod_opendsa_activity_core_calendar_get_valid_event_timestart_range($event, $opendsa_activity);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * A user who does not have capabilities to add events to the calendar should be able to create an opendsa_activity.
     */
    public function test_creation_with_no_calendar_capabilities() {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/calendar:manageentries', CAP_PROHIBIT, $roleid, $context, true);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_opendsa_activity');
        // Create an instance as a user without the calendar capabilities.
        $this->setUser($user);
        $time = time();
        $params = array(
            'course' => $course->id,
            'available' => $time + 200,
            'deadline' => $time + 2000,
        );
        $generator->create_instance($params);
    }
}
