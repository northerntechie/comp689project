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
 * This file plays the mediafile set in opendsa_activity settings.
 *
 *  If there is a way to use the resource class instead of this code, please change to do so
 *
 *
 * @package mod_opendsa_activity
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');

$id = required_param('id', PARAM_INT);    // Course Module ID
$printclose = optional_param('printclose', 0, PARAM_INT);

$cm = get_coursemodule_from_id('opendsa_activity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$opendsa_activity = new opendsa_activity($DB->get_record('opendsa_activity', array('id' => $cm->instance), '*', MUST_EXIST), $cm);

require_login($course, false, $cm);

// Apply overrides.
$opendsa_activity->update_effective_access($USER->id);

$context = $opendsa_activity->context;
$canmanage = $opendsa_activity->can_manage();

$url = new moodle_url('/mod/opendsa_activity/mediafile.php', array('id'=>$id));
if ($printclose !== '') {
    $url->param('printclose', $printclose);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title($course->shortname);

$opendsa_activityoutput = $PAGE->get_renderer('mod_opendsa_activity');

// Get the mimetype
$mimetype = mimeinfo("type", $opendsa_activity->mediafile);

if ($printclose) {  // this is for framesets
    if ($opendsa_activity->mediaclose) {
        echo $opendsa_activityoutput->header($opendsa_activity, $cm);
        echo $OUTPUT->box('<form><div><input type="button" onclick="top.close();" value="'.get_string("closewindow").'" /></div></form>', 'opendsa_activitymediafilecontrol');
        echo $opendsa_activityoutput->footer();
    }
    exit();
}

// Check access restrictions.
if ($timerestriction = $opendsa_activity->get_time_restriction_status()) {  // Deadline restrictions.
    echo $opendsa_activityoutput->header($opendsa_activity, $cm, '', false, null, get_string('notavailable'));
    echo $opendsa_activityoutput->opendsa_activity_inaccessible(get_string($timerestriction->reason, 'opendsa_activity', userdate($timerestriction->time)));
    echo $opendsa_activityoutput->footer();
    exit();
} else if ($passwordrestriction = $opendsa_activity->get_password_restriction_status(null)) { // Password protected opendsa_activity code.
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

echo $opendsa_activityoutput->header($opendsa_activity, $cm);

// print the embedded media html code
echo $OUTPUT->box(opendsa_activity_get_media_html($opendsa_activity, $context));

if ($opendsa_activity->mediaclose) {
   echo '<div class="opendsa_activitymediafilecontrol">';
   echo $OUTPUT->close_window_button();
   echo '</div>';
}

echo $opendsa_activityoutput->footer();
