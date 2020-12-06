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
 * End of cluster
 *
 * @package mod_opendsa_activity
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

 /** End of Cluster page */
define("OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER",   "31");

class opendsa_activity_page_type_endofcluster extends opendsa_activity_page {

    protected $type = opendsa_activity_page::TYPE_STRUCTURE;
    protected $typeidstring = 'endofcluster';
    protected $typeid = OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER;
    protected $string = null;
    protected $jumpto = null;

    public function display($renderer, $attempt) {
        return '';
    }
    public function get_typeid() {
        return $this->typeid;
    }
    public function get_typestring() {
        if ($this->string===null) {
            $this->string = get_string($this->typeidstring, 'opendsa_activity');
        }
        return $this->string;
    }
    public function get_idstring() {
        return $this->typeidstring;
    }
    public function callback_on_view($canmanage, $redirect = true) {
        return (int) $this->redirect_to_next_page($canmanage, $redirect);
    }
    public function redirect_to_next_page($canmanage, $redirect) {
        global $PAGE;
        if ($this->properties->nextpageid == 0) {
            $nextpageid = OPENDSA_ACTIVITY_EOL;
        } else {
            $nextpageid = $this->properties->nextpageid;
        }
        if ($redirect) {
            redirect(new moodle_url('/mod/opendsa_activity/view.php', array('id' => $PAGE->cm->id, 'pageid' => $nextpageid)));
            die;
        }
        return $nextpageid;
    }
    public function get_grayout() {
        return 1;
    }

    public function override_next_page() {
        global $DB;
        $jump = $DB->get_field("opendsa_activity_answers", "jumpto", array("pageid" => $this->properties->id, "opendsa_activity_id" => $this->opendsa_activity->id));
        if ($jump == OPENDSA_ACTIVITY_NEXTPAGE) {
            if ($this->properties->nextpageid == 0) {
                return OPENDSA_ACTIVITY_EOL;
            } else {
                return $this->properties->nextpageid;
            }
        } else {
            return $jump;
        }
    }
    public function add_page_link($previd) {
        global $PAGE, $CFG;
        if ($previd != 0) {
            $addurl = new moodle_url('/mod/opendsa_activity/editpage.php', array('id'=>$PAGE->cm->id, 'pageid'=>$previd, 'sesskey'=>sesskey(), 'qtype'=>OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER));
            return array('addurl'=>$addurl, 'type'=>OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER, 'name'=>get_string('addendofcluster', 'opendsa_activity'));
        }
        return false;
    }
    public function valid_page_and_view(&$validpages, &$pageviews) {
        return $this->properties->nextpageid;
    }
}

class opendsa_activity_add_page_form_endofcluster extends opendsa_activity_add_page_form_base {

    public $qtype = OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER;
    public $qtypestring = 'endofcluster';
    protected $standard = false;

    public function custom_definition() {
        global $PAGE, $CFG;

        $mform = $this->_form;
        $opendsa_activity = $this->_customdata['opendsa_activity'];
        $jumptooptions = opendsa_activity_page_type_branchtable::get_jumptooptions(optional_param('firstpage', false, PARAM_BOOL), $opendsa_activity);

        $mform->addElement('hidden', 'firstpage');
        $mform->setType('firstpage', PARAM_BOOL);

        $mform->addElement('hidden', 'qtype');
        $mform->setType('qtype', PARAM_TEXT);

        $mform->addElement('text', 'title', get_string("pagetitle", "opendsa_activity"), array('size'=>70));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('title', PARAM_TEXT);
        } else {
            $mform->setType('title', PARAM_CLEANHTML);
        }

        $this->editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes);
        $mform->addElement('editor', 'contents_editor', get_string("pagecontents", "opendsa_activity"), null, $this->editoroptions);
        $mform->setType('contents_editor', PARAM_RAW);

        $this->add_jumpto(0);
    }

    public function construction_override($pageid, opendsa_activity $opendsa_activity) {
        global $CFG, $PAGE, $DB;
        require_sesskey();

        $timenow = time();

        // the new page is not the first page (end of cluster always comes after an existing page)
        if (!$page = $DB->get_record("opendsa_activity_pages", array("id" => $pageid))) {
            print_error('cannotfindpages', 'opendsa_activity');
        }

        // could put code in here to check if the user really can insert an end of cluster

        $newpage = new stdClass;
        $newpage->opendsa_activity_id = $opendsa_activity->id;
        $newpage->prevpageid = $pageid;
        $newpage->nextpageid = $page->nextpageid;
        $newpage->qtype = $this->qtype;
        $newpage->timecreated = $timenow;
        $newpage->title = get_string("endofclustertitle", "opendsa_activity");
        $newpage->contents = get_string("endofclustertitle", "opendsa_activity");
        $newpageid = $DB->insert_record("opendsa_activity_pages", $newpage);
        // update the linked list...
        $DB->set_field("opendsa_activity_pages", "nextpageid", $newpageid, array("id" => $pageid));
        if ($page->nextpageid) {
            // the new page is not the last page
            $DB->set_field("opendsa_activity_pages", "prevpageid", $newpageid, array("id" => $page->nextpageid));
        }
        // ..and the single "answer"
        $newanswer = new stdClass;
        $newanswer->opendsa_activity_id = $opendsa_activity->id;
        $newanswer->pageid = $newpageid;
        $newanswer->timecreated = $timenow;
        $newanswer->jumpto = OPENDSA_ACTIVITY_NEXTPAGE;
        $newanswerid = $DB->insert_record("opendsa_activity_answers", $newanswer);
        $opendsa_activity->add_message(get_string('addedendofcluster', 'opendsa_activity'), 'notifysuccess');
        redirect($CFG->wwwroot.'/mod/opendsa_activity/edit.php?id='.$PAGE->cm->id);
    }
}
