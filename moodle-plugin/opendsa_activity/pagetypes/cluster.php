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
 * Cluster
 *
 * @package mod_opendsa_activity
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

 /** Start of Cluster page */
define("OPENDSA_ACTIVITY_PAGE_CLUSTER",   "30");

class opendsa_activity_page_type_cluster extends opendsa_activity_page {

    protected $type = opendsa_activity_page::TYPE_STRUCTURE;
    protected $typeidstring = 'cluster';
    protected $typeid = OPENDSA_ACTIVITY_PAGE_CLUSTER;
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
    public function get_grayout() {
        return 1;
    }
    public function callback_on_view($canmanage, $redirect = true) {
        global $USER;
        if (!$canmanage) {
            // Get the next page in the opendsa_activity cluster jump
            return (int) $this->opendsa_activity->cluster_jump($this->properties->id);
        } else {
            // get the next page
            return (int) $this->properties->nextpageid;
        }
    }
    public function override_next_page() {
        global $USER;
        return $this->opendsa_activity->cluster_jump($this->properties->id);
    }
    public function add_page_link($previd) {
        global $PAGE, $CFG;
        $addurl = new moodle_url('/mod/opendsa_activity/editpage.php', array('id'=>$PAGE->cm->id, 'pageid'=>$previd, 'sesskey'=>sesskey(), 'qtype'=>OPENDSA_ACTIVITY_PAGE_CLUSTER));
        return array('addurl'=>$addurl, 'type'=>OPENDSA_ACTIVITY_PAGE_CLUSTER, 'name'=>get_string('addcluster', 'opendsa_activity'));
    }
    public function valid_page_and_view(&$validpages, &$pageviews) {
        $validpages[$this->properties->id] = 1;  // add the cluster page as a valid page
        foreach ($this->opendsa_activity->get_sub_pages_of($this->properties->id, array(OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER)) as $subpage) {
            if (in_array($subpage->id, $pageviews)) {
                unset($pageviews[array_search($subpage->id, $pageviews)]);  // remove it
                // since the user did see one page in the cluster, add the cluster pageid to the viewedpageids
                if (!in_array($this->properties->id, $pageviews)) {
                    $pageviews[] = $this->properties->id;
                }
            }
        }
        return $this->properties->nextpageid;
    }
}

class opendsa_activity_add_page_form_cluster extends opendsa_activity_add_page_form_base {

    public $qtype = OPENDSA_ACTIVITY_PAGE_CLUSTER;
    public $qtypestring = 'cluster';
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
        global $PAGE, $CFG, $DB;
        require_sesskey();

        $timenow = time();

        if ($pageid == 0) {
            if ($opendsa_activity->has_pages()) {
                if (!$page = $DB->get_record("opendsa_activity_pages", array("prevpageid" => 0, "opendsa_activity_id" => $opendsa_activity->id))) {
                    print_error('cannotfindpagerecord', 'opendsa_activity');
                }
            } else {
                // This is the ONLY page
                $page = new stdClass;
                $page->id = 0;
            }
        } else {
            if (!$page = $DB->get_record("opendsa_activity_pages", array("id" => $pageid))) {
                print_error('cannotfindpagerecord', 'opendsa_activity');
            }
        }
        $newpage = new stdClass;
        $newpage->opendsa_activity_id = $opendsa_activity->id;
        $newpage->prevpageid = $pageid;
        if ($pageid != 0) {
            $newpage->nextpageid = $page->nextpageid;
        } else {
            $newpage->nextpageid = $page->id;
        }
        $newpage->qtype = $this->qtype;
        $newpage->timecreated = $timenow;
        $newpage->title = get_string("clustertitle", "opendsa_activity");
        $newpage->contents = get_string("clustertitle", "opendsa_activity");
        $newpageid = $DB->insert_record("opendsa_activity_pages", $newpage);
        // update the linked list...
        if ($pageid != 0) {
            $DB->set_field("opendsa_activity_pages", "nextpageid", $newpageid, array("id" => $pageid));
        }

        if ($pageid == 0) {
            $page->nextpageid = $page->id;
        }
        if ($page->nextpageid) {
            // the new page is not the last page
            $DB->set_field("opendsa_activity_pages", "prevpageid", $newpageid, array("id" => $page->nextpageid));
        }
        // ..and the single "answer"
        $newanswer = new stdClass;
        $newanswer->opendsa_activity_id = $opendsa_activity->id;
        $newanswer->pageid = $newpageid;
        $newanswer->timecreated = $timenow;
        $newanswer->jumpto = OPENDSA_ACTIVITY_CLUSTERJUMP;
        $newanswerid = $DB->insert_record("opendsa_activity_answers", $newanswer);
        $opendsa_activity->add_message(get_string('addedcluster', 'opendsa_activity'), 'notifysuccess');
        redirect($CFG->wwwroot.'/mod/opendsa_activity/edit.php?id='.$PAGE->cm->id);
    }
}