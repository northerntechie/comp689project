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
 * Action for processing page answers by users
 *
 * @package mod_opendsa_activity
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

/** Require the specific libraries */
require_once("../../config.php");
require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('opendsa_activity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$opendsa_activity = new opendsa_activity($DB->get_record('opendsa_activity', array('id' => $cm->instance), '*', MUST_EXIST), $cm, $course);

require_login($course, false, $cm);
require_sesskey();

// Apply overrides.
$opendsa_activity->update_effective_access($USER->id);

$context = $opendsa_activity->context;
$canmanage = $opendsa_activity->can_manage();
$opendsa_activity_output = $PAGE->get_renderer('mod_opendsa_activity');

$url = new moodle_url('/mod/opendsa_activity/continue.php', array('id'=>$cm->id));
$PAGE->set_url($url);
$PAGE->set_pagetype('mod-opendsa_activity-view');
$PAGE->navbar->add(get_string('continue', 'opendsa_activity'));

// This is the code updates the opendsa_activity time for a timed test
// get time information for this user
if (!$canmanage) {
    $opendsa_activity->displayleft = opendsa_activity_displayleftif($opendsa_activity);
    $timer = $opendsa_activity->update_timer();
    if (!$opendsa_activity->check_time($timer)) {
        redirect(new moodle_url('/mod/opendsa_activity/view.php', array('id' => $cm->id, 'pageid' => opendsa_activity_EOL, 'outoftime' => 'normal')));
        die; // Shouldn't be reached, but make sure.
    }
} else {
    $timer = new stdClass;
}

// record answer (if necessary) and show response (if none say if answer is correct or not)
$page = $opendsa_activity->load_page(required_param('pageid', PARAM_INT));

$reviewmode = $opendsa_activity->is_in_review_mode();

// Process the page responses.
$result = $opendsa_activity->process_page_responses($page);

if ($result->nodefaultresponse || $result->inmediatejump) {
    // Don't display feedback or force a redirecto to newpageid.
    redirect(new moodle_url('/mod/opendsa_activity/view.php', array('id'=>$cm->id,'pageid'=>$result->newpageid)));
}

// Set Messages.
$opendsa_activity->add_messages_on_page_process($page, $result, $reviewmode);

$PAGE->set_url('/mod/opendsa_activity/view.php', array('id' => $cm->id, 'pageid' => $page->id));
$PAGE->set_subpage($page->id);

/// Print the header, heading and tabs
opendsa_activity_add_fake_blocks($PAGE, $cm, $opendsa_activity, $timer);
echo $opendsa_activity_output->header($opendsa_activity, $cm, 'view', true, $page->id, get_string('continue', 'opendsa_activity'));

if ($opendsa_activity->displayleft) {
    echo '<a name="maincontent" id="maincontent" title="'.get_string('anchortitle', 'opendsa_activity').'"></a>';
}
// This calculates and prints the ongoing score message
if ($opendsa_activity->ongoing && !$reviewmode) {
    echo $opendsa_activity_output->ongoing_score($opendsa_activity);
}
if (!$reviewmode) {
    echo format_text($result->feedback, FORMAT_MOODLE, array('context' => $context, 'noclean' => true));
}

// User is modifying attempts - save button and some instructions
if (isset($USER->modattempts[$opendsa_activity->id])) {
    $content = $OUTPUT->box(get_string("gotoendofopendsa_activity", "opendsa_activity"), 'center');
    $content .= $OUTPUT->box(get_string("or", "opendsa_activity"), 'center');
    $content .= $OUTPUT->box(get_string("continuetonextpage", "opendsa_activity"), 'center');
    $url = new moodle_url('/mod/opendsa_activity/view.php', array('id' => $cm->id, 'pageid' => opendsa_activity_EOL));
    echo $content . $OUTPUT->single_button($url, get_string('finish', 'opendsa_activity'));
}

// Review button back
if (!$result->correctanswer && !$result->noanswer && !$result->isessayquestion && !$reviewmode && $opendsa_activity->review && !$result->maxattemptsreached) {
    $url = new moodle_url('/mod/opendsa_activity/view.php', array('id' => $cm->id, 'pageid' => $page->id));
    echo $OUTPUT->single_button($url, get_string('reviewquestionback', 'opendsa_activity'));
}

$url = new moodle_url('/mod/opendsa_activity/view.php', array('id'=>$cm->id, 'pageid'=>$result->newpageid));

if ($opendsa_activity->review && !$result->correctanswer && !$result->noanswer && !$result->isessayquestion && !$result->maxattemptsreached) {
    // If both the "Yes, I'd like to try again" and "No, I just want to go on  to the next question" point to the same
    // page then don't show the "No, I just want to go on to the next question" button. It's confusing.
    if ($page->id != $result->newpageid) {
        // Button to continue the opendsa_activity (the page to go is configured by the teacher).
        echo $OUTPUT->single_button($url, get_string('reviewquestioncontinue', 'opendsa_activity'));
    }
} else {
    // Normal continue button
    echo $OUTPUT->single_button($url, get_string('continue', 'opendsa_activity'));
}

echo $opendsa_activityoutput->footer();
