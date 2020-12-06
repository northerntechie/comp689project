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
 * Branch Table
 *
 * @package mod_opendsa_activity
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

 /** Branch Table page */
define("OPENDSA_ACTIVITY_PAGE_BRANCHTABLE",   "20");

class opendsa_activity_page_type_branchtable extends opendsa_activity_page {

    protected $type = opendsa_activity_page::TYPE_STRUCTURE;
    protected $typeid = OPENDSA_ACTIVITY_PAGE_BRANCHTABLE;
    protected $typeidstring = 'branchtable';
    protected $string = null;
    protected $jumpto = null;

    public function get_typeid() {
        return $this->typeid;
    }
    public function get_typestring() {
        if ($this->string===null) {
            $this->string = get_string($this->typeidstring, 'opendsa_activity');
        }
        return $this->string;
    }

    /**
     * Gets an array of the jumps used by the answers of this page
     *
     * @return array
     */
    public function get_jumps() {
        global $DB;
        $jumps = array();
        $params = array ("opendsa_activity_id" => $this->opendsa_activity->id, "pageid" => $this->properties->id);
        if ($answers = $this->get_answers()) {
            foreach ($answers as $answer) {
                if ($answer->answer === '') {
                    // show only jumps for real branches (==have description)
                    continue;
                }
                $jumps[] = $this->get_jump_name($answer->jumpto);
            }
        } else {
            // We get here is the opendsa_activity was created on a Moodle 1.9 site and
            // the opendsa_activity contains question pages without any answers.
            $jumps[] = $this->get_jump_name($this->properties->nextpageid);
        }
        return $jumps;
    }

    public static function get_jumptooptions($firstpage, opendsa_activity $opendsa_activity) {
        global $DB, $PAGE;
        $jump = array();
        $jump[0] = get_string("thispage", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_NEXTPAGE] = get_string("nextpage", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_PREVIOUSPAGE] = get_string("previouspage", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_EOL] = get_string("endofopendsa_activity", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_UNSEENBRANCHPAGE] = get_string("unseenpageinbranch", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_RANDOMPAGE] = get_string("randompageinbranch", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_RANDOMBRANCH] = get_string("randombranch", "opendsa_activity");

        if (!$firstpage) {
            if (!$apageid = $DB->get_field("opendsa_activity_pages", "id", array("opendsa_activity_id" => $opendsa_activity->id, "prevpageid" => 0))) {
                print_error('cannotfindfirstpage', 'opendsa_activity');
            }
            while (true) {
                if ($apageid) {
                    $title = $DB->get_field("opendsa_activity_pages", "title", array("id" => $apageid));
                    $jump[$apageid] = $title;
                    $apageid = $DB->get_field("opendsa_activity_pages", "nextpageid", array("id" => $apageid));
                } else {
                    // last page reached
                    break;
                }
            }
         }
        return $jump;
    }
    public function get_idstring() {
        return $this->typeidstring;
    }
    public function display($renderer, $attempt) {
        global $PAGE, $CFG;

        $output = '';
        $options = new stdClass;
        $options->para = false;
        $options->noclean = true;

        if ($this->opendsa_activity->slideshow) {
            $output .= $renderer->slideshow_start($this->opendsa_activity);
        }
        // We are using level 3 header because the page title is a sub-heading of opendsa_activity title (MDL-30911).
        $output .= $renderer->heading(format_string($this->properties->title), 3);
        $output .= $renderer->box($this->get_contents(), 'contents');

        $buttons = array();
        $i = 0;
        foreach ($this->get_answers() as $answer) {
            if ($answer->answer === '') {
                // not a branch!
                continue;
            }
            $params = array();
            $params['id'] = $PAGE->cm->id;
            $params['pageid'] = $this->properties->id;
            $params['sesskey'] = sesskey();
            $params['jumpto'] = $answer->jumpto;
            $url = new moodle_url('/mod/opendsa_activity/continue.php', $params);
            $buttons[] = $renderer->single_button($url, strip_tags(format_text($answer->answer, FORMAT_MOODLE, $options)));
            $i++;
        }
        // Set the orientation
        if ($this->properties->layout) {
            $buttonshtml = $renderer->box(implode("\n", $buttons), 'branchbuttoncontainer horizontal');
        } else {
            $buttonshtml = $renderer->box(implode("\n", $buttons), 'branchbuttoncontainer vertical');
        }
        $output .= $buttonshtml;

        if ($this->opendsa_activity->slideshow) {
            $output .= $renderer->slideshow_end();
        }

        // Trigger an event: content page viewed.
        $eventparams = array(
            'context' => context_module::instance($PAGE->cm->id),
            'objectid' => $this->properties->id
            );

        $event = \mod_opendsa_activity\event\content_page_viewed::create($eventparams);
        $event->trigger();

        return $output;
    }

    public function check_answer() {
        global $USER, $DB, $PAGE, $CFG;

        $result = parent::check_answer();

        require_sesskey();
        $newpageid = optional_param('jumpto', null, PARAM_INT);
        // going to insert into opendsa_activity_branch
        if ($newpageid == OPENDSA_ACTIVITY_RANDOMBRANCH) {
            $branchflag = 1;
        } else {
            $branchflag = 0;
        }
        if ($grades = $DB->get_records("opendsa_activity_grades", array("opendsa_activity_id" => $this->opendsa_activity->id, "userid" => $USER->id), "grade DESC")) {
            $retries = count($grades);
        } else {
            $retries = 0;
        }

        // First record this page in opendsa_activity_branch. This record may be needed by opendsa_activity_unseen_branch_jump.
        $branch = new stdClass;
        $branch->opendsa_activity_id = $this->opendsa_activity->id;
        $branch->userid = $USER->id;
        $branch->pageid = $this->properties->id;
        $branch->retry = $retries;
        $branch->flag = $branchflag;
        $branch->timeseen = time();
        $branch->nextpageid = 0;    // Next page id will be set later.
        $branch->id = $DB->insert_record("opendsa_activity_branch", $branch);

        //  this is called when jumping to random from a branch table
        $context = context_module::instance($PAGE->cm->id);
        if($newpageid == OPENDSA_ACTIVITY_UNSEENBRANCHPAGE) {
            if (has_capability('mod/opendsa_activity:manage', $context)) {
                 $newpageid = OPENDSA_ACTIVITY_NEXTPAGE;
            } else {
                 $newpageid = opendsa_activity_unseen_question_jump($this->opendsa_activity, $USER->id, $this->properties->id);  // this may return 0
            }
        }
        // convert jumpto page into a proper page id
        if ($newpageid == 0) {
            $newpageid = $this->properties->id;
        } elseif ($newpageid == OPENDSA_ACTIVITY_NEXTPAGE) {
            if (!$newpageid = $this->nextpageid) {
                // no nextpage go to end of opendsa_activity
                $newpageid = OPENDSA_ACTIVITY_EOL;
            }
        } elseif ($newpageid == OPENDSA_ACTIVITY_PREVIOUSPAGE) {
            $newpageid = $this->prevpageid;
        } elseif ($newpageid == OPENDSA_ACTIVITY_RANDOMPAGE) {
            $newpageid = opendsa_activity_random_question_jump($this->opendsa_activity, $this->properties->id);
        } elseif ($newpageid == OPENDSA_ACTIVITY_RANDOMBRANCH) {
            $newpageid = opendsa_activity_unseen_branch_jump($this->opendsa_activity, $USER->id);
        }

        // Update record to set nextpageid.
        $branch->nextpageid = $newpageid;
        $DB->update_record("opendsa_activity_branch", $branch);

        // This will force to redirect to the newpageid.
        $result->inmediatejump = true;
        $result->newpageid = $newpageid;
        return $result;
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass;
        $options->noclean = true;
        $options->para = false;
        $i = 1;
        foreach ($answers as $answer) {
            if ($answer->answer === '') {
                // not a branch!
                continue;
            }
            $cells = array();
            $cells[] = '<label>' . get_string('branch', 'opendsa_activity') . ' ' . $i . '</label>: ';
            $cells[] = format_text($answer->answer, $answer->answerformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = '<label>' . get_string('jump', 'opendsa_activity') . ' ' . $i . '</label>: ';
            $cells[] = $this->get_jump_name($answer->jumpto);
            $table->data[] = new html_table_row($cells);

            if ($i === 1){
                $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
            }
            $i++;
        }
        return $table;
    }
    public function get_grayout() {
        return 1;
    }
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  //I'll use it widely in this page
        $formattextdefoptions->context = $answerpage->context;

        foreach ($answers as $answer) {
            $data = "<input type=\"button\" class=\"btn btn-secondary\" name=\"$answer->id\" " .
                    "value=\"".s(strip_tags(format_text($answer->answer, FORMAT_MOODLE, $formattextdefoptions)))."\" " .
                    "disabled=\"disabled\"> ";
            $data .= get_string('jumpsto', 'opendsa_activity', $this->get_jump_name($answer->jumpto));
            $answerdata->answers[] = array($data, "");
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }

    public function update($properties, $context = null, $maxbytes = null) {
        if (empty($properties->display)) {
            $properties->display = '0';
        }
        if (empty($properties->layout)) {
            $properties->layout = '0';
        }
        return parent::update($properties);
    }
    public function add_page_link($previd) {
        global $PAGE, $CFG;
        $addurl = new moodle_url('/mod/opendsa_activity/editpage.php', array('id'=>$PAGE->cm->id, 'pageid'=>$previd, 'qtype'=>OPENDSA_ACTIVITY_PAGE_BRANCHTABLE));
        return array('addurl'=>$addurl, 'type'=>OPENDSA_ACTIVITY_PAGE_BRANCHTABLE, 'name'=>get_string('addabranchtable', 'opendsa_activity'));
    }
    protected function get_displayinmenublock() {
        return true;
    }
    public function is_unseen($param) {
        global $USER, $DB;
        if (is_array($param)) {
            $seenpages = $param;
            $branchpages = $this->opendsa_activity->get_sub_pages_of($this->properties->id, array(OPENDSA_ACTIVITY_PAGE_BRANCHTABLE, OPENDSA_ACTIVITY_PAGE_ENDOFBRANCH));
            foreach ($branchpages as $branchpage) {
                if (array_key_exists($branchpage->id, $seenpages)) {  // check if any of the pages have been viewed
                    return false;
                }
            }
            return true;
        } else {
            $nretakes = $param;
            if (!$DB->count_records("opendsa_activity_attempts", array("pageid"=>$this->properties->id, "userid"=>$USER->id, "retry"=>$nretakes))) {
                return true;
            }
            return false;
        }
    }
}

class opendsa_activity_add_page_form_branchtable extends opendsa_activity_add_page_form_base {

    public $qtype = OPENDSA_ACTIVITY_PAGE_BRANCHTABLE;
    public $qtypestring = 'branchtable';
    protected $standard = false;

    public function custom_definition() {
        global $PAGE, $CFG;

        $mform = $this->_form;
        $opendsa_activity = $this->_customdata['opendsa_activity'];

        $firstpage = optional_param('firstpage', false, PARAM_BOOL);

        $jumptooptions = opendsa_activity_page_type_branchtable::get_jumptooptions($firstpage, $opendsa_activity);

        if ($this->_customdata['edit']) {
            $mform->setDefault('qtypeheading', get_string('editbranchtable', 'opendsa_activity'));
        } else {
            $mform->setDefault('qtypeheading', get_string('addabranchtable', 'opendsa_activity'));
        }

        $mform->addElement('hidden', 'firstpage');
        $mform->setType('firstpage', PARAM_BOOL);
        $mform->setDefault('firstpage', $firstpage);

        $mform->addElement('hidden', 'qtype');
        $mform->setType('qtype', PARAM_INT);

        $mform->addElement('text', 'title', get_string("pagetitle", "opendsa_activity"), array('size'=>70));
        $mform->addRule('title', null, 'required', null, 'server');
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('title', PARAM_TEXT);
        } else {
            $mform->setType('title', PARAM_CLEANHTML);
        }

        $this->editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes);
        $mform->addElement('editor', 'contents_editor', get_string("pagecontents", "opendsa_activity"), null, $this->editoroptions);
        $mform->setType('contents_editor', PARAM_RAW);

        $mform->addElement('checkbox', 'layout', null, get_string("arrangebuttonshorizontally", "opendsa_activity"));
        $mform->setDefault('layout', true);

        $mform->addElement('checkbox', 'display', null, get_string("displayinleftmenu", "opendsa_activity"));
        $mform->setDefault('display', true);

        for ($i = 0; $i < $opendsa_activity->maxanswers; $i++) {
            $mform->addElement('header', 'headeranswer'.$i, get_string('branch', 'opendsa_activity').' '.($i+1));
            $this->add_answer($i, get_string("description", "opendsa_activity"), $i == 0);

            $mform->addElement('select', 'jumpto['.$i.']', get_string("jump", "opendsa_activity"), $jumptooptions);
            if ($i === 0) {
                $mform->setDefault('jumpto['.$i.']', 0);
            } else {
                $mform->setDefault('jumpto['.$i.']', OPENDSA_ACTIVITY_NEXTPAGE);
            }
        }
    }
}
