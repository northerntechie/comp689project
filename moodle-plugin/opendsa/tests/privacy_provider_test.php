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
 * Privacy provider tests.
 *
 * @package    mod_opendsa
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_privacy\local\metadata\collection;
use core_privacy\local\request\deletion_criteria;
use mod_opendsa\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @package    mod_opendsa
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_opendsa_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {
    /** @var stdClass The student object. */
    protected $student;

    /** @var stdClass The opendsa object. */
    protected $opendsa;

    /** @var stdClass The course object. */
    protected $course;

    /**
     * {@inheritdoc}
     */
    protected function setUp() {
        $this->resetAfterTest();

        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $options = ['fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza'];
        $params = [
            'course' => $course->id,
            'option' => $options,
            'name' => 'First OpenDSA Activity',
            'showpreview' => 0
        ];

        $plugingenerator = $generator->get_plugin_generator('mod_opendsa');
        // The opendsa activity the user will answer.
        $opendsa = $plugingenerator->create_instance($params);
        // Create another opendsa activity.
        $plugingenerator->create_instance($params);
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id);

        // Create a student which will make a opendsa.
        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($student->id,  $course->id, $studentrole->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[2], $opendsa, $student->id, $course, $cm);
        $this->student = $student;
        $this->opendsa = $opendsa;
        $this->course = $course;
    }

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata() {
        $collection = new collection('mod_opendsa');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(1, $itemcollection);

        $table = reset($itemcollection);
        $this->assertEquals('opendsa_answers', $table->get_name());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('opendsaid', $privacyfields);
        $this->assertArrayHasKey('optionid', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);

        $this->assertEquals('privacy:metadata:opendsa_answers', $table->get_summary());
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {
        $cm = get_coursemodule_from_instance('opendsa', $this->opendsa->id);

        $contextlist = provider::get_contexts_for_userid($this->student->id);
        $this->assertCount(1, $contextlist);
        $contextforuser = $contextlist->current();
        $cmcontext = context_module::instance($cm->id);
        $this->assertEquals($cmcontext->id, $contextforuser->id);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_for_context() {
        $cm = get_coursemodule_from_instance('opendsa', $this->opendsa->id);
        $cmcontext = context_module::instance($cm->id);

        // Export all of the data for the context.
        $this->export_context_data_for_user($this->student->id, $cmcontext, 'mod_opendsa');
        $writer = \core_privacy\local\request\writer::with_context($cmcontext);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $opendsa = $this->opendsa;
        $generator = $this->getDataGenerator();
        $cm = get_coursemodule_from_instance('opendsa', $this->opendsa->id);

        // Create another student who will answer the opendsa activity.
        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($student->id, $this->course->id, $studentrole->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[1], $opendsa, $student->id, $this->course, $cm);

        // Before deletion, we should have 2 responses.
        $count = $DB->count_records('opendsa_answers', ['opendsaid' => $opendsa->id]);
        $this->assertEquals(2, $count);

        // Delete data based on context.
        $cmcontext = context_module::instance($cm->id);
        provider::delete_data_for_all_users_in_context($cmcontext);

        // After deletion, the opendsa answers for that opendsa activity should have been deleted.
        $count = $DB->count_records('opendsa_answers', ['opendsaid' => $opendsa->id]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user_() {
        global $DB;

        $opendsa = $this->opendsa;
        $generator = $this->getDataGenerator();
        $cm1 = get_coursemodule_from_instance('opendsa', $this->opendsa->id);

        // Create a second opendsa activity.
        $options = ['Boracay', 'Camiguin', 'Bohol', 'Cebu', 'Coron'];
        $params = [
            'course' => $this->course->id,
            'option' => $options,
            'name' => 'Which do you think is the best island in the Philippines?',
            'showpreview' => 0
        ];
        $plugingenerator = $generator->get_plugin_generator('mod_opendsa');
        $opendsa2 = $plugingenerator->create_instance($params);
        $plugingenerator->create_instance($params);
        $cm2 = get_coursemodule_from_instance('opendsa', $opendsa2->id);

        // Make a selection for the first student for the 2nd opendsa activity.
        $opendsawithoptions = opendsa_get_opendsa($opendsa2->id);
        $optionids = array_keys($opendsawithoptions->option);
        opendsa_user_submit_response($optionids[2], $opendsa2, $this->student->id, $this->course, $cm2);

        // Create another student who will answer the first opendsa activity.
        $otherstudent = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($otherstudent->id, $this->course->id, $studentrole->id);

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[1], $opendsa, $otherstudent->id, $this->course, $cm1);

        // Before deletion, we should have 2 responses.
        $count = $DB->count_records('opendsa_answers', ['opendsaid' => $opendsa->id]);
        $this->assertEquals(2, $count);

        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);
        $contextlist = new \core_privacy\local\request\approved_contextlist($this->student, 'opendsa',
            [context_system::instance()->id, $context1->id, $context2->id]);
        provider::delete_data_for_user($contextlist);

        // After deletion, the opendsa answers for the first student should have been deleted.
        $count = $DB->count_records('opendsa_answers', ['opendsaid' => $opendsa->id, 'userid' => $this->student->id]);
        $this->assertEquals(0, $count);

        // Confirm that we only have one opendsa answer available.
        $opendsaanswers = $DB->get_records('opendsa_answers');
        $this->assertCount(1, $opendsaanswers);
        $lastresponse = reset($opendsaanswers);
        // And that it's the other student's response.
        $this->assertEquals($otherstudent->id, $lastresponse->userid);
    }

    /**
     * Test for provider::get_users_in_context().
     */
    public function test_get_users_in_context() {
        $cm = get_coursemodule_from_instance('opendsa', $this->opendsa->id);
        $cmcontext = context_module::instance($cm->id);

        $userlist = new \core_privacy\local\request\userlist($cmcontext, 'mod_opendsa');
        \mod_opendsa\privacy\provider::get_users_in_context($userlist);

        $this->assertEquals(
                [$this->student->id],
                $userlist->get_userids()
        );
    }

    /**
     * Test for provider::get_users_in_context() with invalid context type.
     */
    public function test_get_users_in_context_invalid_context_type() {
        $systemcontext = context_system::instance();

        $userlist = new \core_privacy\local\request\userlist($systemcontext, 'mod_opendsa');
        \mod_opendsa\privacy\provider::get_users_in_context($userlist);

        $this->assertCount(0, $userlist->get_userids());
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {
        global $DB;

        $opendsa = $this->opendsa;
        $generator = $this->getDataGenerator();
        $cm1 = get_coursemodule_from_instance('opendsa', $this->opendsa->id);

        // Create a second opendsa activity.
        $options = ['Boracay', 'Camiguin', 'Bohol', 'Cebu', 'Coron'];
        $params = [
            'course' => $this->course->id,
            'option' => $options,
            'name' => 'Which do you think is the best island in the Philippines?',
            'showpreview' => 0
        ];
        $plugingenerator = $generator->get_plugin_generator('mod_opendsa');
        $opendsa2 = $plugingenerator->create_instance($params);
        $plugingenerator->create_instance($params);
        $cm2 = get_coursemodule_from_instance('opendsa', $opendsa2->id);

        // Make a selection for the first student for the 2nd opendsa activity.
        $opendsawithoptions = opendsa_get_opendsa($opendsa2->id);
        $optionids = array_keys($opendsawithoptions->option);
        opendsa_user_submit_response($optionids[2], $opendsa2, $this->student->id, $this->course, $cm2);

        // Create 2 other students who will answer the first opendsa activity.
        $otherstudent = $generator->create_and_enrol($this->course, 'student');
        $anotherstudent = $generator->create_and_enrol($this->course, 'student');

        $opendsawithoptions = opendsa_get_opendsa($opendsa->id);
        $optionids = array_keys($opendsawithoptions->option);

        opendsa_user_submit_response($optionids[1], $opendsa, $otherstudent->id, $this->course, $cm1);
        opendsa_user_submit_response($optionids[1], $opendsa, $anotherstudent->id, $this->course, $cm1);

        // Before deletion, we should have 3 responses in the first opendsa activity.
        $count = $DB->count_records('opendsa_answers', ['opendsaid' => $opendsa->id]);
        $this->assertEquals(3, $count);

        $context1 = context_module::instance($cm1->id);
        $approveduserlist = new \core_privacy\local\request\approved_userlist($context1, 'opendsa',
                [$this->student->id, $otherstudent->id]);
        provider::delete_data_for_users($approveduserlist);

        // After deletion, the opendsa answers of the 2 students provided above should have been deleted
        // from the first opendsa activity. So there should only remain 1 answer which is for $anotherstudent.
        $opendsaanswers = $DB->get_records('opendsa_answers', ['opendsaid' => $opendsa->id]);
        $this->assertCount(1, $opendsaanswers);
        $lastresponse = reset($opendsaanswers);
        $this->assertEquals($anotherstudent->id, $lastresponse->userid);

        // Confirm that the answer that was submitted in the other opendsa activity is intact.
        $opendsaanswers = $DB->get_records_select('opendsa_answers', 'opendsaid <> ?', [$opendsa->id]);
        $this->assertCount(1, $opendsaanswers);
        $lastresponse = reset($opendsaanswers);
        // And that it's for the opendsa2 activity.
        $this->assertEquals($opendsa2->id, $lastresponse->opendsaid);
    }
}
