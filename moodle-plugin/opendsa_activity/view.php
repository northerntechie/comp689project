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
 * This page prints a particular instance of opendsa_activity
 *
 * @package mod_opendsa_activity
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
require_once($CFG->libdir . '/grade/constants.php');

$id      = required_param('id', PARAM_INT);             // Course Module ID
$pageid  = optional_param('pageid', null, PARAM_INT);   // Lesson Page ID
$edit    = optional_param('edit', -1, PARAM_BOOL);
$userpassword = optional_param('userpassword','',PARAM_RAW);
$backtocourse = optional_param('backtocourse', false, PARAM_RAW);

$cm = get_coursemodule_from_id('opendsa_activity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$opendsa_activity = new opendsa_activity($DB->get_record('opendsa_activity', array('id' => $cm->instance), '*', MUST_EXIST), $cm, $course);

require_login($course, false, $cm);

if ($backtocourse) {
    redirect(new moodle_url('/course/view.php', array('id'=>$course->id)));
}

// Apply overrides.
$opendsa_activity->update_effective_access($USER->id);

$url = new moodle_url('/mod/opendsa_activity/view.php', array('id'=>$id));
if ($pageid !== null) {
    $url->param('pageid', $pageid);
}
$PAGE->set_url($url);
$PAGE->force_settings_menu();

$context = $opendsa_activity->context;
$canmanage = $opendsa_activity->can_manage();

$opendsa_activityoutput = $PAGE->get_renderer('mod_opendsa_activity');

$reviewmode = $opendsa_activity->is_in_review_mode();

if ($opendsa_activity->usepassword && !empty($userpassword)) {
    require_sesskey();
}

// Check these for students only TODO: Find a better method for doing this!
if ($timerestriction = $opendsa_activity->get_time_restriction_status()) {  // Deadline restrictions.
    echo $opendsa_activityoutput->header($opendsa_activity, $cm, '', false, null, get_string('notavailable'));
    echo $opendsa_activityoutput->opendsa_activity_inaccessible(get_string($timerestriction->reason, 'opendsa_activity', userdate($timerestriction->time)));
    echo $opendsa_activityoutput->footer();
    exit();
} else if ($passwordrestriction = $opendsa_activity->get_password_restriction_status($userpassword)) { // Password protected opendsa_activity code.
    echo $opendsa_activityoutput->header($opendsa_activity, $cm, '', false, null, get_string('passwordprotectedopendsa_activity', 'opendsa_activity', format_string($opendsa_activity->name)));
    echo $opendsa_activityoutput->login_prompt($opendsa_activity, $userpassword !== '');
    echo $opendsa_activityoutput->footer();
    exit();
} else if ($dependenciesrestriction = $opendsa_activity->get_dependencies_restriction_status()) { // Check for dependencies.
    echo $opendsa_activityoutput->header($opendsa_activity, $cm, '', false, null, get_string('completethefollowingconditions', 'opendsa_activity', format_string($opendsa_activity->name)));
    echo $opendsa_activityoutput->dependancy_errors($dependenciesrestriction->dependentopendsa_activity, $dependenciesrestriction->errors);
    echo $opendsa_activityoutput->footer();
    exit();
}

// This is called if a student leaves during a opendsa_activity.
if ($pageid == OPENDSA_ACTIVITY_UNSEENBRANCHPAGE) {
    $pageid = opendsa_activity_unseen_question_jump($opendsa_activity, $USER->id, $pageid);
}

// To avoid multiple calls, store the magic property firstpage.
$opendsa_activityfirstpage = $opendsa_activity->firstpage;
$opendsa_activityfirstpageid = $opendsa_activityfirstpage ? $opendsa_activityfirstpage->id : false;

// display individual pages and their sets of answers
// if pageid is EOL then the end of the opendsa_activity has been reached
// for flow, changed to simple echo for flow styles, michaelp, moved opendsa_activity name and page title down
$attemptflag = false;
if (empty($pageid)) {
    // make sure there are pages to view
    if (!$opendsa_activityfirstpageid) {
        if (!$canmanage) {
            $opendsa_activity->add_message(get_string('opendsa_activitynotready2', 'opendsa_activity')); // a nice message to the student
        } else {
            if (!$DB->count_records('opendsa_activity_pages', array('opendsa_activity_id'=>$opendsa_activity->id))) {
                redirect("$CFG->wwwroot/mod/opendsa_activity/edit.php?id=$cm->id"); // no pages - redirect to add pages
            } else {
                $opendsa_activity->add_message(get_string('opendsa_activitypagelinkingbroken', 'opendsa_activity'));  // ok, bad mojo
            }
        }
    }

    // if no pageid given see if the opendsa_activity has been started
    $retries = $opendsa_activity->count_user_retries($USER->id);
    if ($retries > 0) {
        $attemptflag = true;
    }

    if (isset($USER->modattempts[$opendsa_activity->id])) {
        unset($USER->modattempts[$opendsa_activity->id]);  // if no pageid, then student is NOT reviewing
    }

    $lastpageseen = $opendsa_activity->get_last_page_seen($retries);

    // Check if the opendsa_activity was attempted in an external device like the mobile app.
    // This check makes sense only when the opendsa_activity allows offline attempts.
    if ($opendsa_activity->allowofflineattempts && $timers = $opendsa_activity->get_user_timers($USER->id, 'starttime DESC', '*', 0, 1)) {
        $timer = current($timers);
        if (!empty($timer->timemodifiedoffline)) {
            $lasttime = format_time(time() - $timer->timemodifiedoffline);
            $opendsa_activity->add_message(get_string('offlinedatamessage', 'opendsa_activity', $lasttime), 'warning');
        }
    }

    // Check to see if end of opendsa_activity was reached.
    if (($lastpageseen !== false && ($lastpageseen != OPENDSA_ACTIVITY_EOL))) {
        // End not reached. Check if the user left.
        if ($opendsa_activity->left_during_timed_session($retries)) {

            echo $opendsa_activityoutput->header($opendsa_activity, $cm, '', false, null, get_string('leftduringtimedsession', 'opendsa_activity'));
            if ($opendsa_activity->timelimit) {
                if ($opendsa_activity->retake) {
                    $continuelink = new single_button(new moodle_url('/mod/opendsa_activity/view.php',
                            array('id' => $cm->id, 'pageid' => $opendsa_activity->firstpageid, 'startlastseen' => 'no')),
                            get_string('continue', 'opendsa_activity'), 'get');

                    echo html_writer::div($opendsa_activityoutput->message(get_string('leftduringtimed', 'opendsa_activity'), $continuelink),
                            'center leftduring');

                } else {
                    $courselink = new single_button(new moodle_url('/course/view.php',
                            array('id' => $PAGE->course->id)), get_string('returntocourse', 'opendsa_activity'), 'get');

                    echo html_writer::div($opendsa_activityoutput->message(get_string('leftduringtimednoretake', 'opendsa_activity'), $courselink),
                            'center leftduring');
                }
            } else {
                echo $opendsa_activityoutput->continue_links($opendsa_activity, $lastpageseen);
            }
            echo $opendsa_activityoutput->footer();
            exit();
        }
    }

    if ($attemptflag) {
        if (!$opendsa_activity->retake) {
            echo $opendsa_activityoutput->header($opendsa_activity, $cm, 'view', '', null, get_string("noretake", "opendsa_activity"));
            $courselink = new single_button(new moodle_url('/course/view.php', array('id'=>$PAGE->course->id)), get_string('returntocourse', 'opendsa_activity'), 'get');
            echo $opendsa_activityoutput->message(get_string("noretake", "opendsa_activity"), $courselink);
            echo $opendsa_activityoutput->footer();
            exit();
        }
    }
    // start at the first page
    if (!$pageid = $opendsa_activityfirstpageid) {
        echo $opendsa_activityoutput->header($opendsa_activity, $cm, 'view', '', null);
        // Lesson currently has no content. A message for display has been prepared and will be displayed by the header method
        // of the opendsa_activity renderer.
        echo $opendsa_activityoutput->footer();
        exit();
    }
    /// This is the code for starting a timed test
    if(!isset($USER->startopendsa_activity[$opendsa_activity->id]) && !$canmanage) {
        $opendsa_activity->start_timer();
    }
}

$currenttab = 'view';
$extraeditbuttons = false;
$opendsa_activitypageid = null;
$timer = null;

if ($pageid != OPENDSA_ACTIVITY_EOL) {

    $opendsa_activity->set_module_viewed();

    $timer = null;
    // This is the code updates the opendsa_activitytime for a timed test.
    $startlastseen = optional_param('startlastseen', '', PARAM_ALPHA);

    // Check to see if the user can see the left menu.
    if (!$canmanage) {
        $opendsa_activity->displayleft = opendsa_activity_displayleftif($opendsa_activity);

        $continue = ($startlastseen !== '');
        $restart  = ($continue && $startlastseen == 'yes');
        $timer = $opendsa_activity->update_timer($continue, $restart);

        // Check time limit.
        if (!$opendsa_activity->check_time($timer)) {
            redirect(new moodle_url('/mod/opendsa_activity/view.php', array('id' => $cm->id, 'pageid' => OPENDSA_ACTIVITY_EOL, 'outoftime' => 'normal')));
            die; // Shouldn't be reached, but make sure.
        }
    }

    list($newpageid, $page, $opendsa_activitycontent) = $opendsa_activity->prepare_page_and_contents($pageid, $opendsa_activityoutput, $reviewmode);

    if (($edit != -1) && $PAGE->user_allowed_editing()) {
        $USER->editing = $edit;
    }

    $PAGE->set_subpage($page->id);
    $currenttab = 'view';
    $extraeditbuttons = true;
    $opendsa_activitypageid = $page->id;
    $extrapagetitle = $page->title;

    opendsa_activity_add_fake_blocks($PAGE, $cm, $opendsa_activity, $timer);
    echo $opendsa_activityoutput->header($opendsa_activity, $cm, $currenttab, $extraeditbuttons, $opendsa_activitypageid, $extrapagetitle);
    if ($attemptflag) {
        // We are using level 3 header because attempt heading is a sub-heading of opendsa_activity title (MDL-30911).
        echo $OUTPUT->heading(get_string('attempt', 'opendsa_activity', $retries), 3);
    }
    // This calculates and prints the ongoing score.
    if ($opendsa_activity->ongoing && !empty($pageid) && !$reviewmode) {
        echo $opendsa_activityoutput->ongoing_score($opendsa_activity);
    }
    if ($opendsa_activity->displayleft) {
        echo '<a name="maincontent" id="maincontent" title="' . get_string('anchortitle', 'opendsa_activity') . '"></a>';
    }
    echo $opendsa_activitycontent;
    echo $opendsa_activityoutput->progress_bar($opendsa_activity);
    echo $opendsa_activityoutput->footer();

} else {

    // End of opendsa_activity reached work out grade.
    // Used to check to see if the student ran out of time.
    $outoftime = optional_param('outoftime', '', PARAM_ALPHA);

    $data = $opendsa_activity->process_eol_page($outoftime);
    $opendsa_activitycontent = $opendsa_activityoutput->display_eol_page($opendsa_activity, $data);

    opendsa_activity_add_fake_blocks($PAGE, $cm, $opendsa_activity, $timer);
    echo $opendsa_activityoutput->header($opendsa_activity, $cm, $currenttab, $extraeditbuttons, $opendsa_activitypageid, get_string("congratulations", "opendsa_activity"));
    echo $opendsa_activitycontent;
    echo $opendsa_activityoutput->footer();
}
