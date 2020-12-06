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
 * mod_opendsa_activity data generator.
 *
 * @package    mod_opendsa_activity
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_opendsa_activity data generator class.
 *
 * @package    mod_opendsa_activity
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_opendsa_activity_generator extends testing_module_generator {

    /**
     * @var int keep track of how many pages have been created.
     */
    protected $pagecount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->pagecount = 0;
        parent::reset();
    }

    public function create_instance($record = null, array $options = null) {
        global $CFG;

        // Add default values for opendsa_activity.
        $opendsa_activityconfig = get_config('mod_opendsa_activity');
        $record = (array)$record + array(
            'progressbar' => $opendsa_activityconfig->progressbar,
            'ongoing' => $opendsa_activityconfig->ongoing,
            'displayleft' => $opendsa_activityconfig->displayleftmenu,
            'displayleftif' => $opendsa_activityconfig->displayleftif,
            'slideshow' => $opendsa_activityconfig->slideshow,
            'maxanswers' => $opendsa_activityconfig->maxanswers,
            'feedback' => $opendsa_activityconfig->defaultfeedback,
            'activitylink' => 0,
            'available' => 0,
            'deadline' => 0,
            'usepassword' => 0,
            'password' => '',
            'dependency' => 0,
            'timespent' => 0,
            'completed' => 0,
            'gradebetterthan' => 0,
            'modattempts' => $opendsa_activityconfig->modattempts,
            'review' => $opendsa_activityconfig->displayreview,
            'maxattempts' => $opendsa_activityconfig->maximumnumberofattempts,
            'nextpagedefault' => $opendsa_activityconfig->defaultnextpage,
            'maxpages' => $opendsa_activityconfig->numberofpagestoshow,
            'practice' => $opendsa_activityconfig->practice,
            'custom' => $opendsa_activityconfig->customscoring,
            'retake' => $opendsa_activityconfig->retakesallowed,
            'usemaxgrade' => $opendsa_activityconfig->handlingofretakes,
            'minquestions' => $opendsa_activityconfig->minimumnumberofquestions,
            'grade' => 100,
        );
        if (!isset($record['mediafile'])) {
            require_once($CFG->libdir.'/filelib.php');
            $record['mediafile'] = file_get_unused_draft_itemid();
        }

        return parent::create_instance($record, (array)$options);
    }

    public function create_content($opendsa_activity, $record = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
        $now = time();
        $this->pagecount++;
        $record = (array)$record + array(
            'opendsa_activity_id' => $opendsa_activity->id,
            'title' => 'Lesson page '.$this->pagecount,
            'timecreated' => $now,
            'qtype' => 20, // OPENDSA_ACTIVITY_PAGE_BRANCHTABLE
            'pageid' => 0, // By default insert in the beginning.
        );
        if (!isset($record['contents_editor'])) {
            $record['contents_editor'] = array(
                'text' => 'Contents of opendsa_activity page '.$this->pagecount,
                'format' => FORMAT_MOODLE,
                'itemid' => 0,
            );
        }
        $context = context_module::instance($opendsa_activity->cmid);
        $page = opendsa_activity_page::create((object)$record, new opendsa_activity($opendsa_activity), $context, $CFG->maxbytes);
        return $DB->get_record('opendsa_activity_pages', array('id' => $page->id), '*', MUST_EXIST);
    }

    /**
     * Create True/false question pages.
     * @param object $opendsa_activity
     * @param array $record
     * @return int
     */
    public function create_question_truefalse($opendsa_activity, $record = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
        $now = time();
        $this->pagecount++;
        $record = (array)$record + array(
            'opendsa_activity_id' => $opendsa_activity->id,
            'title' => 'Lesson TF question '.$this->pagecount,
            'timecreated' => $now,
            'qtype' => 2,  // OPENDSA_ACTIVITY_PAGE_TRUEFALSE.
            'pageid' => 0, // By default insert in the beginning.
        );
        if (!isset($record['contents_editor'])) {
            $record['contents_editor'] = array(
                'text' => 'The answer is TRUE '.$this->pagecount,
                'format' => FORMAT_HTML,
                'itemid' => 0
            );
        }

        // First Answer (TRUE).
        if (!isset($record['answer_editor'][0])) {
            $record['answer_editor'][0] = array(
                'text' => 'TRUE answer for '.$this->pagecount,
                'format' => FORMAT_HTML
            );
        }
        if (!isset($record['jumpto'][0])) {
            $record['jumpto'][0] = OPENDSA_ACTIVITY_NEXTPAGE;
        }

        // Second Answer (FALSE).
        if (!isset($record['answer_editor'][1])) {
            $record['answer_editor'][1] = array(
                'text' => 'FALSE answer for '.$this->pagecount,
                'format' => FORMAT_HTML
            );
        }
        if (!isset($record['jumpto'][1])) {
            $record['jumpto'][1] = OPENDSA_ACTIVITY_THISPAGE;
        }

        $context = context_module::instance($opendsa_activity->cmid);
        $page = opendsa_activity_page::create((object)$record, new opendsa_activity($opendsa_activity), $context, $CFG->maxbytes);
        return $DB->get_record('opendsa_activity_pages', array('id' => $page->id), '*', MUST_EXIST);
    }

    /**
     * Create multichoice question pages.
     * @param object $opendsa_activity
     * @param array $record
     * @return int
     */
    public function create_question_multichoice($opendsa_activity, $record = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
        $now = time();
        $this->pagecount++;
        $record = (array)$record + array(
            'opendsa_activity_id' => $opendsa_activity->id,
            'title' => 'Lesson multichoice question '.$this->pagecount,
            'timecreated' => $now,
            'qtype' => 3,  // OPENDSA_ACTIVITY_PAGE_MULTICHOICE.
            'pageid' => 0, // By default insert in the beginning.
        );
        if (!isset($record['contents_editor'])) {
            $record['contents_editor'] = array(
                'text' => 'Pick the correct answer '.$this->pagecount,
                'format' => FORMAT_HTML,
                'itemid' => 0
            );
        }

        // First Answer (correct).
        if (!isset($record['answer_editor'][0])) {
            $record['answer_editor'][0] = array(
                'text' => 'correct answer for '.$this->pagecount,
                'format' => FORMAT_HTML
            );
        }
        if (!isset($record['jumpto'][0])) {
            $record['jumpto'][0] = OPENDSA_ACTIVITY_NEXTPAGE;
        }

        // Second Answer (incorrect).
        if (!isset($record['answer_editor'][1])) {
            $record['answer_editor'][1] = array(
                'text' => 'correct answer for '.$this->pagecount,
                'format' => FORMAT_HTML
            );
        }
        if (!isset($record['jumpto'][1])) {
            $record['jumpto'][1] = OPENDSA_ACTIVITY_THISPAGE;
        }

        $context = context_module::instance($opendsa_activity->cmid);
        $page = opendsa_activity_page::create((object)$record, new opendsa_activity($opendsa_activity), $context, $CFG->maxbytes);
        return $DB->get_record('opendsa_activity_pages', array('id' => $page->id), '*', MUST_EXIST);
    }

    /**
     * Create essay question pages.
     * @param object $opendsa_activity
     * @param array $record
     * @return int
     */
    public function create_question_essay($opendsa_activity, $record = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
        $now = time();
        $this->pagecount++;
        $record = (array)$record + array(
            'opendsa_activity_id' => $opendsa_activity->id,
            'title' => 'Lesson Essay question '.$this->pagecount,
            'timecreated' => $now,
            'qtype' => 10, // OPENDSA_ACTIVITY_PAGE_ESSAY.
            'pageid' => 0, // By default insert in the beginning.
        );
        if (!isset($record['contents_editor'])) {
            $record['contents_editor'] = array(
                'text' => 'Write an Essay '.$this->pagecount,
                'format' => FORMAT_HTML,
                'itemid' => 0
            );
        }

        // Essays have an answer of NULL.
        if (!isset($record['answer_editor'][0])) {
            $record['answer_editor'][0] = array(
                'text' => null,
                'format' => FORMAT_MOODLE
            );
        }
        if (!isset($record['jumpto'][0])) {
            $record['jumpto'][0] = OPENDSA_ACTIVITY_NEXTPAGE;
        }

        $context = context_module::instance($opendsa_activity->cmid);
        $page = opendsa_activity_page::create((object)$record, new opendsa_activity($opendsa_activity), $context, $CFG->maxbytes);
        return $DB->get_record('opendsa_activity_pages', array('id' => $page->id), '*', MUST_EXIST);
    }

    /**
     * Create matching question pages.
     * @param object $opendsa_activity
     * @param array $record
     * @return int
     */
    public function create_question_matching($opendsa_activity, $record = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
        $now = time();
        $this->pagecount++;
        $record = (array)$record + array(
            'opendsa_activity_id' => $opendsa_activity->id,
            'title' => 'Lesson Matching question '.$this->pagecount,
            'timecreated' => $now,
            'qtype' => 5,  // OPENDSA_ACTIVITY_PAGE_MATCHING.
            'pageid' => 0, // By default insert in the beginning.
        );
        if (!isset($record['contents_editor'])) {
            $record['contents_editor'] = array(
                'text' => 'Match the values '.$this->pagecount,
                'format' => FORMAT_HTML,
                'itemid' => 0
            );
        }
        // Feedback for correct result.
        if (!isset($record['answer_editor'][0])) {
            $record['answer_editor'][0] = array(
                'text' => '',
                'format' => FORMAT_HTML
            );
        }
        // Feedback for wrong result.
        if (!isset($record['answer_editor'][1])) {
            $record['answer_editor'][1] = array(
                'text' => '',
                'format' => FORMAT_HTML
            );
        }
        // First answer value.
        if (!isset($record['answer_editor'][2])) {
            $record['answer_editor'][2] = array(
                'text' => 'Match value 1',
                'format' => FORMAT_HTML
            );
        }
        // First response value.
        if (!isset($record['response_editor'][2])) {
            $record['response_editor'][2] = 'Match answer 1';
        }
        // Second Matching value.
        if (!isset($record['answer_editor'][3])) {
            $record['answer_editor'][3] = array(
                'text' => 'Match value 2',
                'format' => FORMAT_HTML
            );
        }
        // Second Matching answer.
        if (!isset($record['response_editor'][3])) {
            $record['response_editor'][3] = 'Match answer 2';
        }

        // Jump Values.
        if (!isset($record['jumpto'][0])) {
            $record['jumpto'][0] = OPENDSA_ACTIVITY_NEXTPAGE;
        }
        if (!isset($record['jumpto'][1])) {
            $record['jumpto'][1] = OPENDSA_ACTIVITY_THISPAGE;
        }

        // Mark the correct values.
        if (!isset($record['score'][0])) {
            $record['score'][0] = 1;
        }
        $context = context_module::instance($opendsa_activity->cmid);
        $page = opendsa_activity_page::create((object)$record, new opendsa_activity($opendsa_activity), $context, $CFG->maxbytes);
        return $DB->get_record('opendsa_activity_pages', array('id' => $page->id), '*', MUST_EXIST);
    }

    /**
     * Create shortanswer question pages.
     * @param object $opendsa_activity
     * @param array $record
     * @return int
     */
    public function create_question_shortanswer($opendsa_activity, $record = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
        $now = time();
        $this->pagecount++;
        $record = (array)$record + array(
            'opendsa_activity_id' => $opendsa_activity->id,
            'title' => 'Lesson Shortanswer question '.$this->pagecount,
            'timecreated' => $now,
            'qtype' => 1,  // OPENDSA_ACTIVITY_PAGE_SHORTANSWER.
            'pageid' => 0, // By default insert in the beginning.
        );
        if (!isset($record['contents_editor'])) {
            $record['contents_editor'] = array(
                'text' => 'Fill in the blank '.$this->pagecount,
                'format' => FORMAT_HTML,
                'itemid' => 0
            );
        }

        // First Answer (correct).
        if (!isset($record['answer_editor'][0])) {
            $record['answer_editor'][0] = array(
                'text' => 'answer'.$this->pagecount,
                'format' => FORMAT_MOODLE
            );
        }
        if (!isset($record['jumpto'][0])) {
            $record['jumpto'][0] = OPENDSA_ACTIVITY_NEXTPAGE;
        }

        $context = context_module::instance($opendsa_activity->cmid);
        $page = opendsa_activity_page::create((object)$record, new opendsa_activity($opendsa_activity), $context, $CFG->maxbytes);
        return $DB->get_record('opendsa_activity_pages', array('id' => $page->id), '*', MUST_EXIST);
    }

    /**
     * Create shortanswer question pages.
     * @param object $opendsa_activity
     * @param array $record
     * @return int
     */
    public function create_question_numeric($opendsa_activity, $record = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
        $now = time();
        $this->pagecount++;
        $record = (array)$record + array(
            'opendsa_activity_id' => $opendsa_activity->id,
            'title' => 'Lesson numerical question '.$this->pagecount,
            'timecreated' => $now,
            'qtype' => 8,  // OPENDSA_ACTIVITY_PAGE_NUMERICAL.
            'pageid' => 0, // By default insert in the beginning.
        );
        if (!isset($record['contents_editor'])) {
            $record['contents_editor'] = array(
                'text' => 'Numerical question '.$this->pagecount,
                'format' => FORMAT_HTML,
                'itemid' => 0
            );
        }

        // First Answer (correct).
        if (!isset($record['answer_editor'][0])) {
            $record['answer_editor'][0] = array(
                'text' => $this->pagecount,
                'format' => FORMAT_MOODLE
            );
        }
        if (!isset($record['jumpto'][0])) {
            $record['jumpto'][0] = OPENDSA_ACTIVITY_NEXTPAGE;
        }

        $context = context_module::instance($opendsa_activity->cmid);
        $page = opendsa_activity_page::create((object)$record, new opendsa_activity($opendsa_activity), $context, $CFG->maxbytes);
        return $DB->get_record('opendsa_activity_pages', array('id' => $page->id), '*', MUST_EXIST);
    }
}
