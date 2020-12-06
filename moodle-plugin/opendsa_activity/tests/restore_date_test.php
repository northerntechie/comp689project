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
 * Restore date tests.
 *
 * @package    mod_opendsa_activity
 * @copyright  2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore date tests.
 *
 * @package    mod_opendsa_activity
 * @copyright  2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_opendsa_activity_restore_date_testcase extends restore_date_testcase {

    /**
     * Creates an attempt for the given userwith a correct or incorrect answer and optionally finishes it.
     *
     * TODO This api can be better extracted to a generator.
     *
     * @param  stdClass $opendsa_activity  Lesson object.
     * @param  stdClass $page    page object.
     * @param  boolean $correct  If the answer should be correct.
     * @param  boolean $finished If we should finish the attempt.
     *
     * @return array the result of the attempt creation or finalisation.
     */
    protected function create_attempt($opendsa_activity, $page, $correct = true, $finished = false) {
        global $DB, $USER;

        // First we need to launch the opendsa_activity so the timer is on.
        mod_opendsa_activity_external::launch_attempt($opendsa_activity->id);

        $DB->set_field('opendsa_activity', 'feedback', 1, array('id' => $opendsa_activity->id));
        $DB->set_field('opendsa_activity', 'progressbar', 1, array('id' => $opendsa_activity->id));
        $DB->set_field('opendsa_activity', 'custom', 0, array('id' => $opendsa_activity->id));
        $DB->set_field('opendsa_activity', 'maxattempts', 3, array('id' => $opendsa_activity->id));

        $answercorrect = 0;
        $answerincorrect = 0;
        $p2answers = $DB->get_records('opendsa_activity_answers', array('opendsa_activity_id' => $opendsa_activity->id, 'pageid' => $page->id), 'id');
        foreach ($p2answers as $answer) {
            if ($answer->jumpto == 0) {
                $answerincorrect = $answer->id;
            } else {
                $answercorrect = $answer->id;
            }
        }

        $data = array(
            array(
                'name' => 'answerid',
                'value' => $correct ? $answercorrect : $answerincorrect,
            ),
            array(
                'name' => '_qf__opendsa_activity_display_answer_form_truefalse',
                'value' => 1,
            )
        );
        $result = mod_opendsa_activity_external::process_page($opendsa_activity->id, $page->id, $data);
        $result = external_api::clean_returnvalue(mod_opendsa_activity_external::process_page_returns(), $result);

        // Create attempt.
        $newpageattempt = [
            'opendsa_activity_id' => $opendsa_activity->id,
            'pageid' => $page->id,
            'userid' => $USER->id,
            'answerid' => $answercorrect,
            'retry' => 1,   // First attempt is always 0.
            'correct' => 1,
            'useranswer' => '1',
            'timeseen' => time(),
        ];
        $DB->insert_record('opendsa_activity_attempts', (object) $newpageattempt);

        if ($finished) {
            $result = mod_opendsa_activity_external::finish_attempt($opendsa_activity->id);
            $result = external_api::clean_returnvalue(mod_opendsa_activity_external::finish_attempt_returns(), $result);
        }
        return $result;
    }

    /**
     * Test restore dates.
     */
    public function test_restore_dates() {
        global $DB, $USER;

        // Create opendsa_activity data.
        $record = ['available' => 100, 'deadline' => 100, 'timemodified' => 100];
        list($course, $opendsa_activity) = $this->create_course_and_module('opendsa_activity', $record);
        $opendsa_activitygenerator = $this->getDataGenerator()->get_plugin_generator('mod_opendsa_activity');
        $page = $opendsa_activitygenerator->create_content($opendsa_activity);
        $page2 = $opendsa_activitygenerator->create_question_truefalse($opendsa_activity);
        $this->create_attempt($opendsa_activity, $page2, true, true);

        $timer = $DB->get_record('opendsa_activity_timer', ['opendsa_activity_id' => $opendsa_activity->id]);
        // Lesson grade.
        $timestamp = 100;
        $grade = new stdClass();
        $grade->opendsa_activity_id = $opendsa_activity->id;
        $grade->userid = $USER->id;
        $grade->grade = 8.9;
        $grade->completed = $timestamp;
        $grade->id = $DB->insert_record('opendsa_activity_grades', $grade);

        // User override.
        $override = (object)[
            'opendsa_activity_id' => $opendsa_activity->id,
            'groupid' => 0,
            'userid' => $USER->id,
            'sortorder' => 1,
            'available' => 100,
            'deadline' => 200
        ];
        $DB->insert_record('opendsa_activity_overrides', $override);

        // Set time fields to a constant for easy validation.
        $DB->set_field('opendsa_activity_pages', 'timecreated', $timestamp);
        $DB->set_field('opendsa_activity_pages', 'timemodified', $timestamp);
        $DB->set_field('opendsa_activity_answers', 'timecreated', $timestamp);
        $DB->set_field('opendsa_activity_answers', 'timemodified', $timestamp);
        $DB->set_field('opendsa_activity_attempts', 'timeseen', $timestamp);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $newopendsa_activity = $DB->get_record('opendsa_activity', ['course' => $newcourseid]);

        $this->assertFieldsNotRolledForward($opendsa_activity, $newopendsa_activity, ['timemodified']);
        $props = ['available', 'deadline'];
        $this->assertFieldsRolledForward($opendsa_activity, $newopendsa_activity, $props);

        $newpages = $DB->get_records('opendsa_activity_pages', ['opendsa_activity_id' => $newopendsa_activity->id]);
        $newanswers = $DB->get_records('opendsa_activity_answers', ['opendsa_activity_id' => $newopendsa_activity->id]);
        $newgrade = $DB->get_record('opendsa_activity_grades', ['opendsa_activity_id' => $newopendsa_activity->id]);
        $newoverride = $DB->get_record('opendsa_activity_overrides', ['opendsa_activity_id' => $newopendsa_activity->id]);
        $newtimer = $DB->get_record('opendsa_activity_timer', ['opendsa_activity_id' => $newopendsa_activity->id]);
        $newattempt = $DB->get_record('opendsa_activity_attempts', ['opendsa_activity_id' => $newopendsa_activity->id]);

        // Page time checks.
        foreach ($newpages as $newpage) {
            $this->assertEquals($timestamp, $newpage->timemodified);
            $this->assertEquals($timestamp, $newpage->timecreated);
        }

        // Page answers time checks.
        foreach ($newanswers as $newanswer) {
            $this->assertEquals($timestamp, $newanswer->timemodified);
            $this->assertEquals($timestamp, $newanswer->timecreated);
        }

        // Lesson override time checks.
        $diff = $this->get_diff();
        $this->assertEquals($override->available + $diff, $newoverride->available);
        $this->assertEquals($override->deadline + $diff, $newoverride->deadline);

        // Lesson grade time checks.
        $this->assertEquals($timestamp, $newgrade->completed);

        // Lesson timer time checks.
        $this->assertEquals($timer->starttime, $newtimer->starttime);
        $this->assertEquals($timer->opendsa_activitytime, $newtimer->opendsa_activitytime);

        // Lesson attempt time check.
        $this->assertEquals($timestamp, $newattempt->timeseen);
    }
}
