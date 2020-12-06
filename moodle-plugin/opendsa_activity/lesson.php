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
 * Handles opendsa_activity actions
 *
 * ACTIONS handled are:
 *    confirmdelete
 *    delete
 *    move
 *    moveit
 *    duplicate
 * @package mod_opendsa_activity
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');

$id     = required_param('id', PARAM_INT);         // Course Module ID
$action = required_param('action', PARAM_ALPHA);   // Action
$pageid = required_param('pageid', PARAM_INT);

$cm = get_coursemodule_from_id('opendsa_activity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$opendsa_activity = new opendsa_activity($DB->get_record('opendsa_activity', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

$url = new moodle_url('/mod/opendsa_activity/opendsa_activity.php', array('id'=>$id,'action'=>$action));
$PAGE->set_url($url);

$context = context_module::instance($cm->id);
require_capability('mod/opendsa_activity:edit', $context);
require_sesskey();

$opendsa_activityoutput = $PAGE->get_renderer('mod_opendsa_activity');

/// Process the action
switch ($action) {
    case 'confirmdelete':
        $PAGE->navbar->add(get_string($action, 'opendsa_activity'));

        $thispage = $opendsa_activity->load_page($pageid);

        echo $opendsa_activity_output->header($opendsa_activity, $cm, '', false, null, get_string('deletingpage', 'opendsa_activity', format_string($thispage->title)));
        echo $OUTPUT->heading(get_string("deletingpage", "opendsa_activity", format_string($thispage->title)));
        // print the jumps to this page
        $params = array("opendsa_activity_id" => $opendsa_activity->id, "pageid" => $pageid);
        if ($answers = $DB->get_records_select("opendsa_activity_answers", "opendsa_activity_id = :opendsa_activity_id AND jumpto = :pageid + 1", $params)) {
            echo $OUTPUT->heading(get_string("thefollowingpagesjumptothispage", "opendsa_activity"));
            echo "<p align=\"center\">\n";
            foreach ($answers as $answer) {
                if (!$title = $DB->get_field("opendsa_activity_pages", "title", array("id" => $answer->pageid))) {
                    print_error('cannotfindpagetitle', 'opendsa_activity');
                }
                echo $title."<br />\n";
            }
        }
        echo $OUTPUT->confirm(get_string("confirmdeletionofthispage","opendsa_activity"),"opendsa_activity.php?action=delete&id=$cm->id&pageid=$pageid","view.php?id=$cm->id");

        break;
    case 'move':
        $PAGE->navbar->add(get_string($action, 'opendsa_activity'));

        $title = $DB->get_field("opendsa_activity_pages", "title", array("id" => $pageid));

        echo $opendsa_activity_output->header($opendsa_activity, $cm, '', false, null, get_string('moving', 'opendsa_activity', format_String($title)));
        echo $OUTPUT->heading(get_string("moving", "opendsa_activity", format_string($title)), 3);

        $params = array ("opendsa_activity_id" => $opendsa_activity->id, "prevpageid" => 0);
        if (!$page = $DB->get_record_select("opendsa_activity_pages", "opendsa_activity_id = :opendsa_activity_id AND prevpageid = :prevpageid", $params)) {
            print_error('cannotfindfirstpage', 'opendsa_activity');
        }

        echo html_writer::start_tag('div', array('class' => 'move-page'));

        echo html_writer::start_tag('div', array('class' => 'available-position'));
        $moveurl = "opendsa_activity.php?id=$cm->id&sesskey=".sesskey()."&action=moveit&pageid=$pageid&after=0";
        echo html_writer::link($moveurl, get_string("movepagehere", "opendsa_activity"));
        echo html_writer::end_tag('div');

        while (true) {
            if ($page->id != $pageid) {
                if (!$title = trim(format_string($page->title))) {
                    $title = "<< ".get_string("notitle", "opendsa_activity")."  >>";
                }
                echo html_writer::tag('div', $title, array('class' => 'page'));

                echo html_writer::start_tag('div', array('class' => 'available-position'));
                $moveurl = "opendsa_activity.php?id=$cm->id&sesskey=".sesskey()."&action=moveit&pageid=$pageid&after={$page->id}";
                echo html_writer::link($moveurl, get_string("movepagehere", "opendsa_activity"));
                echo html_writer::end_tag('div');
            }
            if ($page->nextpageid) {
                if (!$page = $DB->get_record("opendsa_activity_pages", array("id" => $page->nextpageid))) {
                    print_error('cannotfindnextpage', 'opendsa_activity');
                }
            } else {
                // last page reached
                break;
            }
        }
        echo html_writer::end_tag('div');

        break;
    case 'delete':
        $thispage = $opendsa_activity->load_page($pageid);
        $thispage->delete();
        redirect("$CFG->wwwroot/mod/opendsa_activity/edit.php?id=$cm->id");
        break;
    case 'moveit':
        $after = (int)required_param('after', PARAM_INT); // target page

        $opendsa_activity->resort_pages($pageid, $after);
        redirect("$CFG->wwwroot/mod/opendsa_activity/edit.php?id=$cm->id");
        break;
    case 'duplicate':
            $opendsa_activity->duplicate_page($pageid);
            redirect(new moodle_url('/mod/opendsa_activity/edit.php', array('id' => $cm->id)));
        break;
    default:
        print_error('unknowaction');
        break;
}

echo $opendsa_activityoutput->footer();
