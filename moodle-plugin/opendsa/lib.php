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
 * @package   mod_opendsa
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @global int $OPENDSA_COLUMN_HEIGHT */
global $OPENDSA_COLUMN_HEIGHT;
$OPENDSA_COLUMN_HEIGHT = 300;

/** @global int $OPENDSA_COLUMN_WIDTH */
global $OPENDSA_COLUMN_WIDTH;
$OPENDSA_COLUMN_WIDTH = 300;

define('OPENDSA_PUBLISH_ANONYMOUS', '0');
define('OPENDSA_PUBLISH_NAMES',     '1');

define('OPENDSA_SHOWRESULTS_NOT',          '0');
define('OPENDSA_SHOWRESULTS_AFTER_ANSWER', '1');
define('OPENDSA_SHOWRESULTS_AFTER_CLOSE',  '2');
define('OPENDSA_SHOWRESULTS_ALWAYS',       '3');

define('OPENDSA_DISPLAY_HORIZONTAL',  '0');
define('OPENDSA_DISPLAY_VERTICAL',    '1');

define('OPENDSA_EVENT_TYPE_OPEN', 'open');
define('OPENDSA_EVENT_TYPE_CLOSE', 'close');

/** @global array $OPENDSA_PUBLISH */
global $OPENDSA_PUBLISH;
$OPENDSA_PUBLISH = array (OPENDSA_PUBLISH_ANONYMOUS  => get_string('publishanonymous', 'opendsa'),
                         OPENDSA_PUBLISH_NAMES      => get_string('publishnames', 'opendsa'));

/** @global array $OPENDSA_SHOWRESULTS */
global $OPENDSA_SHOWRESULTS;
$OPENDSA_SHOWRESULTS = array (OPENDSA_SHOWRESULTS_NOT          => get_string('publishnot', 'opendsa'),
                         OPENDSA_SHOWRESULTS_AFTER_ANSWER => get_string('publishafteranswer', 'opendsa'),
                         OPENDSA_SHOWRESULTS_AFTER_CLOSE  => get_string('publishafterclose', 'opendsa'),
                         OPENDSA_SHOWRESULTS_ALWAYS       => get_string('publishalways', 'opendsa'));

/** @global array $OPENDSA_DISPLAY */
global $OPENDSA_DISPLAY;
$OPENDSA_DISPLAY = array (OPENDSA_DISPLAY_HORIZONTAL   => get_string('displayhorizontal', 'opendsa'),
                         OPENDSA_DISPLAY_VERTICAL     => get_string('displayvertical','opendsa'));

/// Standard functions /////////////////////////////////////////////////////////

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $opendsa
 * @return object|null
 */
function opendsa_user_outline($course, $user, $mod, $opendsa) {
    global $DB;
    if ($answer = $DB->get_record('opendsa_answers', array('opendsaid' => $opendsa->id, 'userid' => $user->id))) {
        $result = new stdClass();
        $result->info = "'".format_string(opendsa_get_option_text($opendsa, $answer->optionid))."'";
        $result->time = $answer->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * Callback for the "Complete" report - prints the activity summary for the given user
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $opendsa
 */
function opendsa_user_complete($course, $user, $mod, $opendsa) {
    global $DB;
    if ($answers = $DB->get_records('opendsa_answers', array("opendsaid" => $opendsa->id, "userid" => $user->id))) {
        $info = [];
        foreach ($answers as $answer) {
            $info[] = "'" . format_string(opendsa_get_option_text($opendsa, $answer->optionid)) . "'";
        }
        core_collator::asort($info);
        echo get_string("answered", "opendsa") . ": ". join(', ', $info) . ". " .
                get_string("updated", '', userdate($answer->timemodified));
    } else {
        print_string("notanswered", "opendsa");
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $opendsa
 * @return int
 */
function opendsa_add_instance($opendsa) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/opendsa/locallib.php');

    $opendsa->timemodified = time();

    //insert answers
    $opendsa->id = $DB->insert_record("opendsa", $opendsa);
    var_dump($opendsa);
    
    return $opendsa->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $opendsa
 * @return bool
 */
function opendsa_update_instance($opendsa) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/opendsa/locallib.php');

    $opendsa->id = $opendsa->instance;
    $opendsa->timemodified = time();

    /*
    //update, delete or insert answers
    foreach ($opendsa->option as $key => $value) {
        $value = trim($value);
        $option = new stdClass();
        $option->text = $value;
        $option->opendsaid = $opendsa->id;
        if (isset($opendsa->limit[$key])) {
            $option->maxanswers = $opendsa->limit[$key];
        }
        $option->timemodified = time();
        if (isset($opendsa->optionid[$key]) && !empty($opendsa->optionid[$key])){//existing opendsa record
            $option->id=$opendsa->optionid[$key];
            if (isset($value) && $value <> '') {
                $DB->update_record("opendsa_options", $option);
            } else {
                // Remove the empty (unused) option.
                $DB->delete_records("opendsa_options", array("id" => $option->id));
                // Delete any answers associated with this option.
                $DB->delete_records("opendsa_answers", array("opendsaid" => $opendsa->id, "optionid" => $option->id));
            }
        } else {
            if (isset($value) && $value <> '') {
                $DB->insert_record("opendsa_options", $option);
            }
        }
    }

    // Add calendar events if necessary.
    opendsa_set_events($opendsa);
    $completionexpected = (!empty($opendsa->completionexpected)) ? $opendsa->completionexpected : null;
    \core_completion\api::update_completion_date_event($opendsa->coursemodule, 'opendsa', $opendsa->id, $completionexpected);
    */
    return $DB->update_record('opendsa', $opendsa);
}

/**
 * @global object
 * @param object $opendsa
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function opendsa_prepare_options($opendsa, $user, $coursemodule, $allresponses) {
    global $DB;

    $cdisplay = array('options'=>array());

    $cdisplay['limitanswers'] = true;
    $context = context_module::instance($coursemodule->id);

    foreach ($opendsa->option as $optionid => $text) {
        if (isset($text)) { //make sure there are no dud entries in the db with blank text values.
            $option = new stdClass;
            $option->attributes = new stdClass;
            $option->attributes->value = $optionid;
            $option->text = format_string($text);
            $option->maxanswers = $opendsa->maxanswers[$optionid];
            $option->displaylayout = $opendsa->display;

            if (isset($allresponses[$optionid])) {
                $option->countanswers = count($allresponses[$optionid]);
            } else {
                $option->countanswers = 0;
            }
            if ($DB->record_exists('opendsa_answers', array('opendsaid' => $opendsa->id, 'userid' => $user->id, 'optionid' => $optionid))) {
                $option->attributes->checked = true;
            }
            if ( $opendsa->limitanswers && ($option->countanswers >= $option->maxanswers) && empty($option->attributes->checked)) {
                $option->attributes->disabled = true;
            }
            $cdisplay['options'][] = $option;
        }
    }

    $cdisplay['hascapability'] = is_enrolled($context, NULL, 'mod/opendsa:choose'); //only enrolled users are allowed to make a opendsa

    if ($opendsa->allowupdate && $DB->record_exists('opendsa_answers', array('opendsaid'=> $opendsa->id, 'userid'=> $user->id))) {
        $cdisplay['allowupdate'] = true;
    }

    if ($opendsa->showpreview && $opendsa->timeopen > time()) {
        $cdisplay['previewonly'] = true;
    }

    return $cdisplay;
}

/**
 * Modifies responses of other users adding the option $newoptionid to them
 *
 * @param array $userids list of users to add option to (must be users without any answers yet)
 * @param array $answerids list of existing attempt ids of users (will be either appended or
 *      substituted with the newoptionid, depending on $opendsa->allowmultiple)
 * @param int $newoptionid
 * @param stdClass $opendsa opendsa object, result of {@link opendsa_get_opendsa()}
 * @param stdClass $cm
 * @param stdClass $course
 */
function opendsa_modify_responses($userids, $answerids, $newoptionid, $opendsa, $cm, $course) {
    // Get all existing responses and the list of non-respondents.
    $groupmode = groups_get_activity_groupmode($cm);
    $onlyactive = $opendsa->includeinactive ? false : true;
    $allresponses = opendsa_get_response_data($opendsa, $cm, $groupmode, $onlyactive);

    // Check that the option value is valid.
    if (!$newoptionid || !isset($opendsa->option[$newoptionid])) {
        return;
    }

    // First add responses for users who did not make any opendsa yet.
    foreach ($userids as $userid) {
        if (isset($allresponses[0][$userid])) {
            opendsa_user_submit_response($newoptionid, $opendsa, $userid, $course, $cm);
        }
    }

    // Create the list of all options already selected by each user.
    $optionsbyuser = []; // Mapping userid=>array of chosen opendsa options.
    $usersbyanswer = []; // Mapping answerid=>userid (which answer belongs to each user).
    foreach ($allresponses as $optionid => $responses) {
        if ($optionid > 0) {
            foreach ($responses as $userid => $userresponse) {
                $optionsbyuser += [$userid => []];
                $optionsbyuser[$userid][] = $optionid;
                $usersbyanswer[$userresponse->answerid] = $userid;
            }
        }
    }

    // Go through the list of submitted attemptids and find which users answers need to be updated.
    foreach ($answerids as $answerid) {
        if (isset($usersbyanswer[$answerid])) {
            $userid = $usersbyanswer[$answerid];
            if (!in_array($newoptionid, $optionsbyuser[$userid])) {
                $options = $opendsa->allowmultiple ?
                        array_merge($optionsbyuser[$userid], [$newoptionid]) : $newoptionid;
                opendsa_user_submit_response($options, $opendsa, $userid, $course, $cm);
            }
        }
    }
}

/**
 * Process user submitted answers for a opendsa,
 * and either updating them or saving new answers.
 *
 * @param int|array $formanswer the id(s) of the user submitted opendsa options.
 * @param object $opendsa the selected opendsa.
 * @param int $userid user identifier.
 * @param object $course current course.
 * @param object $cm course context.
 * @return void
 */
function opendsa_user_submit_response($formanswer, $opendsa, $userid, $course, $cm) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir.'/completionlib.php');

    $continueurl = new moodle_url('/mod/opendsa/view.php', array('id' => $cm->id));

    if (empty($formanswer)) {
        print_error('atleastoneoption', 'opendsa', $continueurl);
    }

    if (is_array($formanswer)) {
        if (!$opendsa->allowmultiple) {
            print_error('multiplenotallowederror', 'opendsa', $continueurl);
        }
        $formanswers = $formanswer;
    } else {
        $formanswers = array($formanswer);
    }

    $options = $DB->get_records('opendsa_options', array('opendsaid' => $opendsa->id), '', 'id');
    foreach ($formanswers as $key => $val) {
        if (!isset($options[$val])) {
            print_error('cannotsubmit', 'opendsa', $continueurl);
        }
    }
    // Start lock to prevent synchronous access to the same data
    // before it's updated, if using limits.
    if ($opendsa->limitanswers) {
        $timeout = 10;
        $locktype = 'mod_opendsa_opendsa_user_submit_response';
        // Limiting access to this opendsa.
        $resouce = 'opendsaid:' . $opendsa->id;
        $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);

        // Opening the lock.
        $opendsalock = $lockfactory->get_lock($resouce, $timeout, MINSECS);
        if (!$opendsalock) {
            print_error('cannotsubmit', 'opendsa', $continueurl);
        }
    }

    $current = $DB->get_records('opendsa_answers', array('opendsaid' => $opendsa->id, 'userid' => $userid));

    // Array containing [answerid => optionid] mapping.
    $existinganswers = array_map(function($answer) {
        return $answer->optionid;
    }, $current);

    $context = context_module::instance($cm->id);

    $opendsasexceeded = false;
    $countanswers = array();
    foreach ($formanswers as $val) {
        $countanswers[$val] = 0;
    }
    if($opendsa->limitanswers) {
        // Find out whether groups are being used and enabled
        if (groups_get_activity_groupmode($cm) > 0) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            $currentgroup = 0;
        }

        list ($insql, $params) = $DB->get_in_or_equal($formanswers, SQL_PARAMS_NAMED);

        if($currentgroup) {
            // If groups are being used, retrieve responses only for users in
            // current group
            global $CFG;

            $params['groupid'] = $currentgroup;
            $sql = "SELECT ca.*
                      FROM {opendsa_answers} ca
                INNER JOIN {groups_members} gm ON ca.userid=gm.userid
                     WHERE optionid $insql
                       AND gm.groupid= :groupid";
        } else {
            // Groups are not used, retrieve all answers for this option ID
            $sql = "SELECT ca.*
                      FROM {opendsa_answers} ca
                     WHERE optionid $insql";
        }

        $answers = $DB->get_records_sql($sql, $params);
        if ($answers) {
            foreach ($answers as $a) { //only return enrolled users.
                if (is_enrolled($context, $a->userid, 'mod/opendsa:choose')) {
                    $countanswers[$a->optionid]++;
                }
            }
        }

        foreach ($countanswers as $opt => $count) {
            // Ignore the user's existing answers when checking whether an answer count has been exceeded.
            // A user may wish to update their response with an additional opendsa option and shouldn't be competing with themself!
            if (in_array($opt, $existinganswers)) {
                continue;
            }
            if ($count >= $opendsa->maxanswers[$opt]) {
                $opendsasexceeded = true;
                break;
            }
        }
    }

    // Check the user hasn't exceeded the maximum selections for the opendsa(s) they have selected.
    $answersnapshots = array();
    $deletedanswersnapshots = array();
    if (!($opendsa->limitanswers && $opendsasexceeded)) {
        if ($current) {
            // Update an existing answer.
            foreach ($current as $c) {
                if (in_array($c->optionid, $formanswers)) {
                    $DB->set_field('opendsa_answers', 'timemodified', time(), array('id' => $c->id));
                } else {
                    $deletedanswersnapshots[] = $c;
                    $DB->delete_records('opendsa_answers', array('id' => $c->id));
                }
            }

            // Add new ones.
            foreach ($formanswers as $f) {
                if (!in_array($f, $existinganswers)) {
                    $newanswer = new stdClass();
                    $newanswer->optionid = $f;
                    $newanswer->opendsaid = $opendsa->id;
                    $newanswer->userid = $userid;
                    $newanswer->timemodified = time();
                    $newanswer->id = $DB->insert_record("opendsa_answers", $newanswer);
                    $answersnapshots[] = $newanswer;
                }
            }
        } else {
            // Add new answer.
            foreach ($formanswers as $answer) {
                $newanswer = new stdClass();
                $newanswer->opendsaid = $opendsa->id;
                $newanswer->userid = $userid;
                $newanswer->optionid = $answer;
                $newanswer->timemodified = time();
                $newanswer->id = $DB->insert_record("opendsa_answers", $newanswer);
                $answersnapshots[] = $newanswer;
            }

            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $opendsa->completionsubmit) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }
    } else {
        // This is a opendsa with limited options, and one of the options selected has just run over its limit.
        $opendsalock->release();
        print_error('opendsafull', 'opendsa', $continueurl);
    }

    // Release lock.
    if (isset($opendsalock)) {
        $opendsalock->release();
    }

    // Trigger events.
    foreach ($deletedanswersnapshots as $answer) {
        \mod_opendsa\event\answer_deleted::create_from_object($answer, $opendsa, $cm, $course)->trigger();
    }
    foreach ($answersnapshots as $answer) {
        \mod_opendsa\event\answer_created::create_from_object($answer, $opendsa, $cm, $course)->trigger();
    }
}

/**
 * @param array $user
 * @param object $cm
 * @return void Output is echo'd
 */
function opendsa_show_reportlink($user, $cm) {
    $userschosen = array();
    foreach($user as $optionid => $userlist) {
        if ($optionid) {
            $userschosen = array_merge($userschosen, array_keys($userlist));
        }
    }
    $responsecount = count(array_unique($userschosen));

    echo '<div class="reportlink">';
    echo "<a href=\"report.php?id=$cm->id\">".get_string("viewallresponses", "opendsa", $responsecount)."</a>";
    echo '</div>';
}

/**
 * @global object
 * @param object $opendsa
 * @param object $course
 * @param object $coursemodule
 * @param array $allresponses

 *  * @param bool $allresponses
 * @return object
 */
function prepare_opendsa_show_results($opendsa, $course, $cm, $allresponses) {
    global $OUTPUT;

    $display = clone($opendsa);
    $display->coursemoduleid = $cm->id;
    $display->courseid = $course->id;

    if (!empty($opendsa->showunanswered)) {
        $opendsa->option[0] = get_string('notanswered', 'opendsa');
        $opendsa->maxanswers[0] = 0;
    }

    // Remove from the list of non-respondents the users who do not have access to this activity.
    if (!empty($display->showunanswered) && $allresponses[0]) {
        $info = new \core_availability\info_module(cm_info::create($cm));
        $allresponses[0] = $info->filter_user_list($allresponses[0]);
    }

    //overwrite options value;
    $display->options = array();
    $allusers = [];
    foreach ($opendsa->option as $optionid => $optiontext) {
        $display->options[$optionid] = new stdClass;
        $display->options[$optionid]->text = format_string($optiontext, true,
            ['context' => context_module::instance($cm->id)]);
        $display->options[$optionid]->maxanswer = $opendsa->maxanswers[$optionid];

        if (array_key_exists($optionid, $allresponses)) {
            $display->options[$optionid]->user = $allresponses[$optionid];
            $allusers = array_merge($allusers, array_keys($allresponses[$optionid]));
        }
    }
    unset($display->option);
    unset($display->maxanswers);

    $display->numberofuser = count(array_unique($allusers));
    $context = context_module::instance($cm->id);
    $display->viewresponsecapability = has_capability('mod/opendsa:readresponses', $context);
    $display->deleterepsonsecapability = has_capability('mod/opendsa:deleteresponses',$context);
    $display->fullnamecapability = has_capability('moodle/site:viewfullnames', $context);

    if (empty($allresponses)) {
        echo $OUTPUT->heading(get_string("nousersyet"), 3, null);
        return false;
    }

    return $display;
}

/**
 * @global object
 * @param array $attemptids
 * @param object $opendsa OpenDSA main table row
 * @param object $cm Course-module object
 * @param object $course Course object
 * @return bool
 */
function opendsa_delete_responses($attemptids, $opendsa, $cm, $course) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir.'/completionlib.php');

    if(!is_array($attemptids) || empty($attemptids)) {
        return false;
    }

    foreach($attemptids as $num => $attemptid) {
        if(empty($attemptid)) {
            unset($attemptids[$num]);
        }
    }

    $completion = new completion_info($course);
    foreach($attemptids as $attemptid) {
        if ($todelete = $DB->get_record('opendsa_answers', array('opendsaid' => $opendsa->id, 'id' => $attemptid))) {
            // Trigger the event answer deleted.
            \mod_opendsa\event\answer_deleted::create_from_object($todelete, $opendsa, $cm, $course)->trigger();
            $DB->delete_records('opendsa_answers', array('opendsaid' => $opendsa->id, 'id' => $attemptid));
        }
    }

    // Update completion state.
    if ($completion->is_enabled($cm) && $opendsa->completionsubmit) {
        $completion->update_state($cm, COMPLETION_INCOMPLETE);
    }

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function opendsa_delete_instance($id) {
    global $DB;

    if (! $opendsa = $DB->get_record("opendsa", array("id"=>"$id"))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("opendsa_answers", array("opendsaid"=>"$opendsa->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("opendsa_options", array("opendsaid"=>"$opendsa->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("opendsa", array("id"=>"$opendsa->id"))) {
        $result = false;
    }
    // Remove old calendar events.
    if (! $DB->delete_records('event', array('modulename' => 'opendsa', 'instance' => $opendsa->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Returns text string which is the answer that matches the id
 *
 * @global object
 * @param object $opendsa
 * @param int $id
 * @return string
 */
function opendsa_get_option_text($opendsa, $id) {
    global $DB;

    if ($result = $DB->get_record("opendsa_options", array("id" => $id))) {
        return $result->text;
    } else {
        return get_string("notanswered", "opendsa");
    }
}

/**
 * Gets a full opendsa record
 *
 * @global object
 * @param int $opendsaid
 * @return object|bool The opendsa or false
 */
function opendsa_get_opendsa($opendsaid) {
    global $DB;

    if ($opendsa = $DB->get_record("opendsa", array("id" => $opendsaid))) {
        return $opendsa;
    }
    return false;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function opendsa_get_view_actions() {
    return array('view','view all','report');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function opendsa_get_post_actions() {
    return array('choose','choose again');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the opendsa.
 *
 * @param object $mform form passed by reference
 */
function opendsa_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'opendsaheader', get_string('modulenameplural', 'opendsa'));
    $mform->addElement('advcheckbox', 'reset_opendsa', get_string('removeresponses','opendsa'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function opendsa_reset_course_form_defaults($course) {
    return array('reset_opendsa'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * opendsa responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function opendsa_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'opendsa');
    $status = array();

    if (!empty($data->reset_opendsa)) {
        $opendsassql = "SELECT ch.id
                       FROM {opendsa} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('opendsa_answers', "opendsaid IN ($opendsassql)", array($data->courseid));
        $status[] = array('component'=>$componentstr, 'item'=>get_string('removeresponses', 'opendsa'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates('opendsa', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $opendsa
 * @param object $cm
 * @param int $groupmode
 * @param bool $onlyactive Whether to get response data for active users only.
 * @return array
 */
function opendsa_get_response_data($opendsa, $cm, $groupmode, $onlyactive) {
    global $CFG, $USER, $DB;

    $context = context_module::instance($cm->id);

/// Get the current group
    if ($groupmode > 0) {
        $currentgroup = groups_get_activity_group($cm);
    } else {
        $currentgroup = 0;
    }

/// Initialise the returned array, which is a matrix:  $allresponses[responseid][userid] = responseobject
    $allresponses = array();

/// First get all the users who have access here
/// To start with we assume they are all "unanswered" then move them later
    $extrafields = get_extra_user_fields($context);
    $allresponses[0] = get_enrolled_users($context, 'mod/opendsa:choose', $currentgroup,
            user_picture::fields('u', $extrafields), null, 0, 0, $onlyactive);

/// Get all the recorded responses for this opendsa
    //$rawresponses = $DB->get_records('opendsa_answers', array('opendsaid' => $opendsa->id));

/// Use the responses to move users into the correct column
/*
    if ($rawresponses) {
        $answeredusers = array();
        foreach ($rawresponses as $response) {
            if (isset($allresponses[0][$response->userid])) {   // This person is enrolled and in correct group
                $allresponses[0][$response->userid]->timemodified = $response->timemodified;
                $allresponses[$response->optionid][$response->userid] = clone($allresponses[0][$response->userid]);
                $allresponses[$response->optionid][$response->userid]->answerid = $response->id;
                $answeredusers[] = $response->userid;
            }
        }
        foreach ($answeredusers as $answereduser) {
            unset($allresponses[0][$answereduser]);
        }
    } */

    return $allresponses;
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function opendsa_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $opendsanode The node to add module settings to
 */
function opendsa_extend_settings_navigation(settings_navigation $settings, navigation_node $opendsanode) {
    global $PAGE;

    if (has_capability('mod/opendsa:readresponses', $PAGE->cm->context)) {

        $groupmode = groups_get_activity_groupmode($PAGE->cm);
        if ($groupmode) {
            groups_get_activity_group($PAGE->cm, true);
        }

        $opendsa = opendsa_get_opendsa($PAGE->cm->instance);

        // Check if we want to include responses from inactive users.
        $onlyactive = $opendsa->includeinactive ? false : true;

        // Big function, approx 6 SQL calls per user.
        $allresponses = opendsa_get_response_data($opendsa, $PAGE->cm, $groupmode, $onlyactive);

        $allusers = [];
        foreach($allresponses as $optionid => $userlist) {
            if ($optionid) {
                $allusers = array_merge($allusers, array_keys($userlist));
            }
        }
        $responsecount = count(array_unique($allusers));
        $opendsanode->add(get_string("viewallresponses", "opendsa", $responsecount), new moodle_url('/mod/opendsa/report.php', array('id'=>$PAGE->cm->id)));
    }
}

/**
 * Obtains the automatic completion state for this opendsa based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function opendsa_get_completion_state($course, $cm, $userid, $type) {
    global $CFG,$DB;

    // Get opendsa details
    $opendsa = $DB->get_record('opendsa', array('id'=>$cm->instance), '*',
            MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false
    if($opendsa->completionsubmit) {
        return $DB->record_exists('opendsa_answers', array(
                'opendsaid'=>$opendsa->id, 'userid'=>$userid));
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function opendsa_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-opendsa-*'=>get_string('page-mod-opendsa-x', 'opendsa'));
    return $module_pagetype;
}

/**
 * @deprecated since Moodle 3.3, when the block_course_overview block was removed.
 */
function opendsa_print_overview() {
    throw new coding_exception('opendsa_print_overview() can not be used any more and is obsolete.');
}


/**
 * Get responses of a given user on a given opendsa.
 *
 * @param stdClass $opendsa OpenDSA record
 * @param int $userid User id
 * @return array of opendsa answers records
 * @since  Moodle 3.6
 */
function opendsa_get_user_response($opendsa, $userid) {
    global $DB;
    return $DB->get_records('opendsa_answers', array('opendsaid' => $opendsa->id, 'userid' => $userid), 'optionid');
}

/**
 * Get my responses on a given opendsa.
 *
 * @param stdClass $opendsa OpenDSA record
 * @return array of opendsa answers records
 * @since  Moodle 3.0
 */
function opendsa_get_my_response($opendsa) {
    global $USER;
    return opendsa_get_user_response($opendsa, $USER->id);
}


/**
 * Get all the responses on a given opendsa.
 *
 * @param stdClass $opendsa OpenDSA record
 * @return array of opendsa answers records
 * @since  Moodle 3.0
 */
function opendsa_get_all_responses($opendsa) {
    global $DB;
    return $DB->get_records('opendsa_answers', array('opendsaid' => $opendsa->id));
}


/**
 * Return true if we are allowd to view the opendsa results.
 *
 * @param stdClass $opendsa OpenDSA record
 * @param rows|null $current my opendsa responses
 * @param bool|null $opendsaopen if the opendsa is open
 * @return bool true if we can view the results, false otherwise.
 * @since  Moodle 3.0
 */
function opendsa_can_view_results($opendsa, $current = null, $opendsaopen = null) {

    if (is_null($opendsaopen)) {
        $timenow = time();

        if ($opendsa->timeopen != 0 && $timenow < $opendsa->timeopen) {
            // If the opendsa is not available, we can't see the results.
            return false;
        }

        if ($opendsa->timeclose != 0 && $timenow > $opendsa->timeclose) {
            $opendsaopen = false;
        } else {
            $opendsaopen = true;
        }
    }
    if (empty($current)) {
        $current = opendsa_get_my_response($opendsa);
    }

    if ($opendsa->showresults == OPENDSA_SHOWRESULTS_ALWAYS or
       ($opendsa->showresults == OPENDSA_SHOWRESULTS_AFTER_ANSWER and !empty($current)) or
       ($opendsa->showresults == OPENDSA_SHOWRESULTS_AFTER_CLOSE and !$opendsaopen)) {
        return true;
    }
    return false;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $opendsa     opendsa object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function opendsa_view($opendsa, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $opendsa->id
    );

    $event = \mod_opendsa\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('opendsa', $opendsa);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if a opendsa is available for the current user.
 *
 * @param  stdClass  $opendsa            opendsa record
 * @return array                       status (available or not and possible warnings)
 */
function opendsa_get_availability_status($opendsa) {
    $available = true;
    $warnings = array();

    $timenow = time();

    if (!empty($opendsa->timeopen) && ($opendsa->timeopen > $timenow)) {
        $available = false;
        $warnings['notopenyet'] = userdate($opendsa->timeopen);
    } else if (!empty($opendsa->timeclose) && ($timenow > $opendsa->timeclose)) {
        $available = false;
        $warnings['expired'] = userdate($opendsa->timeclose);
    }
    if (!$opendsa->allowupdate && opendsa_get_my_response($opendsa)) {
        $available = false;
        $warnings['opendsasaved'] = '';
    }

    // OpenDSA is available.
    return array($available, $warnings);
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every opendsa event in the site is checked, else
 * only opendsa events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance OpenDSA module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function opendsa_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/opendsa/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('opendsa', array('id' => $instance), '*', MUST_EXIST);
        }
        opendsa_set_events($instance);
        return true;
    }

    if ($courseid) {
        if (! $opendsas = $DB->get_records("opendsa", array("course" => $courseid))) {
            return true;
        }
    } else {
        if (! $opendsas = $DB->get_records("opendsa")) {
            return true;
        }
    }

    foreach ($opendsas as $opendsa) {
        opendsa_set_events($opendsa);
    }
    return true;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function opendsa_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB;

    $updates = new stdClass();
    $opendsa = $DB->get_record($cm->modname, array('id' => $cm->instance), '*', MUST_EXIST);
    list($available, $warnings) = opendsa_get_availability_status($opendsa);
    if (!$available) {
        return $updates;
    }

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    if (!opendsa_can_view_results($opendsa)) {
        return $updates;
    }
    // Check if there are new responses in the opendsa.
    $updates->answers = (object) array('updated' => false);
    $select = 'opendsaid = :id AND timemodified > :since';
    $params = array('id' => $opendsa->id, 'since' => $from);
    $answers = $DB->get_records_select('opendsa_answers', $select, $params, '', 'id');
    if (!empty($answers)) {
        $updates->answers->updated = true;
        $updates->answers->itemids = array_keys($answers);
    }

    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_opendsa_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory,
                                                       int $userid = 0) {
    global $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['opendsa'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    $now = time();

    if (!empty($cm->customdata['timeclose']) && $cm->customdata['timeclose'] < $now) {
        // The opendsa has closed so the user can no longer submit anything.
        return null;
    }

    // The opendsa is actionable if we don't have a start time or the start time is
    // in the past.
    $actionable = (empty($cm->customdata['timeopen']) || $cm->customdata['timeopen'] <= $now);

    if ($actionable && opendsa_get_user_response((object)['id' => $event->instance], $userid)) {
        // There is no action if the user has already submitted their opendsa.
        return null;
    }

    return $factory->create_instance(
        get_string('viewopendsas', 'opendsa'),
        new \moodle_url('/mod/opendsa/view.php', array('id' => $cm->id)),
        1,
        $actionable
    );
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The date must be after this date'],
 *     [1506741172, 'The date must be before this date']
 * ]
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $opendsa The module instance to get the range from
 */
function mod_opendsa_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $opendsa) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == OPENDSA_EVENT_TYPE_OPEN) {
        if (!empty($opendsa->timeclose)) {
            $maxdate = [
                $opendsa->timeclose,
                get_string('openafterclose', 'opendsa')
            ];
        }
    } else if ($event->eventtype == OPENDSA_EVENT_TYPE_CLOSE) {
        if (!empty($opendsa->timeopen)) {
            $mindate = [
                $opendsa->timeopen,
                get_string('closebeforeopen', 'opendsa')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the opendsa module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the opendsa instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $opendsa The module instance to get the range from
 */
function mod_opendsa_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $opendsa) {
    global $DB;

    if (!in_array($event->eventtype, [OPENDSA_EVENT_TYPE_OPEN, OPENDSA_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    // Something weird going on. The event is for a different module so
    // we should ignore it.
    if ($modulename != 'opendsa') {
        return;
    }

    if ($opendsa->id != $instanceid) {
        return;
    }

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == OPENDSA_EVENT_TYPE_OPEN) {
        // If the event is for the opendsa activity opening then we should
        // set the start time of the opendsa activity to be the new start
        // time of the event.
        if ($opendsa->timeopen != $event->timestart) {
            $opendsa->timeopen = $event->timestart;
            $modified = true;
        }
    } else if ($event->eventtype == OPENDSA_EVENT_TYPE_CLOSE) {
        // If the event is for the opendsa activity closing then we should
        // set the end time of the opendsa activity to be the new start
        // time of the event.
        if ($opendsa->timeclose != $event->timestart) {
            $opendsa->timeclose = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $opendsa->timemodified = time();
        // Persist the instance changes.
        $DB->update_record('opendsa', $opendsa);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_opendsa_get_fontawesome_icon_map() {
    return [
        'mod_opendsa:row' => 'fa-info',
        'mod_opendsa:column' => 'fa-columns',
    ];
}

/**
 * Add a get_coursemodule_info function in case any opendsa type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function opendsa_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionsubmit, timeopen, timeclose';
    if (!$opendsa = $DB->get_record('opendsa', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $opendsa->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('opendsa', $opendsa, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $opendsa->completionsubmit;
    }
    // Populate some other values that can be used in calendar or on dashboard.
    if ($opendsa->timeopen) {
        $result->customdata['timeopen'] = $opendsa->timeopen;
    }
    if ($opendsa->timeclose) {
        $result->customdata['timeclose'] = $opendsa->timeclose;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_opendsa_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionsubmit', 'opendsa');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}
