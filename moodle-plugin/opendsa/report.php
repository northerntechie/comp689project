<?php

    require_once("../../config.php");
    require_once("lib.php");

    $id         = required_param('id', PARAM_INT);   //moduleid
    $download   = optional_param('download', '', PARAM_ALPHA);
    $action     = optional_param('action', '', PARAM_ALPHANUMEXT);
    $attemptids = optional_param_array('attemptid', array(), PARAM_INT); // Get array of responses to delete or modify.
    $userids    = optional_param_array('userid', array(), PARAM_INT); // Get array of users whose opendsas need to be modified.

    $url = new moodle_url('/mod/opendsa/report.php', array('id'=>$id));
    if ($download !== '') {
        $url->param('download', $download);
    }
    if ($action !== '') {
        $url->param('action', $action);
    }
    $PAGE->set_url($url);

    if (! $cm = get_coursemodule_from_id('opendsa', $id)) {
        print_error("invalidcoursemodule");
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error("coursemisconf");
    }

    require_login($course, false, $cm);

    $context = context_module::instance($cm->id);

    require_capability('mod/opendsa:readresponses', $context);

    if (!$opendsa = opendsa_get_opendsa($cm->instance)) {
        print_error('invalidcoursemodule');
    }

    $stropendsa = get_string("modulename", "opendsa");
    $stropendsas = get_string("modulenameplural", "opendsa");
    $strresponses = get_string("responses", "opendsa");

    $eventdata = array();
    $eventdata['objectid'] = $opendsa->id;
    $eventdata['context'] = $context;
    $eventdata['courseid'] = $course->id;
    $eventdata['other']['content'] = 'opendsareportcontentviewed';

    $event = \mod_opendsa\event\report_viewed::create($eventdata);
    $event->trigger();

    if (data_submitted() && has_capability('mod/opendsa:deleteresponses', $context) && confirm_sesskey()) {
        if ($action === 'delete') {
            // Delete responses of other users.
            opendsa_delete_responses($attemptids, $opendsa, $cm, $course);
            redirect("report.php?id=$cm->id");
        }
        if (preg_match('/^choose_(\d+)$/', $action, $actionmatch)) {
            // Modify responses of other users.
            $newoptionid = (int)$actionmatch[1];
            opendsa_modify_responses($userids, $attemptids, $newoptionid, $opendsa, $cm, $course);
            redirect("report.php?id=$cm->id");
        }
    }

    if (!$download) {
        $PAGE->navbar->add($strresponses);
        $PAGE->set_title(format_string($opendsa->name).": $strresponses");
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($opendsa->name), 2, null);
        /// Check to see if groups are being used in this opendsa
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode) {
            groups_get_activity_group($cm, true);
            groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/opendsa/report.php?id='.$id);
        }
    } else {
        $groupmode = groups_get_activity_groupmode($cm);

        // Trigger the report downloaded event.
        $eventdata = array();
        $eventdata['context'] = $context;
        $eventdata['courseid'] = $course->id;
        $eventdata['other']['content'] = 'opendsareportcontentviewed';
        $eventdata['other']['format'] = $download;
        $eventdata['other']['opendsaid'] = $opendsa->id;
        $event = \mod_opendsa\event\report_downloaded::create($eventdata);
        $event->trigger();

    }

    // Check if we want to include responses from inactive users.
    $onlyactive = $opendsa->includeinactive ? false : true;

    $users = opendsa_get_response_data($opendsa, $cm, $groupmode, $onlyactive);

    $extrafields = get_extra_user_fields($context);

    if ($download == "ods" && has_capability('mod/opendsa:downloadresponses', $context)) {
        require_once("$CFG->libdir/odslib.class.php");

    /// Calculate file name
        $shortname = format_string($course->shortname, true, array('context' => $context));
        $opendsaname = format_string($opendsa->name, true, array('context' => $context));
        $filename = clean_filename("$shortname " . strip_tags($opendsaname)) . '.ods';
    /// Creating a workbook
        $workbook = new MoodleODSWorkbook("-");
    /// Send HTTP headers
        $workbook->send($filename);
    /// Creating the first worksheet
        $myxls = $workbook->add_worksheet($strresponses);

    /// Print names of all the fields
        $i = 0;
        $myxls->write_string(0, $i++, get_string("lastname"));
        $myxls->write_string(0, $i++, get_string("firstname"));

        // Add headers for extra user fields.
        foreach ($extrafields as $field) {
            $myxls->write_string(0, $i++, get_user_field_name($field));
        }

        $myxls->write_string(0, $i++, get_string("group"));
        $myxls->write_string(0, $i++, get_string("opendsa", "opendsa"));

        // Generate the data for the body of the spreadsheet.
        $row = 1;
        if ($users) {
            foreach ($users as $option => $userid) {
                $option_text = opendsa_get_option_text($opendsa, $option);
                foreach ($userid as $user) {
                    $i = 0;
                    $myxls->write_string($row, $i++, $user->lastname);
                    $myxls->write_string($row, $i++, $user->firstname);
                    foreach ($extrafields as $field) {
                        $myxls->write_string($row, $i++, $user->$field);
                    }
                    $ug2 = '';
                    if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                        foreach ($usergrps as $ug) {
                            $ug2 = $ug2 . $ug->name;
                        }
                    }
                    $myxls->write_string($row, $i++, $ug2);

                    if (isset($option_text)) {
                        $myxls->write_string($row, $i++, format_string($option_text, true));
                    }
                    $row++;
                }
            }
        }
        /// Close the workbook
        $workbook->close();

        exit;
    }

    //print spreadsheet if one is asked for:
    if ($download == "xls" && has_capability('mod/opendsa:downloadresponses', $context)) {
        require_once("$CFG->libdir/excellib.class.php");

    /// Calculate file name
        $shortname = format_string($course->shortname, true, array('context' => $context));
        $opendsaname = format_string($opendsa->name, true, array('context' => $context));
        $filename = clean_filename("$shortname " . strip_tags($opendsaname)) . '.xls';
    /// Creating a workbook
        $workbook = new MoodleExcelWorkbook("-");
    /// Send HTTP headers
        $workbook->send($filename);
    /// Creating the first worksheet
        $myxls = $workbook->add_worksheet($strresponses);

    /// Print names of all the fields
        $i = 0;
        $myxls->write_string(0, $i++, get_string("lastname"));
        $myxls->write_string(0, $i++, get_string("firstname"));

        // Add headers for extra user fields.
        foreach ($extrafields as $field) {
            $myxls->write_string(0, $i++, get_user_field_name($field));
        }

        $myxls->write_string(0, $i++, get_string("group"));
        $myxls->write_string(0, $i++, get_string("opendsa", "opendsa"));

        // Generate the data for the body of the spreadsheet.
        $row = 1;
        if ($users) {
            foreach ($users as $option => $userid) {
                $i = 0;
                $option_text = opendsa_get_option_text($opendsa, $option);
                foreach($userid as $user) {
                    $i = 0;
                    $myxls->write_string($row, $i++, $user->lastname);
                    $myxls->write_string($row, $i++, $user->firstname);
                    foreach ($extrafields as $field) {
                        $myxls->write_string($row, $i++, $user->$field);
                    }
                    $ug2 = '';
                    if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                        foreach ($usergrps as $ug) {
                            $ug2 = $ug2 . $ug->name;
                        }
                    }
                    $myxls->write_string($row, $i++, $ug2);
                    if (isset($option_text)) {
                        $myxls->write_string($row, $i++, format_string($option_text, true));
                    }
                    $row++;
                }
            }
        }
        /// Close the workbook
        $workbook->close();
        exit;
    }

    // print text file
    if ($download == "txt" && has_capability('mod/opendsa:downloadresponses', $context)) {
        $shortname = format_string($course->shortname, true, array('context' => $context));
        $opendsaname = format_string($opendsa->name, true, array('context' => $context));
        $filename = clean_filename("$shortname " . strip_tags($opendsaname)) . '.txt';

        header("Content-Type: application/download\n");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
        header("Pragma: public");

        /// Print names of all the fields

        echo get_string("lastname") . "\t" . get_string("firstname") . "\t";

        // Add headers for extra user fields.
        foreach ($extrafields as $field) {
            echo get_user_field_name($field) . "\t";
        }

        echo get_string("group"). "\t";
        echo get_string("opendsa","opendsa"). "\n";

        /// generate the data for the body of the spreadsheet
        $i=0;
        if ($users) {
            foreach ($users as $option => $userid) {
                $option_text = opendsa_get_option_text($opendsa, $option);
                foreach($userid as $user) {
                    echo $user->lastname . "\t";
                    echo $user->firstname . "\t";
                    foreach ($extrafields as $field) {
                        echo $user->$field . "\t";
                    }
                    $ug2 = '';
                    if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                        foreach ($usergrps as $ug) {
                            $ug2 = $ug2. $ug->name;
                        }
                    }
                    echo $ug2. "\t";
                    if (isset($option_text)) {
                        echo format_string($option_text,true);
                    }
                    echo "\n";
                }
            }
        }
        exit;
    }
    $results = prepare_opendsa_show_results($opendsa, $course, $cm, $users);
    $renderer = $PAGE->get_renderer('mod_opendsa');
    echo $renderer->display_result($results, true);

   //now give links for downloading spreadsheets.
    if (!empty($users) && has_capability('mod/opendsa:downloadresponses',$context)) {
        $downloadoptions = array();
        $options = array();
        $options["id"] = "$cm->id";
        $options["download"] = "ods";
        $button =  $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadods"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $options["download"] = "xls";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadexcel"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $options["download"] = "txt";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadtext"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $downloadlist = html_writer::tag('ul', implode('', $downloadoptions), array('class' => 'list-inline inline'));
        $downloadlist .= html_writer::tag('div', '', array('class' => 'clearfloat'));
        echo html_writer::tag('div',$downloadlist, array('class' => 'downloadreport mt-1'));
    }
    echo $OUTPUT->footer();

