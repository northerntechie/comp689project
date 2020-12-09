<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');

$id         = required_param('id', PARAM_INT);                 // Course Module ID
$action     = optional_param('action', '', PARAM_ALPHANUMEXT);
$attemptids = optional_param_array('attemptid', array(), PARAM_INT); // Get array of responses to delete or modify.
$userids    = optional_param_array('userid', array(), PARAM_INT); // Get array of users whose opendsas need to be modified.
$notify     = optional_param('notify', '', PARAM_ALPHA);

$url = new moodle_url('/mod/opendsa/view.php', array('id'=>$id));
if ($action !== '') {
    $url->param('action', $action);
}
$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('opendsa', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

require_course_login($course, false, $cm);

if (!$opendsa = opendsa_get_opendsa($cm->instance)) {
    print_error('invalidcoursemodule');
}

$stropendsa = get_string('modulename', 'opendsa');
$stropendsas = get_string('modulenameplural', 'opendsa');

$context = context_module::instance($cm->id);

list($opendsaavailable, $warnings) = opendsa_get_availability_status($opendsa);

if ($action == 'delopendsa' and confirm_sesskey() and is_enrolled($context, NULL, 'mod/opendsa:choose') and $opendsa->allowupdate
        and $opendsaavailable) {
    $answercount = $DB->count_records('opendsa_answers', array('opendsaid' => $opendsa->id, 'userid' => $USER->id));
    if ($answercount > 0) {
        $opendsaanswers = $DB->get_records('opendsa_answers', array('opendsaid' => $opendsa->id, 'userid' => $USER->id),
            '', 'id');
        $todelete = array_keys($opendsaanswers);
        opendsa_delete_responses($todelete, $opendsa, $cm, $course);
        redirect("view.php?id=$cm->id");
    }
}

$PAGE->set_title($opendsa->name);
$PAGE->set_heading($course->fullname);

/*
/// Submit any new data if there is any
if (data_submitted() && !empty($action) && confirm_sesskey()) {
    $timenow = time();
    if (has_capability('mod/opendsa:deleteresponses', $context)) {
        if ($action === 'delete') {
            // Some responses need to be deleted.
            opendsa_delete_responses($attemptids, $opendsa, $cm, $course);
            redirect("view.php?id=$cm->id");
        }
        if (preg_match('/^choose_(\d+)$/', $action, $actionmatch)) {
            // Modify responses of other users.
            $newoptionid = (int)$actionmatch[1];
            opendsa_modify_responses($userids, $attemptids, $newoptionid, $opendsa, $cm, $course);
            redirect("view.php?id=$cm->id");
        }
    }

    // Redirection after all POSTs breaks block editing, we need to be more specific!
    if ($opendsa->allowmultiple) {
        $answer = optional_param_array('answer', array(), PARAM_INT);
    } else {
        $answer = optional_param('answer', '', PARAM_INT);
    }

    if (!$opendsaavailable) {
        $reason = current(array_keys($warnings));
        throw new moodle_exception($reason, 'opendsa', '', $warnings[$reason]);
    }

    if ($answer && is_enrolled($context, null, 'mod/opendsa:choose')) {
        opendsa_user_submit_response($answer, $opendsa, $USER->id, $course, $cm);
        redirect(new moodle_url('/mod/opendsa/view.php',
            array('id' => $cm->id, 'notify' => 'opendsasaved', 'sesskey' => sesskey())));
    } else if (empty($answer) and $action === 'makeopendsa') {
        // We cannot use the 'makeopendsa' alone because there might be some legacy renderers without it,
        // outdated renderers will not get the 'mustchoose' message - bad luck.
        redirect(new moodle_url('/mod/opendsa/view.php',
            array('id' => $cm->id, 'notify' => 'mustchooseone', 'sesskey' => sesskey())));
    }
}

// Completion and trigger events.
opendsa_view($opendsa, $course, $cm, $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($opendsa->name), 2, null);

if ($notify and confirm_sesskey()) {
    if ($notify === 'opendsasaved') {
        echo $OUTPUT->notification(get_string('opendsasaved', 'opendsa'), 'notifysuccess');
    } else if ($notify === 'mustchooseone') {
        echo $OUTPUT->notification(get_string('mustchooseone', 'opendsa'), 'notifyproblem');
    }
}

/// Display the opendsa and possibly results
$eventdata = array();
$eventdata['objectid'] = $opendsa->id;
$eventdata['context'] = $context;

/// Check to see if groups are being used in this opendsa
$groupmode = groups_get_activity_groupmode($cm);

if ($groupmode) {
    groups_get_activity_group($cm, true);
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/opendsa/view.php?id='.$id);
}

// Check if we want to include responses from inactive users.
$onlyactive = $opendsa->includeinactive ? false : true;

$allresponses = opendsa_get_response_data($opendsa, $cm, $groupmode, $onlyactive);   // Big function, approx 6 SQL calls per user.


if (has_capability('mod/opendsa:readresponses', $context)) {
    opendsa_show_reportlink($allresponses, $cm);
}

echo '<div class="clearer"></div>';

if ($opendsa->intro) {
    echo $OUTPUT->box(format_module_intro('opendsa', $opendsa, $cm->id), 'generalbox', 'intro');
}

$timenow = time();
$current = opendsa_get_my_response($opendsa);
//if user has already made a selection, and they are not allowed to update it or if opendsa is not open, show their selected answer.
if (isloggedin() && (!empty($current)) &&
    (empty($opendsa->allowupdate) || ($timenow > $opendsa->timeclose)) ) {
    $opendsatexts = array();
    foreach ($current as $c) {
        $opendsatexts[] = format_string(opendsa_get_option_text($opendsa, $c->optionid));
    }
    echo $OUTPUT->box(get_string("yourselection", "opendsa", userdate($opendsa->timeopen)).": ".implode('; ', $opendsatexts), 'generalbox', 'yourselection');
}

/// Print the form
$opendsaopen = true;
if ((!empty($opendsa->timeopen)) && ($opendsa->timeopen > $timenow)) {
    if ($opendsa->showpreview) {
        echo $OUTPUT->box(get_string('previewonly', 'opendsa', userdate($opendsa->timeopen)), 'generalbox alert');
    } else {
        echo $OUTPUT->box(get_string("notopenyet", "opendsa", userdate($opendsa->timeopen)), "generalbox notopenyet");
        echo $OUTPUT->footer();
        exit;
    }
} else if ((!empty($opendsa->timeclose)) && ($timenow > $opendsa->timeclose)) {
    echo $OUTPUT->box(get_string("expired", "opendsa", userdate($opendsa->timeclose)), "generalbox expired");
    $opendsaopen = false;
}

if ( (!$current or $opendsa->allowupdate) and $opendsaopen and is_enrolled($context, NULL, 'mod/opendsa:choose')) {

    // Show information on how the results will be published to students.
    $publishinfo = null;
    switch ($opendsa->showresults) {
        case OPENDSA_SHOWRESULTS_NOT:
            $publishinfo = get_string('publishinfonever', 'opendsa');
            break;

        case OPENDSA_SHOWRESULTS_AFTER_ANSWER:
            if ($opendsa->publish == OPENDSA_PUBLISH_ANONYMOUS) {
                $publishinfo = get_string('publishinfoanonafter', 'opendsa');
            } else {
                $publishinfo = get_string('publishinfofullafter', 'opendsa');
            }
            break;

        case OPENDSA_SHOWRESULTS_AFTER_CLOSE:
            if ($opendsa->publish == OPENDSA_PUBLISH_ANONYMOUS) {
                $publishinfo = get_string('publishinfoanonclose', 'opendsa');
            } else {
                $publishinfo = get_string('publishinfofullclose', 'opendsa');
            }
            break;

        default:
            // No need to inform the user in the case of OPENDSA_SHOWRESULTS_ALWAYS since it's already obvious that the results are
            // being published.
            break;
    }

    // Show info if necessary.
    if (!empty($publishinfo)) {
        echo $OUTPUT->notification($publishinfo, 'info');
    }

    // They haven't made their opendsa yet or updates allowed and opendsa is open.
    $options = opendsa_prepare_options($opendsa, $USER, $cm, $allresponses);
    $renderer = $PAGE->get_renderer('mod_opendsa');
    echo $renderer->display_options($options, $cm->id, $opendsa->display, $opendsa->allowmultiple);
    $opendsaformshown = true;
} else {
    $opendsaformshown = false;
}

if (!$opendsaformshown) {
    $sitecontext = context_system::instance();

    if (isguestuser()) {
        // Guest account
        echo $OUTPUT->confirm(get_string('noguestchoose', 'opendsa').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/course/view.php', array('id'=>$course->id)));
    } else if (!is_enrolled($context)) {
        // Only people enrolled can make a opendsa
        $SESSION->wantsurl = qualified_me();
        $SESSION->enrolcancel = get_local_referer(false);

        $coursecontext = context_course::instance($course->id);
        $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

        echo $OUTPUT->box_start('generalbox', 'notice');
        echo '<p align="center">'. get_string('notenrolledchoose', 'opendsa') .'</p>';
        echo $OUTPUT->container_start('continuebutton');
        echo $OUTPUT->single_button(new moodle_url('/enrol/index.php?', array('id'=>$course->id)), get_string('enrolme', 'core_enrol', $courseshortname));
        echo $OUTPUT->container_end();
        echo $OUTPUT->box_end();

    }
}

// print the results at the bottom of the screen
if (opendsa_can_view_results($opendsa, $current, $opendsaopen)) {
    $results = prepare_opendsa_show_results($opendsa, $course, $cm, $allresponses);
    $renderer = $PAGE->get_renderer('mod_opendsa');
    $resultstable = $renderer->display_result($results);
    echo $OUTPUT->box($resultstable);

} else if (!$opendsaformshown) {
    echo $OUTPUT->box(get_string('noresultsviewable', 'opendsa'));
}
*/

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($opendsa->name), 2, null);

// Request the launch content with an iframe tag.
    $attributes = [];
    $attributes['id'] = "frame";
    $attributes['height'] = '600px';
    $attributes['width'] = '100%';
    $attributes['src'] = 'localhost:8080/AV/Binary/BSTInsertPRO.html';
    
    echo "<iframe id=\"frame\" width=\"100%\" height=\"100%\" src=\"http://localhost:8080/AV/Binary/BSTinsertPRO.html\"></iframe>";
    
    echo $OUTPUT->footer();
