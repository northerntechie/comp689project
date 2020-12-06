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
 * mod_opendsa_activity generator tests
 *
 * @package    mod_opendsa_activity
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Genarator tests class for mod_opendsa_activity.
 *
 * @package    mod_opendsa_activity
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_opendsa_activity_generator_testcase extends advanced_testcase {

    public function test_create_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('opendsa_activity', array('course' => $course->id)));
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course));
        $records = $DB->get_records('opendsa_activity', array('course' => $course->id), 'id');
        $this->assertEquals(1, count($records));
        $this->assertTrue(array_key_exists($opendsa_activity->id, $records));

        $params = array('course' => $course->id, 'name' => 'Another opendsa_activity');
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', $params);
        $records = $DB->get_records('opendsa_activity', array('course' => $course->id), 'id');
        $this->assertEquals(2, count($records));
        $this->assertEquals('Another opendsa_activity', $records[$opendsa_activity->id]->name);
    }

    public function test_create_content() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course));
        $opendsa_activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');

        $page1 = $opendsa_activitygenerator->create_content($opendsa_activity);
        $page2 = $opendsa_activitygenerator->create_content($opendsa_activity, array('title' => 'Custom title'));
        $records = $DB->get_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id), 'id');
        $this->assertEquals(2, count($records));
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals('Custom title', $records[$page2->id]->title);
    }

    /**
     * This tests the true/false question generator.
     */
    public function test_create_question_truefalse() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course));
        $opendsa_activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');

        $page1 = $opendsa_activitygenerator->create_question_truefalse($opendsa_activity);
        $page2 = $opendsa_activitygenerator->create_question_truefalse($opendsa_activity, array('title' => 'Custom title'));
        $records = $DB->get_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id), 'id');
        $p1answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page1->id), 'id');
        $p2answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page2->id), 'id');
        $this->assertCount(2, $records);
        $this->assertCount(2, $p1answers); // True/false only supports 2 answer records.
        $this->assertCount(2, $p2answers);
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals($page2->title, $records[$page2->id]->title);
    }

    /**
     * This tests the multichoice question generator.
     */
    public function test_create_question_multichoice() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course));
        $opendsa_activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');

        $page1 = $opendsa_activitygenerator->create_question_multichoice($opendsa_activity);
        $page2 = $opendsa_activitygenerator->create_question_multichoice($opendsa_activity, array('title' => 'Custom title'));
        $records = $DB->get_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id), 'id');
        $p1answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page1->id), 'id');
        $p2answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page2->id), 'id');
        $this->assertCount(2, $records);
        $this->assertCount(2, $p1answers); // Multichoice requires at least 2 records.
        $this->assertCount(2, $p2answers);
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals($page2->title, $records[$page2->id]->title);
    }

    /**
     * This tests the essay question generator.
     */
    public function test_create_question_essay() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course));
        $opendsa_activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');

        $page1 = $opendsa_activitygenerator->create_question_essay($opendsa_activity);
        $page2 = $opendsa_activitygenerator->create_question_essay($opendsa_activity, array('title' => 'Custom title'));
        $records = $DB->get_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id), 'id');
        $p1answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page1->id), 'id');
        $p2answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page2->id), 'id');
        $this->assertCount(2, $records);
        $this->assertCount(1, $p1answers); // Essay creates a single (empty) answer record.
        $this->assertCount(1, $p2answers);
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals($page2->title, $records[$page2->id]->title);
    }

    /**
     * This tests the matching question generator.
     */
    public function test_create_question_matching() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course));
        $opendsa_activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');

        $page1 = $opendsa_activitygenerator->create_question_matching($opendsa_activity);
        $page2 = $opendsa_activitygenerator->create_question_matching($opendsa_activity, array('title' => 'Custom title'));
        $records = $DB->get_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id), 'id');
        $p1answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page1->id), 'id');
        $p2answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page2->id), 'id');
        $this->assertCount(2, $records);
        $this->assertCount(4, $p1answers); // Matching creates two extra records plus 1 for each answer value.
        $this->assertCount(4, $p2answers);
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals($page2->title, $records[$page2->id]->title);
    }

    /**
     * This tests the numeric question generator.
     */
    public function test_create_question_numeric() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course));
        $opendsa_activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');

        $page1 = $opendsa_activitygenerator->create_question_numeric($opendsa_activity);
        $page2 = $opendsa_activitygenerator->create_question_numeric($opendsa_activity, array('title' => 'Custom title'));
        $records = $DB->get_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id), 'id');
        $p1answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page1->id), 'id');
        $p2answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page2->id), 'id');
        $this->assertCount(2, $records);
        $this->assertCount(1, $p1answers); // Numeric only requires 1 answer.
        $this->assertCount(1, $p2answers);
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals($page2->title, $records[$page2->id]->title);
    }

    /**
     * This tests the shortanswer question generator.
     */
    public function test_create_question_shortanswer() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $opendsa_activity = $this->getDataGenerator()->create_module('opendsa_activity', array('course' => $course));
        $opendsa_activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');

        $page1 = $opendsa_activitygenerator->create_question_shortanswer($opendsa_activity);
        $page2 = $opendsa_activitygenerator->create_question_shortanswer($opendsa_activity, array('title' => 'Custom title'));
        $records = $DB->get_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id), 'id');
        $p1answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page1->id), 'id');
        $p2answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page2->id), 'id');
        $this->assertCount(2, $records);
        $this->assertCount(1, $p1answers); // Shortanswer only requires 1 answer.
        $this->assertCount(1, $p2answers);
        $this->assertEquals($page1->id, $records[$page1->id]->id);
        $this->assertEquals($page2->id, $records[$page2->id]->id);
        $this->assertEquals($page2->title, $records[$page2->id]->title);
    }
}
