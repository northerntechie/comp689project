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
 * Imports opendsa_activity pages
 *
 * @package mod_opendsa_activity
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once($CFG->libdir.'/questionlib.php');
require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
require_once($CFG->dirroot.'/mod/opendsa_activity/import_form.php');
require_once($CFG->dirroot.'/mod/opendsa_activity/format.php');  // Parent class

$id     = required_param('id', PARAM_INT);         // Course Module ID
$pageid = optional_param('pageid', '', PARAM_INT); // Page ID

$PAGE->set_url('/mod/opendsa_activity/import.php', array('id'=>$id, 'pageid'=>$pageid));

$cm = get_coursemodule_from_id('opendsa_activity', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$opendsa_activity = new opendsa_activity($DB->get_record('opendsa_activity', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/opendsa_activity:edit', $context);

$strimportquestions = get_string("importquestions", "opendsa_activity");
$stropendsa_activitys = get_string("modulenameplural", "opendsa_activity");

$manager = opendsa_activity_page_type_manager::get($opendsa_activity);

$data = new stdClass;
$data->id = $PAGE->cm->id;
$data->pageid = $pageid;

$mform = new opendsa_activity_import_form(null, array('formats'=>opendsa_activity_get_import_export_formats('import')));
$mform->set_data($data);

    $PAGE->navbar->add($strimportquestions);
    $PAGE->set_title($strimportquestions);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($opendsa_activity->name), 2);
    echo $OUTPUT->heading_with_help($strimportquestions, 'importquestions', 'opendsa_activity', '', '', 3);

if ($data = $mform->get_data()) {

    require_sesskey();

    $realfilename = $mform->get_new_filename('questionfile');
    $importfile = make_request_directory() . "/{$realfilename}";
    if (!$result = $mform->save_file('questionfile', $importfile, true)) {
        throw new moodle_exception('uploadproblem');
    }

    $formatclass = 'qformat_'.$data->format;
    $formatclassfile = $CFG->dirroot.'/question/format/'.$data->format.'/format.php';
    if (!is_readable($formatclassfile)) {
        print_error('unknowformat','', '', $data->format);
            }
    require_once($formatclassfile);
    $format = new $formatclass();

    $format->set_importcontext($context);

    // Do anything before that we need to
    if (! $format->importpreprocess()) {
                print_error('preprocesserror', 'opendsa_activity');
            }

    // Process the uploaded file
    if (! $format->importprocess($importfile, $opendsa_activity, $pageid)) {
                print_error('processerror', 'opendsa_activity');
            }

    // In case anything needs to be done after
    if (! $format->importpostprocess()) {
                print_error('postprocesserror', 'opendsa_activity');
            }

            echo "<hr>";
    echo $OUTPUT->continue_button('view.php?id='.$PAGE->cm->id);

} else {

    // Print upload form
    $mform->display();
}

echo $OUTPUT->footer();
