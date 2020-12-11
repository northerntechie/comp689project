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

echo $OUTPUT->header();
$exercise_name = $opendsa->name;

if(!empty($opendsa->exercisename)) {
    $exercise_name .= " - " . $opendsa->exercisename;
}

echo $OUTPUT->heading(format_string($exercise_name), 2, null);

// Request the launch content with an iframe tag.
/*
$attributes = [];
$attributes['id'] = "frame";
$attributes['height'] = '80%';
$attributes['width'] = '80%';
$path = $opendsa->exercisepath
$attributes['src'] = 'localhost:8080' . $path;

echo $OUTPUT->element
echo "<iframe id=\"frame\" width=\"100%\" height=\"100%\" src=\"http://localhost:8080/AV/Binary/BSTinsertPRO.html\"></iframe>";
*/

$attributes = [];
$attributes['id'] = "frame";
$attributes['height'] = '600px';
$attributes['width'] = '100%';
$path = $opendsa->exercisepath;
$attributes['src'] = 'http://localhost:8080' . $path;
$iframehtml = html_writer::tag('iframe', '', $attributes);

echo $iframehtml;

echo $OUTPUT->footer();
