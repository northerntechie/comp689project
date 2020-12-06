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
 * Local library file for opendsa_activity.  These are non-standard functions that are used
 * only by opendsa_activity.
 *
 * @package mod_opendsa_activity
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

/** Make sure this isn't being directly accessed */
defined('MOODLE_INTERNAL') || die();

/** Include the files that are required by this module */
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/opendsa_activity/lib.php');
require_once($CFG->libdir . '/filelib.php');

/** This page */
define('OPENDSA_ACTIVITY_THISPAGE', 0);
/** Next page -> any page not seen before */
define("OPENDSA_ACTIVITY_UNSEENPAGE", 1);
/** Next page -> any page not answered correctly */
define("OPENDSA_ACTIVITY_UNANSWEREDPAGE", 2);
/** Jump to Next Page */
define("OPENDSA_ACTIVITY_NEXTPAGE", -1);
/** End of OPENDSA_ACTIVITY */
define("OPENDSA_ACTIVITY_EOL", -9);
/** Jump to an unseen page within a branch and end of branch or end of OPENDSA_ACTIVITY */
define("OPENDSA_ACTIVITY_UNSEENBRANCHPAGE", -50);
/** Jump to Previous Page */
define("OPENDSA_ACTIVITY_PREVIOUSPAGE", -40);
/** Jump to a random page within a branch and end of branch or end of OPENDSA_ACTIVITY */
define("OPENDSA_ACTIVITY_RANDOMPAGE", -60);
/** Jump to a random Branch */
define("OPENDSA_ACTIVITY_RANDOMBRANCH", -70);
/** Cluster Jump */
define("OPENDSA_ACTIVITY_CLUSTERJUMP", -80);
/** Undefined */
define("OPENDSA_ACTIVITY_UNDEFINED", -99);

/** OPENDSA_ACTIVITY_MAX_EVENT_LENGTH = 432000 ; 5 days maximum */
define("OPENDSA_ACTIVITYA_ACTIVITY_MAX_EVENT_LENGTH", "432000");

/** Answer format is HTML */
define("OPENDSA_ACTIVITY_ANSWER_HTML", "HTML");

/** Placeholder answer for all other answers. */
define("OPENDSA_ACTIVITY_OTHER_ANSWERS", "@#wronganswer#@");

//////////////////////////////////////////////////////////////////////////////////////
/// Any other opendsa_ctivity functions go here.  Each of them must have a name that
/// starts with opendsa_activity_

/**
 * Checks to see if a OPENDSA_ACTIVITY_CLUSTERJUMP or
 * a OPENDSA_ACTIVITY_UNSEENBRANCHPAGE is used in a opends_activity.
 *
 * This function is only executed when a teacher is
 * checking the navigation for a opendsa_activity.
 *
 * @param stdClass $opendsa_activity Id of the opendsa_activity that is to be checked.
 * @return boolean True or false.
 **/
function opendsa_activity_display_teacher_warning($opendsa_activity) {
    global $DB;

    // get all of the opendsa_activity answers
    $params = array ("opendsa_activity_id" => $opendsa_activity->id);
    if (!$opendsa_activityanswers = $DB->get_records_select("opendsa_activity_answers", "opendsa_activity_id = :opendsa_activity_id", $params)) {
        // no answers, then not using cluster or unseen
        return false;
    }
    // just check for the first one that fulfills the requirements
    foreach ($opendsa_activityanswers as $opendsa_activityanswer) {
        if ($opendsa_activityanswer->jumpto == opendsa_activity_CLUSTERJUMP || $opendsa_activityanswer->jumpto == opendsa_activity_UNSEENBRANCHPAGE) {
            return true;
        }
    }

    // if no answers use either of the two jumps
    return false;
}

/**
 * Interprets the OPENDSA_ACTIVITY_UNSEENBRANCHPAGE jump.
 *
 * will return the pageid of a random unseen page that is within a branch
 *
 * @param opendsa_activity $opendsa_activity
 * @param int $userid Id of the user.
 * @param int $pageid Id of the page from which we are jumping.
 * @return int Id of the next page.
 **/
function opendsa_activity_unseen_question_jump($opendsa_activitya_activity, $user, $pageid) {
    global $DB;

    // get the number of retakes
    if (!$retakes = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id"=>$opendsa_activity->id, "userid"=>$user))) {
        $retakes = 0;
    }

    // get all the opendsa_activity_attempts aka what the user has seen
    if ($viewedpages = $DB->get_records("opendsa_activity_attempts", array("opendsa_activity_id"=>$opendsa_activity->id, "userid"=>$user, "retry"=>$retakes), "timeseen DESC")) {
        foreach($viewedpages as $viewed) {
            $seenpages[] = $viewed->pageid;
        }
    } else {
        $seenpages = array();
    }

    // get the opendsa_activity pages
    $opendsa_activitypages = $opendsa_activity->load_all_pages();

    if ($pageid == opendsa_activity_UNSEENBRANCHPAGE) {  // this only happens when a student leaves in the middle of an unseen question within a branch series
        $pageid = $seenpages[0];  // just change the pageid to the last page viewed inside the branch table
    }

    // go up the pages till branch table
    while ($pageid != 0) { // this condition should never be satisfied... only happens if there are no branch tables above this page
        if ($opendsa_activitypages[$pageid]->qtype == opendsa_activity_PAGE_BRANCHTABLE) {
            break;
        }
        $pageid = $opendsa_activitypages[$pageid]->prevpageid;
    }

    $pagesinbranch = $opendsa_activity->get_sub_pages_of($pageid, array(opendsa_activity_PAGE_BRANCHTABLE, opendsa_activity_PAGE_ENDOFBRANCH));

    // this foreach loop stores all the pages that are within the branch table but are not in the $seenpages array
    $unseen = array();
    foreach($pagesinbranch as $page) {
        if (!in_array($page->id, $seenpages)) {
            $unseen[] = $page->id;
        }
    }

    if(count($unseen) == 0) {
        if(isset($pagesinbranch)) {
            $temp = end($pagesinbranch);
            $nextpage = $temp->nextpageid; // they have seen all the pages in the branch, so go to EOB/next branch table/EOL
        } else {
            // there are no pages inside the branch, so return the next page
            $nextpage = $opendsa_activitypages[$pageid]->nextpageid;
        }
        if ($nextpage == 0) {
            return OPENDSA_ACTIVITY_EOL;
        } else {
            return $nextpage;
        }
    } else {
        return $unseen[rand(0, count($unseen)-1)];  // returns a random page id for the next page
    }
}

/**
 * Handles the unseen branch table jump.
 *
 * @param opendsa_activity $opendsa_activity
 * @param int $userid User id.
 * @return int Will return the page id of a branch table or end of opendsa_activity
 **/
function opendsa_activity_unseen_branch_jump($opendsa_activitya_activity, $userid) {
    global $DB;

    if (!$retakes = $DB->count_records("opendsa_activitya_activity_grades", array("opendsa_activopendsa_acopendsa_activity"=>$opendsa_activity->id, "userid"=>$userid))) {
        $retakes = 0;
    }

    if (!$seenbranches = $opendsa_activity->get_content_pages_viewed($retakes, $userid, 'timeseen DESC')) {
        print_error('cannotfindrecords', 'opendsa_activity');
    }

    // get the opendsa_activity pages
    $opendsa_activitypages = $opendsa_activity->load_all_pages();

    // this loads all the viewed branch tables into $seen until it finds the branch table with the flag
    // which is the branch table that starts the unseenbranch function
    $seen = array();
    foreach ($seenbranches as $seenbranch) {
        if (!$seenbranch->flag) {
            $seen[$seenbranch->pageid] = $seenbranch->pageid;
        } else {
            $start = $seenbranch->pageid;
            break;
        }
    }
    // this function searches through the opendsa_activity pages to find all the branch tables
    // that follow the flagged branch table
    $pageid = $opendsa_activitya_activitypages[$start]->nextpageid; // move down from the flagged branch table
    $branchtables = array();
    while ($pageid != 0) {  // grab all of the branch table till eol
        if ($opendsa_activitya_activitypages[$pageid]->qtype == opendsa_activity_PAGE_BRANCHTABLE) {
            $branchtables[] = $opendsa_activitypages[$pageid]->id;
        }
        $pageid = $opendsa_activitypages[$pageid]->nextpageid;
    }
    $unseen = array();
    foreach ($branchtables as $branchtable) {
        // load all of the unseen branch tables into unseen
        if (!array_key_exists($branchtable, $seen)) {
            $unseen[] = $branchtable;
        }
    }
    if (count($unseen) > 0) {
        return $unseen[rand(0, count($unseen)-1)];  // returns a random page id for the next page
    } else {
        return opendsa_activity_EOL;  // has viewed all of the branch tables
    }
}

/**
 * Handles the random jump between a branch table and end of branch or end of opendsa_activity (OPENDSA_ACTIVITY_RANDOMPAGE).
 *
 * @param opendsa_activity $opendsa_activity
 * @param int $pageid The id of the page that we are jumping from (?)
 * @return int The pageid of a random page that is within a branch table
 **/
function opendsa_activity_random_question_jump($opendsa_activity, $pageid) {
    global $DB;

    // get the opendsa_activity pages
    $params = array ("opendsa_activity_id" => $opendsa_activity->id);
    if (!$opendsa_activitypages = $DB->get_records_select("opendsa_activity_pages", "opendsa_activity_id = :opendsa_activity_id", $params)) {
        print_error('cannotfindpages', 'opendsa_activity');
    }

    // go up the pages till branch table
    while ($pageid != 0) { // this condition should never be satisfied... only happens if there are no branch tables above this page

        if ($opendsa_activitypages[$pageid]->qtype == OPENDSA_ACTIVITY_PAGE_BRANCHTABLE) {
            break;
        }
        $pageid = $opendsa_activitypages[$pageid]->prevpageid;
    }

    // get the pages within the branch
    $pagesinbranch = $opendsa_activity->get_sub_pages_of($pageid, array(OPENDSA_ACTIVITY_PAGE_BRANCHTABLE, OPENDSA_ACTIVITY_PAGE_ENDOFBRANCH));

    if(count($pagesinbranch) == 0) {
        // there are no pages inside the branch, so return the next page
        return $opendsa_activitypages[$pageid]->nextpageid;
    } else {
        return $pagesinbranch[rand(0, count($pagesinbranch)-1)]->id;  // returns a random page id for the next page
    }
}

/**
 * Calculates a user's grade for a opendsa_activity.
 *
 * @param object $opendsa_activity The opendsa_activity that the user is taking.
 * @param int $retries The attempt number.
 * @param int $userid Id of the user (optional, default current user).
 * @return object { nquestions => number of questions answered
                    attempts => number of question attempts
                    total => max points possible
                    earned => points earned by student
                    grade => calculated percentage grade
                    nmanual => number of manually graded questions
                    manualpoints => point value for manually graded questions }
 */
function opendsa_activity_grade($opendsa_activity, $ntries, $userid = 0) {
    global $USER, $DB;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    // Zero out everything
    $ncorrect     = 0;
    $nviewed      = 0;
    $score        = 0;
    $nmanual      = 0;
    $manualpoints = 0;
    $thegrade     = 0;
    $nquestions   = 0;
    $total        = 0;
    $earned       = 0;

    $params = array ("opendsa_activity_id" => $opendsa_activity->id, "userid" => $userid, "retry" => $ntries);
    if ($useranswers = $DB->get_records_select("opendsa_activity_attempts",  "opendsa_activity_id = :opendsa_activity_id AND
            userid = :userid AND retry = :retry", $params, "timeseen")) {
        // group each try with its page
        $attemptset = array();
        foreach ($useranswers as $useranswer) {
            $attemptset[$useranswer->pageid][] = $useranswer;
        }

        // Drop all attempts that go beyond max attempts for the opendsa_activity
        foreach ($attemptset as $key => $set) {
            $attemptset[$key] = array_slice($set, 0, $opendsa_activity->maxattempts);
        }

        // get only the pages and their answers that the user answered
        list($usql, $parameters) = $DB->get_in_or_equal(array_keys($attemptset));
        array_unshift($parameters, $opendsa_activity->id);
        $pages = $DB->get_records_select("opendsa_activity_pages", "opendsa_activity_id = ? AND id $usql", $parameters);
        $answers = $DB->get_records_select("opendsa_activity_answers", "opendsa_activity_id = ? AND pageid $usql", $parameters);

        // Number of pages answered
        $nquestions = count($pages);

        foreach ($attemptset as $attempts) {
            $page = opendsa_activity_page::load($pages[end($attempts)->pageid], $opendsa_activity);
            if ($opendsa_activity->custom) {
                $attempt = end($attempts);
                // If essay question, handle it, otherwise add to score
                if ($page->requires_manual_grading()) {
                    $useranswerobj = unserialize($attempt->useranswer);
                    if (isset($useranswerobj->score)) {
                        $earned += $useranswerobj->score;
                    }
                    $nmanual++;
                    $manualpoints += $answers[$attempt->answerid]->score;
                } else if (!empty($attempt->answerid)) {
                    $earned += $page->earned_score($answers, $attempt);
                }
            } else {
                foreach ($attempts as $attempt) {
                    $earned += $attempt->correct;
                }
                $attempt = end($attempts); // doesn't matter which one
                // If essay question, increase numbers
                if ($page->requires_manual_grading()) {
                    $nmanual++;
                    $manualpoints++;
                }
            }
            // Number of times answered
            $nviewed += count($attempts);
        }

        if ($opendsa_activity->custom) {
            $bestscores = array();
            // Find the highest possible score per page to get our total
            foreach ($answers as $answer) {
                if(!isset($bestscores[$answer->pageid])) {
                    $bestscores[$answer->pageid] = $answer->score;
                } else if ($bestscores[$answer->pageid] < $answer->score) {
                    $bestscores[$answer->pageid] = $answer->score;
                }
            }
            $total = array_sum($bestscores);
        } else {
            // Check to make sure the student has answered the minimum questions
            if ($opendsa_activity->minquestions and $nquestions < $opendsa_activity->minquestions) {
                // Nope, increase number viewed by the amount of unanswered questions
                $total =  $nviewed + ($opendsa_activity->minquestions - $nquestions);
            } else {
                $total = $nviewed;
            }
        }
    }

    if ($total) { // not zero
        $thegrade = round(100 * $earned / $total, 5);
    }

    // Build the grade information object
    $gradeinfo               = new stdClass;
    $gradeinfo->nquestions   = $nquestions;
    $gradeinfo->attempts     = $nviewed;
    $gradeinfo->total        = $total;
    $gradeinfo->earned       = $earned;
    $gradeinfo->grade        = $thegrade;
    $gradeinfo->nmanual      = $nmanual;
    $gradeinfo->manualpoints = $manualpoints;

    return $gradeinfo;
}

/**
 * Determines if a user can view the left menu.  The determining factor
 * is whether a user has a grade greater than or equal to the opendsa_activity setting
 * of displayleftif
 *
 * @param object $opendsa_activity Lesson object of the current opendsa_activity
 * @return boolean 0 if the user cannot see, or $opendsa_activity->displayleft to keep displayleft unchanged
 **/
function opendsa_activity_displayleftif($opendsa_activity) {
    global $CFG, $USER, $DB;

    if (!empty($opendsa_activity->displayleftif)) {
        // get the current user's max grade for this opendsa_activity
        $params = array ("userid" => $USER->id, "opendsa_activity_id" => $opendsa_activity->id);
        if ($maxgrade = $DB->get_record_sql('SELECT userid, MAX(grade) AS maxgrade FROM {opendsa_activity_grades} WHERE userid = :userid AND opendsa_activity_id = :opendsa_activity_id GROUP BY userid', $params)) {
            if ($maxgrade->maxgrade < $opendsa_activity->displayleftif) {
                return 0;  // turn off the displayleft
            }
        } else {
            return 0; // no grades
        }
    }

    // if we get to here, keep the original state of displayleft opendsa_activity setting
    return $opendsa_activity->displayleft;
}

/**
 *
 * @param $cm
 * @param $opendsa_activity
 * @param $page
 * @return unknown_type
 */
function opendsa_activity_add_fake_blocks($page, $cm, $opendsa_activity, $timer = null) {
    $bc = opendsa_activity_menu_block_contents($cm->id, $opendsa_activity);
    if (!empty($bc)) {
        $regions = $page->blocks->get_regions();
        $firstregion = reset($regions);
        $page->blocks->add_fake_block($bc, $firstregion);
    }

    $bc = opendsa_activity_mediafile_block_contents($cm->id, $opendsa_activity);
    if (!empty($bc)) {
        $page->blocks->add_fake_block($bc, $page->blocks->get_default_region());
    }

    if (!empty($timer)) {
        $bc = opendsa_activity_clock_block_contents($cm->id, $opendsa_activity, $timer, $page);
        if (!empty($bc)) {
            $page->blocks->add_fake_block($bc, $page->blocks->get_default_region());
        }
    }
}

/**
 * If there is a media file associated with this
 * opendsa_activity, return a block_contents that displays it.
 *
 * @param int $cmid Course Module ID for this opendsa_activity
 * @param object $opendsa_activity Full opendsa_activity record object
 * @return block_contents
 **/
function opendsa_activity_mediafile_block_contents($cmid, $opendsa_activity) {
    global $OUTPUT;
    if (empty($opendsa_activity->mediafile)) {
        return null;
    }

    $options = array();
    $options['menubar'] = 0;
    $options['location'] = 0;
    $options['left'] = 5;
    $options['top'] = 5;
    $options['scrollbars'] = 1;
    $options['resizable'] = 1;
    $options['width'] = $opendsa_activity->mediawidth;
    $options['height'] = $opendsa_activity->mediaheight;

    $link = new moodle_url('/mod/opendsa_activity/mediafile.php?id='.$cmid);
    $action = new popup_action('click', $link, 'opendsa_activitymediafile', $options);
    $content = $OUTPUT->action_link($link, get_string('mediafilepopup', 'opendsa_activity'), $action, array('title'=>get_string('mediafilepopup', 'opendsa_activity')));

    $bc = new block_contents();
    $bc->title = get_string('linkedmedia', 'opendsa_activity');
    $bc->attributes['class'] = 'mediafile block';
    $bc->content = $content;

    return $bc;
}

/**
 * If a timed opendsa_activity and not a teacher, then
 * return a block_contents containing the clock.
 *
 * @param int $cmid Course Module ID for this opendsa_activity
 * @param object $opendsa_activity Full opendsa_activity record object
 * @param object $timer Full timer record object
 * @return block_contents
 **/
function opendsa_activity_clock_block_contents($cmid, $opendsa_activity, $timer, $page) {
    // Display for timed opendsa_activitys and for students only
    $context = context_module::instance($cmid);
    if ($opendsa_activity->timelimit == 0 || has_capability('mod/opendsa_activity:manage', $context)) {
        return null;
    }

    $content = '<div id="opendsa_activity-timer">';
    $content .=  $opendsa_activity->time_remaining($timer->starttime);
    $content .= '</div>';

    $clocksettings = array('starttime' => $timer->starttime, 'servertime' => time(), 'testlength' => $opendsa_activity->timelimit);
    $page->requires->data_for_js('clocksettings', $clocksettings, true);
    $page->requires->strings_for_js(array('timeisup'), 'opendsa_activity');
    $page->requires->js('/mod/opendsa_activity/timer.js');
    $page->requires->js_init_call('show_clock');

    $bc = new block_contents();
    $bc->title = get_string('timeremaining', 'opendsa_activity');
    $bc->attributes['class'] = 'clock block';
    $bc->content = $content;

    return $bc;
}

/**
 * If left menu is turned on, then this will
 * print the menu in a block
 *
 * @param int $cmid Course Module ID for this opendsa_activity
 * @param opendsa_activity $opendsa_activity Full opendsa_activity record object
 * @return void
 **/
function opendsa_activity_menu_block_contents($cmid, $opendsa_activity) {
    global $CFG, $DB;

    if (!$opendsa_activity->displayleft) {
        return null;
    }

    $pages = $opendsa_activity->load_all_pages();
    foreach ($pages as $page) {
        if ((int)$page->prevpageid === 0) {
            $pageid = $page->id;
            break;
        }
    }
    $currentpageid = optional_param('pageid', $pageid, PARAM_INT);

    if (!$pageid || !$pages) {
        return null;
    }

    $content = '<a href="#maincontent" class="accesshide">' .
        get_string('skip', 'opendsa_activity') .
        "</a>\n<div class=\"menuwrapper\">\n<ul>\n";

    while ($pageid != 0) {
        $page = $pages[$pageid];

        // Only process branch tables with display turned on
        if ($page->displayinmenublock && $page->display) {
            if ($page->id == $currentpageid) {
                $content .= '<li class="selected">'.format_string($page->title,true)."</li>\n";
            } else {
                $content .= "<li class=\"notselected\"><a href=\"$CFG->wwwroot/mod/opendsa_activity/view.php?id=$cmid&amp;pageid=$page->id\">".format_string($page->title,true)."</a></li>\n";
            }

        }
        $pageid = $page->nextpageid;
    }
    $content .= "</ul>\n</div>\n";

    $bc = new block_contents();
    $bc->title = get_string('opendsa_activitymenu', 'opendsa_activity');
    $bc->attributes['class'] = 'menu block';
    $bc->content = $content;

    return $bc;
}

/**
 * Adds header buttons to the page for the opendsa_activity
 *
 * @param object $cm
 * @param object $context
 * @param bool $extraeditbuttons
 * @param int $opendsa_activitypageid
 */
function opendsa_activity_add_header_buttons($cm, $context, $extraeditbuttons=false, $opendsa_activitypageid=null) {
    global $CFG, $PAGE, $OUTPUT;
    if (has_capability('mod/opendsa_activity:edit', $context) && $extraeditbuttons) {
        if ($opendsa_activitypageid === null) {
            print_error('invalidpageid', 'opendsa_activity');
        }
        if (!empty($opendsa_activitypageid) && $opendsa_activitypageid != OPENDSA_ACTIVITY_EOL) {
            $url = new moodle_url('/mod/opendsa_activity/editpage.php', array(
                'id'       => $cm->id,
                'pageid'   => $opendsa_activitypageid,
                'edit'     => 1,
                'returnto' => $PAGE->url->out_as_local_url(false)
            ));
            $PAGE->set_button($OUTPUT->single_button($url, get_string('editpagecontent', 'opendsa_activity')));
        }
    }
}

/**
 * This is a function used to detect media types and generate html code.
 *
 * @global object $CFG
 * @global object $PAGE
 * @param object $opendsa_activity
 * @param object $context
 * @return string $code the html code of media
 */
function opendsa_activity_get_media_html($opendsa_activity, $context) {
    global $CFG, $PAGE, $OUTPUT;
    require_once("$CFG->libdir/resourcelib.php");

    // get the media file link
    if (strpos($opendsa_activity->mediafile, '://') !== false) {
        $url = new moodle_url($opendsa_activity->mediafile);
    } else {
        // the timemodified is used to prevent caching problems, instead of '/' we should better read from files table and use sortorder
        $url = moodle_url::make_pluginfile_url($context->id, 'mod_opendsa_activity', 'mediafile', $opendsa_activity->timemodified, '/', ltrim($opendsa_activity->mediafile, '/'));
    }
    $title = $opendsa_activity->mediafile;

    $clicktoopen = html_writer::link($url, get_string('download'));

    $mimetype = resourcelib_guess_url_mimetype($url);

    $extension = resourcelib_get_extension($url->out(false));

    $mediamanager = core_media_manager::instance($PAGE);
    $embedoptions = array(
        core_media_manager::OPTION_TRUSTED => true,
        core_media_manager::OPTION_BLOCK => true
    );

    // find the correct type and print it out
    if (in_array($mimetype, array('image/gif','image/jpeg','image/png'))) {  // It's an image
        $code = resourcelib_embed_image($url, $title);

    } else if ($mediamanager->can_embed_url($url, $embedoptions)) {
        // Media (audio/video) file.
        $code = $mediamanager->embed_url($url, $title, 0, 0, $embedoptions);

    } else {
        // anything else - just try object tag enlarged as much as possible
        $code = resourcelib_embed_general($url, $title, $clicktoopen, $mimetype);
    }

    return $code;
}

/**
 * Logic to happen when a/some group(s) has/have been deleted in a course.
 *
 * @param int $courseid The course ID.
 * @param int $groupid The group id if it is known
 * @return void
 */
function opendsa_activity_process_group_deleted_in_course($courseid, $groupid = null) {
    global $DB;

    $params = array('courseid' => $courseid);
    if ($groupid) {
        $params['groupid'] = $groupid;
        // We just update the group that was deleted.
        $sql = "SELECT o.id, o.opendsa_activity_id
                  FROM {opendsa_activity_overrides} o
                  JOIN {opendsa_activity} opendsa_activity ON opendsa_activity.id = o.opendsa_activity_id
                 WHERE opendsa_activity.course = :courseid
                   AND o.groupid = :groupid";
    } else {
        // No groupid, we update all orphaned group overrides for all opendsa_activitys in course.
        $sql = "SELECT o.id, o.opendsa_activity_id
                  FROM {opendsa_activity_overrides} o
                  JOIN {opendsa_activity} opendsa_activity ON opendsa_activity.id = o.opendsa_activity_id
             LEFT JOIN {groups} grp ON grp.id = o.groupid
                 WHERE opendsa_activity.course = :courseid
                   AND o.groupid IS NOT NULL
                   AND grp.id IS NULL";
    }
    $records = $DB->get_records_sql_menu($sql, $params);
    if (!$records) {
        return; // Nothing to do.
    }
    $DB->delete_records_list('opendsa_activity_overrides', 'id', array_keys($records));
}

/**
 * Return the overview report table and data.
 *
 * @param  opendsa_activity $opendsa_activity       opendsa_activity instance
 * @param  mixed $currentgroup  false if not group used, 0 for all groups, group id (int) to filter by that groups
 * @return mixed false if there is no information otherwise html_table and stdClass with the table and data
 * @since  Moodle 3.3
 */
function opendsa_activity_get_overview_report_table_and_data(opendsa_activity $opendsa_activity, $currentgroup) {
    global $DB, $CFG, $OUTPUT;
    require_once($CFG->dirroot . '/mod/opendsa_activity/pagetypes/branchtable.php');

    $context = $opendsa_activity->context;
    $cm = $opendsa_activity->cm;
    // Count the number of branch and question pages in this opendsa_activity.
    $branchcount = $DB->count_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id, 'qtype' => OPENDSA_ACTIVITY_PAGE_BRANCHTABLE));
    $questioncount = ($DB->count_records('opendsa_activity_pages', array('opendsa_activity_id' => $opendsa_activity->id)) - $branchcount);

    // Only load students if there attempts for this opendsa_activity.
    $attempts = $DB->record_exists('opendsa_activity_attempts', array('opendsa_activity_id' => $opendsa_activity->id));
    $branches = $DB->record_exists('opendsa_activity_branch', array('opendsa_activity_id' => $opendsa_activity->id));
    $timer = $DB->record_exists('opendsa_activity_timer', array('opendsa_activity_id' => $opendsa_activity->id));
    if ($attempts or $branches or $timer) {
        list($esql, $params) = get_enrolled_sql($context, '', $currentgroup, true);
        list($sort, $sortparams) = users_order_by_sql('u');

        $extrafields = get_extra_user_fields($context);

        $params['a1opendsa_activity_id'] = $opendsa_activity->id;
        $params['b1opendsa_activity_id'] = $opendsa_activity->id;
        $params['c1opendsa_activity_id'] = $opendsa_activity->id;
        $ufields = user_picture::fields('u', $extrafields);
        $sql = "SELECT DISTINCT $ufields
                FROM {user} u
                JOIN (
                    SELECT userid, opendsa_activity_id FROM {opendsa_activity_attempts} a1
                    WHERE a1.opendsa_activity_id = :a1opendsa_activity_id
                        UNION
                    SELECT userid, opendsa_activity_id FROM {opendsa_activity_branch} b1
                    WHERE b1.opendsa_activity_id = :b1opendsa_activity_id
                        UNION
                    SELECT userid, opendsa_activity_id FROM {opendsa_activity_timer} c1
                    WHERE c1.opendsa_activity_id = :c1opendsa_activity_id
                    ) a ON u.id = a.userid
                JOIN ($esql) ue ON ue.id = a.userid
                ORDER BY $sort";

        $students = $DB->get_recordset_sql($sql, $params);
        if (!$students->valid()) {
            $students->close();
            return array(false, false);
        }
    } else {
        return array(false, false);
    }

    if (! $grades = $DB->get_records('opendsa_activity_grades', array('opendsa_activity_id' => $opendsa_activity->id), 'completed')) {
        $grades = array();
    }

    if (! $times = $DB->get_records('opendsa_activity_timer', array('opendsa_activity_id' => $opendsa_activity->id), 'starttime')) {
        $times = array();
    }

    // Build an array for output.
    $studentdata = array();

    $attempts = $DB->get_recordset('opendsa_activity_attempts', array('opendsa_activity_id' => $opendsa_activity->id), 'timeseen');
    foreach ($attempts as $attempt) {
        // if the user is not in the array or if the retry number is not in the sub array, add the data for that try.
        if (empty($studentdata[$attempt->userid]) || empty($studentdata[$attempt->userid][$attempt->retry])) {
            // restore/setup defaults
            $n = 0;
            $timestart = 0;
            $timeend = 0;
            $usergrade = null;
            $eol = 0;

            // search for the grade record for this try. if not there, the nulls defined above will be used.
            foreach($grades as $grade) {
                // check to see if the grade matches the correct user
                if ($grade->userid == $attempt->userid) {
                    // see if n is = to the retry
                    if ($n == $attempt->retry) {
                        // get grade info
                        $usergrade = round($grade->grade, 2); // round it here so we only have to do it once
                        break;
                    }
                    $n++; // if not equal, then increment n
                }
            }
            $n = 0;
            // search for the time record for this try. if not there, the nulls defined above will be used.
            foreach($times as $time) {
                // check to see if the grade matches the correct user
                if ($time->userid == $attempt->userid) {
                    // see if n is = to the retry
                    if ($n == $attempt->retry) {
                        // get grade info
                        $timeend = $time->opendsa_activitytime;
                        $timestart = $time->starttime;
                        $eol = $time->completed;
                        break;
                    }
                    $n++; // if not equal, then increment n
                }
            }

            // build up the array.
            // this array represents each student and all of their tries at the opendsa_activity
            $studentdata[$attempt->userid][$attempt->retry] = array( "timestart" => $timestart,
                                                                    "timeend" => $timeend,
                                                                    "grade" => $usergrade,
                                                                    "end" => $eol,
                                                                    "try" => $attempt->retry,
                                                                    "userid" => $attempt->userid);
        }
    }
    $attempts->close();

    $branches = $DB->get_recordset('opendsa_activity_branch', array('opendsa_activity_id' => $opendsa_activity->id), 'timeseen');
    foreach ($branches as $branch) {
        // If the user is not in the array or if the retry number is not in the sub array, add the data for that try.
        if (empty($studentdata[$branch->userid]) || empty($studentdata[$branch->userid][$branch->retry])) {
            // Restore/setup defaults.
            $n = 0;
            $timestart = 0;
            $timeend = 0;
            $usergrade = null;
            $eol = 0;
            // Search for the time record for this try. if not there, the nulls defined above will be used.
            foreach ($times as $time) {
                // Check to see if the grade matches the correct user.
                if ($time->userid == $branch->userid) {
                    // See if n is = to the retry.
                    if ($n == $branch->retry) {
                        // Get grade info.
                        $timeend = $time->opendsa_activitytime;
                        $timestart = $time->starttime;
                        $eol = $time->completed;
                        break;
                    }
                    $n++; // If not equal, then increment n.
                }
            }

            // Build up the array.
            // This array represents each student and all of their tries at the opendsa_activity.
            $studentdata[$branch->userid][$branch->retry] = array( "timestart" => $timestart,
                                                                    "timeend" => $timeend,
                                                                    "grade" => $usergrade,
                                                                    "end" => $eol,
                                                                    "try" => $branch->retry,
                                                                    "userid" => $branch->userid);
        }
    }
    $branches->close();

    // Need the same thing for timed entries that were not completed.
    foreach ($times as $time) {
        $endofopendsa_activity = $time->completed;
        // If the time start is the same with another record then we shouldn't be adding another item to this array.
        if (isset($studentdata[$time->userid])) {
            $foundmatch = false;
            $n = 0;
            foreach ($studentdata[$time->userid] as $key => $value) {
                if ($value['timestart'] == $time->starttime) {
                    // Don't add this to the array.
                    $foundmatch = true;
                    break;
                }
            }
            $n = count($studentdata[$time->userid]) + 1;
            if (!$foundmatch) {
                // Add a record.
                $studentdata[$time->userid][] = array(
                                "timestart" => $time->starttime,
                                "timeend" => $time->opendsa_activitytime,
                                "grade" => null,
                                "end" => $endofopendsa_activity,
                                "try" => $n,
                                "userid" => $time->userid
                            );
            }
        } else {
            $studentdata[$time->userid][] = array(
                                "timestart" => $time->starttime,
                                "timeend" => $time->opendsa_activitytime,
                                "grade" => null,
                                "end" => $endofopendsa_activity,
                                "try" => 0,
                                "userid" => $time->userid
                            );
        }
    }

    // To store all the data to be returned by the function.
    $data = new stdClass();

    // Determine if opendsa_activity should have a score.
    if ($branchcount > 0 AND $questioncount == 0) {
        // This opendsa_activity only contains content pages and is not graded.
        $data->opendsa_activityscored = false;
    } else {
        // This opendsa_activity is graded.
        $data->opendsa_activityscored = true;
    }
    // set all the stats variables
    $data->numofattempts = 0;
    $data->avescore      = 0;
    $data->avetime       = 0;
    $data->highscore     = null;
    $data->lowscore      = null;
    $data->hightime      = null;
    $data->lowtime       = null;
    $data->students      = array();

    $table = new html_table();

    $headers = [get_string('name')];

    foreach ($extrafields as $field) {
        $headers[] = get_user_field_name($field);
    }

    $caneditopendsa_activity = has_capability('mod/opendsa_activity:edit', $context);
    $attemptsheader = get_string('attempts', 'opendsa_activity');
    if ($caneditopendsa_activity) {
        $selectall = get_string('selectallattempts', 'opendsa_activity');
        $deselectall = get_string('deselectallattempts', 'opendsa_activity');
        // Build the select/deselect all control.
        $selectallid = 'selectall-attempts';
        $mastercheckbox = new \core\output\checkbox_toggleall('opendsa_activity-attempts', true, [
            'id' => $selectallid,
            'name' => $selectallid,
            'value' => 1,
            'label' => $selectall,
            'selectall' => $selectall,
            'deselectall' => $deselectall,
            'labelclasses' => 'form-check-label'
        ]);
        $attemptsheader = $OUTPUT->render($mastercheckbox);
    }
    $headers [] = $attemptsheader;

    // Set up the table object.
    if ($data->opendsa_activityscored) {
        $headers [] = get_string('highscore', 'opendsa_activity');
    }

    $colcount = count($headers);

    $table->head = $headers;

    $table->align = [];
    $table->align = array_pad($table->align, $colcount, 'center');
    $table->align[$colcount - 1] = 'left';

    if ($data->opendsa_activityscored) {
        $table->align[$colcount - 2] = 'left';
    }

    $table->wrap = [];
    $table->wrap = array_pad($table->wrap, $colcount, 'nowrap');

    $table->attributes['class'] = 'table table-striped';

    // print out the $studentdata array
    // going through each student that has attempted the opendsa_activity, so, each student should have something to be displayed
    foreach ($students as $student) {
        // check to see if the student has attempts to print out
        if (array_key_exists($student->id, $studentdata)) {
            // set/reset some variables
            $attempts = array();
            $dataforstudent = new stdClass;
            $dataforstudent->attempts = array();
            // gather the data for each user attempt
            $bestgrade = 0;

            // $tries holds all the tries/retries a student has done
            $tries = $studentdata[$student->id];
            $studentname = fullname($student, true);

            foreach ($tries as $try) {
                $dataforstudent->attempts[] = $try;

                // Start to build up the checkbox and link.
                $attempturlparams = [
                    'id' => $cm->id,
                    'action' => 'reportdetail',
                    'userid' => $try['userid'],
                    'try' => $try['try'],
                ];

                if ($try["grade"] !== null) { // if null then not done yet
                    // this is what the link does when the user has completed the try
                    $timetotake = $try["timeend"] - $try["timestart"];

                    if ($try["grade"] > $bestgrade) {
                        $bestgrade = $try["grade"];
                    }

                    $attemptdata = (object)[
                        'grade' => $try["grade"],
                        'timestart' => userdate($try["timestart"]),
                        'duration' => format_time($timetotake),
                    ];
                    $attemptlinkcontents = get_string('attemptinfowithgrade', 'opendsa_activity', $attemptdata);

                } else {
                    if ($try["end"]) {
                        // User finished the opendsa_activity but has no grade. (Happens when there are only content pages).
                        $timetotake = $try["timeend"] - $try["timestart"];
                        $attemptdata = (object)[
                            'timestart' => userdate($try["timestart"]),
                            'duration' => format_time($timetotake),
                        ];
                        $attemptlinkcontents = get_string('attemptinfonograde', 'opendsa_activity', $attemptdata);
                    } else {
                        // This is what the link does/looks like when the user has not completed the attempt.
                        if ($try['timestart'] !== 0) {
                            // Teacher previews do not track time spent.
                            $attemptlinkcontents = get_string("notcompletedwithdate", "opendsa_activity", userdate($try["timestart"]));
                        } else {
                            $attemptlinkcontents = get_string("notcompleted", "opendsa_activity");
                        }
                        $timetotake = null;
                    }
                }
                $attempturl = new moodle_url('/mod/opendsa_activity/report.php', $attempturlparams);
                $attemptlink = html_writer::link($attempturl, $attemptlinkcontents, ['class' => 'opendsa_activity-attempt-link']);

                if ($caneditopendsa_activity) {
                    $attemptid = 'attempt-' . $try['userid'] . '-' . $try['try'];
                    $attemptname = 'attempts[' . $try['userid'] . '][' . $try['try'] . ']';

                    $checkbox = new \core\output\checkbox_toggleall('opendsa_activity-attempts', false, [
                        'id' => $attemptid,
                        'name' => $attemptname,
                        'label' => $attemptlink
                    ]);
                    $attemptlink = $OUTPUT->render($checkbox);
                }

                // build up the attempts array
                $attempts[] = $attemptlink;

                // Run these lines for the stats only if the user finnished the opendsa_activity.
                if ($try["end"]) {
                    // User has completed the opendsa_activity.
                    $data->numofattempts++;
                    $data->avetime += $timetotake;
                    if ($timetotake > $data->hightime || $data->hightime == null) {
                        $data->hightime = $timetotake;
                    }
                    if ($timetotake < $data->lowtime || $data->lowtime == null) {
                        $data->lowtime = $timetotake;
                    }
                    if ($try["grade"] !== null) {
                        // The opendsa_activity was scored.
                        $data->avescore += $try["grade"];
                        if ($try["grade"] > $data->highscore || $data->highscore === null) {
                            $data->highscore = $try["grade"];
                        }
                        if ($try["grade"] < $data->lowscore || $data->lowscore === null) {
                            $data->lowscore = $try["grade"];
                        }

                    }
                }
            }
            // get line breaks in after each attempt
            $attempts = implode("<br />\n", $attempts);
            $row = [$studentname];

            foreach ($extrafields as $field) {
                $row[] = $student->$field;
            }

            $row[] = $attempts;

            if ($data->opendsa_activityscored) {
                // Add the grade if the opendsa_activity is graded.
                $row[] = $bestgrade."%";
            }

            $table->data[] = $row;

            // Add the student data.
            $dataforstudent->id = $student->id;
            $dataforstudent->fullname = $studentname;
            $dataforstudent->bestgrade = $bestgrade;
            $data->students[] = $dataforstudent;
        }
    }
    $students->close();
    if ($data->numofattempts > 0) {
        $data->avescore = $data->avescore / $data->numofattempts;
    }

    return array($table, $data);
}

/**
 * Return information about one user attempt (including answers)
 * @param  opendsa_activity $opendsa_activity  opendsa_activity instance
 * @param  int $userid     the user id
 * @param  int $attempt    the attempt number
 * @return array the user answers (array) and user data stats (object)
 * @since  Moodle 3.3
 */
function opendsa_activity_get_user_detailed_report_data(opendsa_activity $opendsa_activity, $userid, $attempt) {
    global $DB;

    $context = $opendsa_activity->context;
    if (!empty($userid)) {
        // Apply overrides.
        $opendsa_activity->update_effective_access($userid);
    }

    $pageid = 0;
    $opendsa_activitypages = $opendsa_activity->load_all_pages();
    foreach ($opendsa_activitypages as $opendsa_activitypage) {
        if ($opendsa_activitypage->prevpageid == 0) {
            $pageid = $opendsa_activitypage->id;
        }
    }

    // now gather the stats into an object
    $firstpageid = $pageid;
    $pagestats = array();
    while ($pageid != 0) { // EOL
        $page = $opendsa_activitypages[$pageid];
        $params = array ("opendsa_activity_id" => $opendsa_activity->id, "pageid" => $page->id);
        if ($allanswers = $DB->get_records_select("opendsa_activity_attempts", "opendsa_activity_id = :opendsa_activity_id AND pageid = :pageid", $params, "timeseen")) {
            // get them ready for processing
            $orderedanswers = array();
            foreach ($allanswers as $singleanswer) {
                // ordering them like this, will help to find the single attempt record that we want to keep.
                $orderedanswers[$singleanswer->userid][$singleanswer->retry][] = $singleanswer;
            }
            // this is foreach user and for each try for that user, keep one attempt record
            foreach ($orderedanswers as $orderedanswer) {
                foreach($orderedanswer as $tries) {
                    $page->stats($pagestats, $tries);
                }
            }
        } else {
            // no one answered yet...
        }
        //unset($orderedanswers);  initialized above now
        $pageid = $page->nextpageid;
    }

    $manager = opendsa_activity_page_type_manager::get($opendsa_activity);
    $qtypes = $manager->get_page_type_strings();

    $answerpages = array();
    $answerpage = "";
    $pageid = $firstpageid;
    // cycle through all the pages
    //  foreach page, add to the $answerpages[] array all the data that is needed
    //  from the question, the users attempt, and the statistics
    // grayout pages that the user did not answer and Branch, end of branch, cluster
    // and end of cluster pages
    while ($pageid != 0) { // EOL
        $page = $opendsa_activitypages[$pageid];
        $answerpage = new stdClass;
        // Keep the original page object.
        $answerpage->page = $page;
        $data ='';

        $answerdata = new stdClass;
        // Set some defaults for the answer data.
        $answerdata->score = null;
        $answerdata->response = null;
        $answerdata->responseformat = FORMAT_PLAIN;

        $answerpage->title = format_string($page->title);

        $options = new stdClass;
        $options->noclean = true;
        $options->overflowdiv = true;
        $options->context = $context;
        $answerpage->contents = format_text($page->contents, $page->contentsformat, $options);

        $answerpage->qtype = $qtypes[$page->qtype].$page->option_description_string();
        $answerpage->grayout = $page->grayout;
        $answerpage->context = $context;

        if (empty($userid)) {
            // there is no userid, so set these vars and display stats.
            $answerpage->grayout = 0;
            $useranswer = null;
        } elseif ($useranswers = $DB->get_records("opendsa_activity_attempts",array("opendsa_activity_id"=>$opendsa_activity->id, "userid"=>$userid, "retry"=>$attempt,"pageid"=>$page->id), "timeseen")) {
            // get the user's answer for this page
            // need to find the right one
            $i = 0;
            foreach ($useranswers as $userattempt) {
                $useranswer = $userattempt;
                $i++;
                if ($opendsa_activity->maxattempts == $i) {
                    break; // reached maxattempts, break out
                }
            }
        } else {
            // user did not answer this page, gray it out and set some nulls
            $answerpage->grayout = 1;
            $useranswer = null;
        }
        $i = 0;
        $n = 0;
        $answerpages[] = $page->report_answers(clone($answerpage), clone($answerdata), $useranswer, $pagestats, $i, $n);
        $pageid = $page->nextpageid;
    }

    $userstats = new stdClass;
    if (!empty($userid)) {
        $params = array("opendsa_activity_id"=>$opendsa_activity->id, "userid"=>$userid);

        $alreadycompleted = true;

        if (!$grades = $DB->get_records_select("opendsa_activity_grades", "opendsa_activity_id = :opendsa_activity_id and userid = :userid", $params, "completed", "*", $attempt, 1)) {
            $userstats->grade = -1;
            $userstats->completed = -1;
            $alreadycompleted = false;
        } else {
            $userstats->grade = current($grades);
            $userstats->completed = $userstats->grade->completed;
            $userstats->grade = round($userstats->grade->grade, 2);
        }

        if (!$times = $opendsa_activity->get_user_timers($userid, 'starttime', '*', $attempt, 1)) {
            $userstats->timetotake = -1;
            $alreadycompleted = false;
        } else {
            $userstats->timetotake = current($times);
            $userstats->timetotake = $userstats->timetotake->opendsa_activitytime - $userstats->timetotake->starttime;
        }

        if ($alreadycompleted) {
            $userstats->gradeinfo = opendsa_activity_grade($opendsa_activity, $attempt, $userid);
        }
    }

    return array($answerpages, $userstats);
}

/**
 * Return user's deadline for all opendsa_activitys in a course, hereby taking into account group and user overrides.
 *
 * @param int $courseid the course id.
 * @return object An object with of all opendsa_activitysids and close unixdates in this course,
 * taking into account the most lenient overrides, if existing and 0 if no close date is set.
 */
function opendsa_activity_get_user_deadline($courseid) {
    global $DB, $USER;

    // For teacher and manager/admins return opendsa_activity's deadline.
    if (has_capability('moodle/course:update', context_course::instance($courseid))) {
        $sql = "SELECT opendsa_activity.id, opendsa_activity.deadline AS userdeadline
                  FROM {opendsa_activity} opendsa_activity
                 WHERE opendsa_activity.course = :courseid";

        $results = $DB->get_records_sql($sql, array('courseid' => $courseid));
        return $results;
    }

    $sql = "SELECT a.id,
                   COALESCE(v.userclose, v.groupclose, a.deadline, 0) AS userdeadline
              FROM (
                      SELECT opendsa_activity.id as opendsa_activity_id,
                             MAX(leo.deadline) AS userclose, MAX(qgo.deadline) AS groupclose
                        FROM {opendsa_activity} opendsa_activity
                   LEFT JOIN {opendsa_activity_overrides} leo on opendsa_activity.id = leo.opendsa_activity_id AND leo.userid = :userid
                   LEFT JOIN {groups_members} gm ON gm.userid = :useringroupid
                   LEFT JOIN {opendsa_activity_overrides} qgo on opendsa_activity.id = qgo.opendsa_activity_id AND qgo.groupid = gm.groupid
                       WHERE opendsa_activity.course = :courseid
                    GROUP BY opendsa_activity.id
                   ) v
              JOIN {opendsa_activity} a ON a.id = v.opendsa_activity_id";

    $results = $DB->get_records_sql($sql, array('userid' => $USER->id, 'useringroupid' => $USER->id, 'courseid' => $courseid));
    return $results;

}

/**
 * Abstract class that page type's MUST inherit from.
 *
 * This is the abstract class that ALL add page type forms must extend.
 * You will notice that all but two of the methods this class contains are final.
 * Essentially the only thing that extending classes can do is extend custom_definition.
 * OR if it has a special requirement on creation it can extend construction_override
 *
 * @abstract
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class opendsa_activity_add_page_form_base extends moodleform {

    /**
     * This is the classic define that is used to identify this pagetype.
     * Will be one of OPENDSA_ACTIVITY_*
     * @var int
     */
    public $qtype;

    /**
     * The simple string that describes the page type e.g. truefalse, multichoice
     * @var string
     */
    public $qtypestring;

    /**
     * An array of options used in the htmleditor
     * @var array
     */
    protected $editoroptions = array();

    /**
     * True if this is a standard page of false if it does something special.
     * Questions are standard pages, branch tables are not
     * @var bool
     */
    protected $standard = true;

    /**
     * Answer format supported by question type.
     */
    protected $answerformat = '';

    /**
     * Response format supported by question type.
     */
    protected $responseformat = '';

    /**
     * Each page type can and should override this to add any custom elements to
     * the basic form that they want
     */
    public function custom_definition() {}

    /**
     * Returns answer format used by question type.
     */
    public function get_answer_format() {
        return $this->answerformat;
    }

    /**
     * Returns response format used by question type.
     */
    public function get_response_format() {
        return $this->responseformat;
    }

    /**
     * Used to determine if this is a standard page or a special page
     * @return bool
     */
    public final function is_standard() {
        return (bool)$this->standard;
    }

    /**
     * Add the required basic elements to the form.
     *
     * This method adds the basic elements to the form including title and contents
     * and then calls custom_definition();
     */
    public final function definition() {
        global $CFG;
        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];

        if ($this->qtypestring != 'selectaqtype') {
            if ($this->_customdata['edit']) {
                $mform->addElement('header', 'qtypeheading', get_string('edit'. $this->qtypestring, 'opendsa_activity'));
            } else {
                $mform->addElement('header', 'qtypeheading', get_string('add'. $this->qtypestring, 'opendsa_activity'));
            }
        }

        if (!empty($this->_customdata['returnto'])) {
            $mform->addElement('hidden', 'returnto', $this->_customdata['returnto']);
            $mform->setType('returnto', PARAM_LOCALURL);
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        if ($this->standard === true) {
            $mform->addElement('hidden', 'qtype');
            $mform->setType('qtype', PARAM_INT);

            $mform->addElement('text', 'title', get_string('pagetitle', 'opendsa_activity'), array('size'=>70));
            $mform->addRule('title', get_string('required'), 'required', null, 'client');
            if (!empty($CFG->formatstringstriptags)) {
                $mform->setType('title', PARAM_TEXT);
            } else {
                $mform->setType('title', PARAM_CLEANHTML);
            }

            $this->editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$this->_customdata['maxbytes']);
            $mform->addElement('editor', 'contents_editor', get_string('pagecontents', 'opendsa_activity'), null, $this->editoroptions);
            $mform->setType('contents_editor', PARAM_RAW);
            $mform->addRule('contents_editor', get_string('required'), 'required', null, 'client');
        }

        $this->custom_definition();

        if ($this->_customdata['edit'] === true) {
            $mform->addElement('hidden', 'edit', 1);
            $mform->setType('edit', PARAM_BOOL);
            $this->add_action_buttons(get_string('cancel'), get_string('savepage', 'opendsa_activity'));
        } else if ($this->qtype === 'questiontype') {
            $this->add_action_buttons(get_string('cancel'), get_string('addaquestionpage', 'opendsa_activity'));
        } else {
            $this->add_action_buttons(get_string('cancel'), get_string('savepage', 'opendsa_activity'));
        }
    }

    /**
     * Convenience function: Adds a jumpto select element
     *
     * @param string $name
     * @param string|null $label
     * @param int $selected The page to select by default
     */
    protected final function add_jumpto($name, $label=null, $selected=OPENDSA_ACTIVITY_NEXTPAGE) {
        $title = get_string("jump", "opendsa_activity");
        if ($label === null) {
            $label = $title;
        }
        if (is_int($name)) {
            $name = "jumpto[$name]";
        }
        $this->_form->addElement('select', $name, $label, $this->_customdata['jumpto']);
        $this->_form->setDefault($name, $selected);
        $this->_form->addHelpButton($name, 'jumps', 'opendsa_activity');
    }

    /**
     * Convenience function: Adds a score input element
     *
     * @param string $name
     * @param string|null $label
     * @param mixed $value The default value
     */
    protected final function add_score($name, $label=null, $value=null) {
        if ($label === null) {
            $label = get_string("score", "opendsa_activity");
        }

        if (is_int($name)) {
            $name = "score[$name]";
        }
        $this->_form->addElement('text', $name, $label, array('size'=>5));
        $this->_form->setType($name, PARAM_INT);
        if ($value !== null) {
            $this->_form->setDefault($name, $value);
        }
        $this->_form->addHelpButton($name, 'score', 'opendsa_activity');

        // Score is only used for custom scoring. Disable the element when not in use to stop some confusion.
        if (!$this->_customdata['opendsa_activity']->custom) {
            $this->_form->freeze($name);
        }
    }

    /**
     * Convenience function: Adds an answer editor
     *
     * @param int $count The count of the element to add
     * @param string $label, null means default
     * @param bool $required
     * @param string $format
     * @param array $help Add help text via the addHelpButton. Must be an array which contains the string identifier and
     *                      component as it's elements
     * @return void
     */
    protected final function add_answer($count, $label = null, $required = false, $format= '', array $help = []) {
        if ($label === null) {
            $label = get_string('answer', 'opendsa_activity');
        }

        if ($format == OPENDSA_ACTIVITY_ANSWER_HTML) {
            $this->_form->addElement('editor', 'answer_editor['.$count.']', $label,
                    array('rows' => '4', 'columns' => '80'),
                    array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $this->_customdata['maxbytes']));
            $this->_form->setType('answer_editor['.$count.']', PARAM_RAW);
            $this->_form->setDefault('answer_editor['.$count.']', array('text' => '', 'format' => FORMAT_HTML));
        } else {
            $this->_form->addElement('text', 'answer_editor['.$count.']', $label,
                array('size' => '50', 'maxlength' => '200'));
            $this->_form->setType('answer_editor['.$count.']', PARAM_TEXT);
        }

        if ($required) {
            $this->_form->addRule('answer_editor['.$count.']', get_string('required'), 'required', null, 'client');
        }

        if ($help) {
            $this->_form->addHelpButton("answer_editor[$count]", $help['identifier'], $help['component']);
        }
    }
    /**
     * Convenience function: Adds an response editor
     *
     * @param int $count The count of the element to add
     * @param string $label, null means default
     * @param bool $required
     * @return void
     */
    protected final function add_response($count, $label = null, $required = false) {
        if ($label === null) {
            $label = get_string('response', 'opendsa_activity');
        }
        $this->_form->addElement('editor', 'response_editor['.$count.']', $label,
                 array('rows' => '4', 'columns' => '80'),
                 array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $this->_customdata['maxbytes']));
        $this->_form->setType('response_editor['.$count.']', PARAM_RAW);
        $this->_form->setDefault('response_editor['.$count.']', array('text' => '', 'format' => FORMAT_HTML));

        if ($required) {
            $this->_form->addRule('response_editor['.$count.']', get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * A function that gets called upon init of this object by the calling script.
     *
     * This can be used to process an immediate action if required. Currently it
     * is only used in special cases by non-standard page types.
     *
     * @return bool
     */
    public function construction_override($pageid, opendsa_activity $opendsa_activity) {
        return true;
    }
}



/**
 * Class representation of a opendsa_activity
 *
 * This class is used the interact with, and manage a opendsa_activity once instantiated.
 * If you need to fetch a opendsa_activity object you can do so by calling
 *
 * <code>
 * opendsa_activity::load($opendsa_activity_id);
 * // or
 * $opendsa_activityrecord = $DB->get_record('opendsa_activity', $opendsa_activity_id);
 * $opendsa_activity = new opendsa_activity($opendsa_activityrecord);
 * </code>
 *
 * The class itself extends opendsa_activity_base as all classes within the opendsa_activity module should
 *
 * These properties are from the database
 * @property int $id The id of this opendsa_activity
 * @property int $course The ID of the course this opendsa_activity belongs to
 * @property string $name The name of this opendsa_activity
 * @property int $practice Flag to toggle this as a practice opendsa_activity
 * @property int $modattempts Toggle to allow the user to go back and review answers
 * @property int $usepassword Toggle the use of a password for entry
 * @property string $password The password to require users to enter
 * @property int $dependency ID of another opendsa_activity this opendsa_activity is dependent on
 * @property string $conditions Conditions of the opendsa_activity dependency
 * @property int $grade The maximum grade a user can achieve (%)
 * @property int $custom Toggle custom scoring on or off
 * @property int $ongoing Toggle display of an ongoing score
 * @property int $usemaxgrade How retakes are handled (max=1, mean=0)
 * @property int $maxanswers The max number of answers or branches
 * @property int $maxattempts The maximum number of attempts a user can record
 * @property int $review Toggle use or wrong answer review button
 * @property int $nextpagedefault Override the default next page
 * @property int $feedback Toggles display of default feedback
 * @property int $minquestions Sets a minimum value of pages seen when calculating grades
 * @property int $maxpages Maximum number of pages this opendsa_activity can contain
 * @property int $retake Flag to allow users to retake a opendsa_activity
 * @property int $activitylink Relate this opendsa_activity to another opendsa_activity
 * @property string $mediafile File to pop up to or webpage to display
 * @property int $mediaheight Sets the height of the media file popup
 * @property int $mediawidth Sets the width of the media file popup
 * @property int $mediaclose Toggle display of a media close button
 * @property int $slideshow Flag for whether branch pages should be shown as slideshows
 * @property int $width Width of slideshow
 * @property int $height Height of slideshow
 * @property string $bgcolor Background colour of slideshow
 * @property int $displayleft Display a left menu
 * @property int $displayleftif Sets the condition on which the left menu is displayed
 * @property int $progressbar Flag to toggle display of a opendsa_activity progress bar
 * @property int $available Timestamp of when this opendsa_activity becomes available
 * @property int $deadline Timestamp of when this opendsa_activity is no longer available
 * @property int $timemodified Timestamp when opendsa_activity was last modified
 * @property int $allowofflineattempts Whether to allow the opendsa_activity to be attempted offline in the mobile app
 *
 * These properties are calculated
 * @property int $firstpageid Id of the first page of this opendsa_activity (prevpageid=0)
 * @property int $lastpageid Id of the last page of this opendsa_activity (nextpageid=0)
 *
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opendsa_activity extends opendsa_activity_base {

    /**
     * The id of the first page (where prevpageid = 0) gets set and retrieved by
     * {@see get_firstpageid()} by directly calling <code>$opendsa_activity->firstpageid;</code>
     * @var int
     */
    protected $firstpageid = null;
    /**
     * The id of the last page (where nextpageid = 0) gets set and retrieved by
     * {@see get_lastpageid()} by directly calling <code>$opendsa_activity->lastpageid;</code>
     * @var int
     */
    protected $lastpageid = null;
    /**
     * An array used to cache the pages associated with this opendsa_activity after the first
     * time they have been loaded.
     * A note to developers: If you are going to be working with MORE than one or
     * two pages from a opendsa_activity you should probably call {@see $opendsa_activity->load_all_pages()}
     * in order to save excess database queries.
     * @var array An array of opendsa_activity_page objects
     */
    protected $pages = array();
    /**
     * Flag that gets set to true once all of the pages associated with the opendsa_activity
     * have been loaded.
     * @var bool
     */
    protected $loadedallpages = false;

    /**
     * Course module object gets set and retrieved by directly calling <code>$opendsa_activity->cm;</code>
     * @see get_cm()
     * @var stdClass
     */
    protected $cm = null;

    /**
     * Course object gets set and retrieved by directly calling <code>$opendsa_activity->courserecord;</code>
     * @see get_courserecord()
     * @var stdClass
     */
    protected $courserecord = null;

    /**
     * Context object gets set and retrieved by directly calling <code>$opendsa_activity->context;</code>
     * @see get_context()
     * @var stdClass
     */
    protected $context = null;

    /**
     * Constructor method
     *
     * @param object $properties
     * @param stdClass $cm course module object
     * @param stdClass $course course object
     * @since Moodle 3.3
     */
    public function __construct($properties, $cm = null, $course = null) {
        parent::__construct($properties);
        $this->cm = $cm;
        $this->courserecord = $course;
    }

    /**
     * Simply generates a opendsa_activity object given an array/object of properties
     * Overrides {@see opendsa_activity_base->create()}
     * @static
     * @param object|array $properties
     * @return opendsa_activity
     */
    public static function create($properties) {
        return new opendsa_activity($properties);
    }

    /**
     * Generates a opendsa_activity object from the database given its id
     * @static
     * @param int $opendsa_activity_id
     * @return opendsa_activity
     */
    public static function load($opendsa_activity_id) {
        global $DB;

        if (!$opendsa_activity = $DB->get_record('opendsa_activity', array('id' => $opendsa_activity_id))) {
            print_error('invalidcoursemodule');
        }
        return new opendsa_activity($opendsa_activity);
    }

    /**
     * Deletes this opendsa_activity from the database
     */
    public function delete() {
        global $CFG, $DB;
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->dirroot.'/calendar/lib.php');

        $cm = get_coursemodule_from_instance('opendsa_activity', $this->properties->id, $this->properties->course);
        $context = context_module::instance($cm->id);

        $this->delete_all_overrides();

        grade_update('mod/opendsa_activity', $this->properties->course, 'mod', 'opendsa_activity', $this->properties->id, 0, null, array('deleted'=>1));

        // We must delete the module record after we delete the grade item.
        $DB->delete_records("opendsa_activity", array("id"=>$this->properties->id));
        $DB->delete_records("opendsa_activity_pages", array("opendsa_activity_id"=>$this->properties->id));
        $DB->delete_records("opendsa_activity_answers", array("opendsa_activity_id"=>$this->properties->id));
        $DB->delete_records("opendsa_activity_attempts", array("opendsa_activity_id"=>$this->properties->id));
        $DB->delete_records("opendsa_activity_grades", array("opendsa_activity_id"=>$this->properties->id));
        $DB->delete_records("opendsa_activity_timer", array("opendsa_activity_id"=>$this->properties->id));
        $DB->delete_records("opendsa_activity_branch", array("opendsa_activity_id"=>$this->properties->id));
        if ($events = $DB->get_records('event', array("modulename"=>'opendsa_activity', "instance"=>$this->properties->id))) {
            $coursecontext = context_course::instance($cm->course);
            foreach($events as $event) {
                $event->context = $coursecontext;
                $event = calendar_event::load($event);
                $event->delete();
            }
        }

        // Delete files associated with this module.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id);

        return true;
    }

    /**
     * Deletes a opendsa_activity override from the database and clears any corresponding calendar events
     *
     * @param int $overrideid The id of the override being deleted
     * @return bool true on success
     */
    public function delete_override($overrideid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/calendar/lib.php');

        $cm = get_coursemodule_from_instance('opendsa_activity', $this->properties->id, $this->properties->course);

        $override = $DB->get_record('opendsa_activity_overrides', array('id' => $overrideid), '*', MUST_EXIST);

        // Delete the events.
        $conds = array('modulename' => 'opendsa_activity',
                'instance' => $this->properties->id);
        if (isset($override->userid)) {
            $conds['userid'] = $override->userid;
        } else {
            $conds['groupid'] = $override->groupid;
        }
        $events = $DB->get_records('event', $conds);
        foreach ($events as $event) {
            $eventold = calendar_event::load($event);
            $eventold->delete();
        }

        $DB->delete_records('opendsa_activity_overrides', array('id' => $overrideid));

        // Set the common parameters for one of the events we will be triggering.
        $params = array(
            'objectid' => $override->id,
            'context' => context_module::instance($cm->id),
            'other' => array(
                'opendsa_activity_id' => $override->opendsa_activity_id
            )
        );
        // Determine which override deleted event to fire.
        if (!empty($override->userid)) {
            $params['relateduserid'] = $override->userid;
            $event = \mod_opendsa_activity\event\user_override_deleted::create($params);
        } else {
            $params['other']['groupid'] = $override->groupid;
            $event = \mod_opendsa_activity\event\group_override_deleted::create($params);
        }

        // Trigger the override deleted event.
        $event->add_record_snapshot('opendsa_activity_overrides', $override);
        $event->trigger();

        return true;
    }

    /**
     * Deletes all opendsa_activity overrides from the database and clears any corresponding calendar events
     */
    public function delete_all_overrides() {
        global $DB;

        $overrides = $DB->get_records('opendsa_activity_overrides', array('opendsa_activity_id' => $this->properties->id), 'id');
        foreach ($overrides as $override) {
            $this->delete_override($override->id);
        }
    }

    /**
     * Checks user enrollment in the current course.
     *
     * @param int $userid
     * @return null|stdClass user record
     */
    public function is_participant($userid) {
        return is_enrolled($this->get_context(), $userid, 'mod/opendsa_activity:view', $this->show_only_active_users());
    }

    /**
     * Check is only active users in course should be shown.
     *
     * @return bool true if only active users should be shown.
     */
    public function show_only_active_users() {
        return !has_capability('moodle/course:viewsuspendedusers', $this->get_context());
    }

    /**
     * Updates the opendsa_activity properties with override information for a user.
     *
     * Algorithm:  For each opendsa_activity setting, if there is a matching user-specific override,
     *   then use that otherwise, if there are group-specific overrides, return the most
     *   lenient combination of them.  If neither applies, leave the quiz setting unchanged.
     *
     *   Special case: if there is more than one password that applies to the user, then
     *   opendsa_activity->extrapasswords will contain an array of strings giving the remaining
     *   passwords.
     *
     * @param int $userid The userid.
     */
    public function update_effective_access($userid) {
        global $DB;

        // Check for user override.
        $override = $DB->get_record('opendsa_activity_overrides', array('opendsa_activity_id' => $this->properties->id, 'userid' => $userid));

        if (!$override) {
            $override = new stdClass();
            $override->available = null;
            $override->deadline = null;
            $override->timelimit = null;
            $override->review = null;
            $override->maxattempts = null;
            $override->retake = null;
            $override->password = null;
        }

        // Check for group overrides.
        $groupings = groups_get_user_groups($this->properties->course, $userid);

        if (!empty($groupings[0])) {
            // Select all overrides that apply to the User's groups.
            list($extra, $params) = $DB->get_in_or_equal(array_values($groupings[0]));
            $sql = "SELECT * FROM {opendsa_activity_overrides}
                    WHERE groupid $extra AND opendsa_activity_id = ?";
            $params[] = $this->properties->id;
            $records = $DB->get_records_sql($sql, $params);

            // Combine the overrides.
            $availables = array();
            $deadlines = array();
            $timelimits = array();
            $reviews = array();
            $attempts = array();
            $retakes = array();
            $passwords = array();

            foreach ($records as $gpoverride) {
                if (isset($gpoverride->available)) {
                    $availables[] = $gpoverride->available;
                }
                if (isset($gpoverride->deadline)) {
                    $deadlines[] = $gpoverride->deadline;
                }
                if (isset($gpoverride->timelimit)) {
                    $timelimits[] = $gpoverride->timelimit;
                }
                if (isset($gpoverride->review)) {
                    $reviews[] = $gpoverride->review;
                }
                if (isset($gpoverride->maxattempts)) {
                    $attempts[] = $gpoverride->maxattempts;
                }
                if (isset($gpoverride->retake)) {
                    $retakes[] = $gpoverride->retake;
                }
                if (isset($gpoverride->password)) {
                    $passwords[] = $gpoverride->password;
                }
            }
            // If there is a user override for a setting, ignore the group override.
            if (is_null($override->available) && count($availables)) {
                $override->available = min($availables);
            }
            if (is_null($override->deadline) && count($deadlines)) {
                if (in_array(0, $deadlines)) {
                    $override->deadline = 0;
                } else {
                    $override->deadline = max($deadlines);
                }
            }
            if (is_null($override->timelimit) && count($timelimits)) {
                if (in_array(0, $timelimits)) {
                    $override->timelimit = 0;
                } else {
                    $override->timelimit = max($timelimits);
                }
            }
            if (is_null($override->review) && count($reviews)) {
                $override->review = max($reviews);
            }
            if (is_null($override->maxattempts) && count($attempts)) {
                $override->maxattempts = max($attempts);
            }
            if (is_null($override->retake) && count($retakes)) {
                $override->retake = max($retakes);
            }
            if (is_null($override->password) && count($passwords)) {
                $override->password = array_shift($passwords);
                if (count($passwords)) {
                    $override->extrapasswords = $passwords;
                }
            }

        }

        // Merge with opendsa_activity defaults.
        $keys = array('available', 'deadline', 'timelimit', 'maxattempts', 'review', 'retake');
        foreach ($keys as $key) {
            if (isset($override->{$key})) {
                $this->properties->{$key} = $override->{$key};
            }
        }

        // Special handling of opendsa_activity usepassword and password.
        if (isset($override->password)) {
            if ($override->password == '') {
                $this->properties->usepassword = 0;
            } else {
                $this->properties->usepassword = 1;
                $this->properties->password = $override->password;
                if (isset($override->extrapasswords)) {
                    $this->properties->extrapasswords = $override->extrapasswords;
                }
            }
        }
    }

    /**
     * Fetches messages from the session that may have been set in previous page
     * actions.
     *
     * <code>
     * // Do not call this method directly instead use
     * $opendsa_activity->messages;
     * </code>
     *
     * @return array
     */
    protected function get_messages() {
        global $SESSION;

        $messages = array();
        if (!empty($SESSION->opendsa_activity_messages) && is_array($SESSION->opendsa_activity_messages) && array_key_exists($this->properties->id, $SESSION->opendsa_activity_messages)) {
            $messages = $SESSION->opendsa_activity_messages[$this->properties->id];
            unset($SESSION->opendsa_activity_messages[$this->properties->id]);
        }

        return $messages;
    }

    /**
     * Get all of the attempts for the current user.
     *
     * @param int $retries
     * @param bool $correct Optional: only fetch correct attempts
     * @param int $pageid Optional: only fetch attempts at the given page
     * @param int $userid Optional: defaults to the current user if not set
     * @return array|false
     */
    public function get_attempts($retries, $correct=false, $pageid=null, $userid=null) {
        global $USER, $DB;
        $params = array("opendsa_activity_id"=>$this->properties->id, "userid"=>$userid, "retry"=>$retries);
        if ($correct) {
            $params['correct'] = 1;
        }
        if ($pageid !== null) {
            $params['pageid'] = $pageid;
        }
        if ($userid === null) {
            $params['userid'] = $USER->id;
        }
        return $DB->get_records('opendsa_activity_attempts', $params, 'timeseen ASC');
    }


    /**
     * Get a list of content pages (formerly known as branch tables) viewed in the opendsa_activity for the given user during an attempt.
     *
     * @param  int $opendsa_activityattempt the opendsa_activity attempt number (also known as retries)
     * @param  int $userid        the user id to retrieve the data from
     * @param  string $sort          an order to sort the results in (a valid SQL ORDER BY parameter)
     * @param  string $fields        a comma separated list of fields to return
     * @return array of pages
     * @since  Moodle 3.3
     */
    public function get_content_pages_viewed($opendsa_activityattempt, $userid = null, $sort = '', $fields = '*') {
        global $USER, $DB;

        if ($userid === null) {
            $userid = $USER->id;
        }
        $conditions = array("opendsa_activity_id" => $this->properties->id, "userid" => $userid, "retry" => $opendsa_activityattempt);
        return $DB->get_records('opendsa_activity_branch', $conditions, $sort, $fields);
    }

    /**
     * Returns the first page for the opendsa_activity or false if there isn't one.
     *
     * This method should be called via the magic method __get();
     * <code>
     * $firstpage = $opendsa_activity->firstpage;
     * </code>
     *
     * @return opendsa_activity_page|bool Returns the opendsa_activity_page specialised object or false
     */
    protected function get_firstpage() {
        $pages = $this->load_all_pages();
        if (count($pages) > 0) {
            foreach ($pages as $page) {
                if ((int)$page->prevpageid === 0) {
                    return $page;
                }
            }
        }
        return false;
    }

    /**
     * Returns the last page for the opendsa_activity or false if there isn't one.
     *
     * This method should be called via the magic method __get();
     * <code>
     * $lastpage = $opendsa_activity->lastpage;
     * </code>
     *
     * @return opendsa_activity_page|bool Returns the opendsa_activity_page specialised object or false
     */
    protected function get_lastpage() {
        $pages = $this->load_all_pages();
        if (count($pages) > 0) {
            foreach ($pages as $page) {
                if ((int)$page->nextpageid === 0) {
                    return $page;
                }
            }
        }
        return false;
    }

    /**
     * Returns the id of the first page of this opendsa_activity. (prevpageid = 0)
     * @return int
     */
    protected function get_firstpageid() {
        global $DB;
        if ($this->firstpageid == null) {
            if (!$this->loadedallpages) {
                $firstpageid = $DB->get_field('opendsa_activity_pages', 'id', array('opendsa_activity_id'=>$this->properties->id, 'prevpageid'=>0));
                if (!$firstpageid) {
                    print_error('cannotfindfirstpage', 'opendsa_activity');
                }
                $this->firstpageid = $firstpageid;
            } else {
                $firstpage = $this->get_firstpage();
                $this->firstpageid = $firstpage->id;
            }
        }
        return $this->firstpageid;
    }

    /**
     * Returns the id of the last page of this opendsa_activity. (nextpageid = 0)
     * @return int
     */
    public function get_lastpageid() {
        global $DB;
        if ($this->lastpageid == null) {
            if (!$this->loadedallpages) {
                $lastpageid = $DB->get_field('opendsa_activity_pages', 'id', array('opendsa_activity_id'=>$this->properties->id, 'nextpageid'=>0));
                if (!$lastpageid) {
                    print_error('cannotfindlastpage', 'opendsa_activity');
                }
                $this->lastpageid = $lastpageid;
            } else {
                $lastpageid = $this->get_lastpage();
                $this->lastpageid = $lastpageid->id;
            }
        }

        return $this->lastpageid;
    }

     /**
     * Gets the next page id to display after the one that is provided.
     * @param int $nextpageid
     * @return bool
     */
    public function get_next_page($nextpageid) {
        global $USER, $DB;
        $allpages = $this->load_all_pages();
        if ($this->properties->nextpagedefault) {
            // in Flash Card mode...first get number of retakes
            $nretakes = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id));
            shuffle($allpages);
            $found = false;
            if ($this->properties->nextpagedefault == OPENDSA_ACTIVITY_UNSEENPAGE) {
                foreach ($allpages as $nextpage) {
                    if (!$DB->count_records("opendsa_activity_attempts", array("pageid" => $nextpage->id, "userid" => $USER->id, "retry" => $nretakes))) {
                        $found = true;
                        break;
                    }
                }
            } elseif ($this->properties->nextpagedefault == OPENDSA_ACTIVITY_UNANSWEREDPAGE) {
                foreach ($allpages as $nextpage) {
                    if (!$DB->count_records("opendsa_activity_attempts", array('pageid' => $nextpage->id, 'userid' => $USER->id, 'correct' => 1, 'retry' => $nretakes))) {
                        $found = true;
                        break;
                    }
                }
            }
            if ($found) {
                if ($this->properties->maxpages) {
                    // check number of pages viewed (in the opendsa_activity)
                    if ($DB->count_records("opendsa_activity_attempts", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id, "retry" => $nretakes)) >= $this->properties->maxpages) {
                        return OPENDSA_ACTIVITY_EOL;
                    }
                }
                return $nextpage->id;
            }
        }
        // In a normal opendsa_activity mode
        foreach ($allpages as $nextpage) {
            if ((int)$nextpage->id === (int)$nextpageid) {
                return $nextpage->id;
            }
        }
        return OPENDSA_ACTIVITY_EOL;
    }

    /**
     * Sets a message against the session for this opendsa_activity that will displayed next
     * time the opendsa_activity processes messages
     *
     * @param string $message
     * @param string $class
     * @param string $align
     * @return bool
     */
    public function add_message($message, $class="notifyproblem", $align='center') {
        global $SESSION;

        if (empty($SESSION->opendsa_activity_messages) || !is_array($SESSION->opendsa_activity_messages)) {
            $SESSION->opendsa_activity_messages = array();
            $SESSION->opendsa_activity_messages[$this->properties->id] = array();
        } else if (!array_key_exists($this->properties->id, $SESSION->opendsa_activity_messages)) {
            $SESSION->opendsa_activity_messages[$this->properties->id] = array();
        }

        $SESSION->opendsa_activity_messages[$this->properties->id][] = array($message, $class, $align);

        return true;
    }

    /**
     * Check if the opendsa_activity is accessible at the present time
     * @return bool True if the opendsa_activity is accessible, false otherwise
     */
    public function is_accessible() {
        $available = $this->properties->available;
        $deadline = $this->properties->deadline;
        return (($available == 0 || time() >= $available) && ($deadline == 0 || time() < $deadline));
    }

    /**
     * Starts the opendsa_activity time for the current user
     * @return bool Returns true
     */
    public function start_timer() {
        global $USER, $DB;

        $cm = get_coursemodule_from_instance('opendsa_activity', $this->properties()->id, $this->properties()->course,
            false, MUST_EXIST);

        // Trigger opendsa_activity started event.
        $event = \mod_opendsa_activity\event\opendsa_activity_started::create(array(
            'objectid' => $this->properties()->id,
            'context' => context_module::instance($cm->id),
            'courseid' => $this->properties()->course
        ));
        $event->trigger();

        $USER->startopendsa_activity[$this->properties->id] = true;

        $timenow = time();
        $startopendsa_activity = new stdClass;
        $startopendsa_activity->opendsa_activity_id = $this->properties->id;
        $startopendsa_activity->userid = $USER->id;
        $startopendsa_activity->starttime = $timenow;
        $startopendsa_activity->opendsa_activitytime = $timenow;
        if (WS_SERVER) {
            $startopendsa_activity->timemodifiedoffline = $timenow;
        }
        $DB->insert_record('opendsa_activity_timer', $startopendsa_activity);
        if ($this->properties->timelimit) {
            $this->add_message(get_string('timelimitwarning', 'opendsa_activity', format_time($this->properties->timelimit)), 'center');
        }
        return true;
    }

    /**
     * Updates the timer to the current time and returns the new timer object
     * @param bool $restart If set to true the timer is restarted
     * @param bool $continue If set to true AND $restart=true then the timer
     *                        will continue from a previous attempt
     * @return stdClass The new timer
     */
    public function update_timer($restart=false, $continue=false, $endreached =false) {
        global $USER, $DB;

        $cm = get_coursemodule_from_instance('opendsa_activity', $this->properties->id, $this->properties->course);

        // clock code
        // get time information for this user
        if (!$timer = $this->get_user_timers($USER->id, 'starttime DESC', '*', 0, 1)) {
            $this->start_timer();
            $timer = $this->get_user_timers($USER->id, 'starttime DESC', '*', 0, 1);
        }
        $timer = current($timer); // This will get the latest start time record.

        if ($restart) {
            if ($continue) {
                // continue a previous test, need to update the clock  (think this option is disabled atm)
                $timer->starttime = time() - ($timer->opendsa_activitytime - $timer->starttime);

                // Trigger opendsa_activity resumed event.
                $event = \mod_opendsa_activity\event\opendsa_activity_resumed::create(array(
                    'objectid' => $this->properties->id,
                    'context' => context_module::instance($cm->id),
                    'courseid' => $this->properties->course
                ));
                $event->trigger();

            } else {
                // starting over, so reset the clock
                $timer->starttime = time();

                // Trigger opendsa_activity restarted event.
                $event = \mod_opendsa_activity\event\opendsa_activity_restarted::create(array(
                    'objectid' => $this->properties->id,
                    'context' => context_module::instance($cm->id),
                    'courseid' => $this->properties->course
                ));
                $event->trigger();

            }
        }

        $timenow = time();
        $timer->opendsa_activitytime = $timenow;
        if (WS_SERVER) {
            $timer->timemodifiedoffline = $timenow;
        }
        $timer->completed = $endreached;
        $DB->update_record('opendsa_activity_timer', $timer);

        // Update completion state.
        $cm = get_coursemodule_from_instance('opendsa_activity', $this->properties()->id, $this->properties()->course,
            false, MUST_EXIST);
        $course = get_course($cm->course);
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) && $this->properties()->completiontimespent > 0) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }
        return $timer;
    }

    /**
     * Updates the timer to the current time then stops it by unsetting the user var
     * @return bool Returns true
     */
    public function stop_timer() {
        global $USER, $DB;
        unset($USER->startopendsa_activity[$this->properties->id]);

        $cm = get_coursemodule_from_instance('opendsa_activity', $this->properties()->id, $this->properties()->course,
            false, MUST_EXIST);

        // Trigger opendsa_activity ended event.
        $event = \mod_opendsa_activity\event\opendsa_activity_ended::create(array(
            'objectid' => $this->properties()->id,
            'context' => context_module::instance($cm->id),
            'courseid' => $this->properties()->course
        ));
        $event->trigger();

        return $this->update_timer(false, false, true);
    }

    /**
     * Checks to see if the opendsa_activity has pages
     */
    public function has_pages() {
        global $DB;
        $pagecount = $DB->count_records('opendsa_activity_pages', array('opendsa_activity_id'=>$this->properties->id));
        return ($pagecount>0);
    }

    /**
     * Returns the link for the related activity
     * @return string
     */
    public function link_for_activitylink() {
        global $DB;
        $module = $DB->get_record('course_modules', array('id' => $this->properties->activitylink));
        if ($module) {
            $modname = $DB->get_field('modules', 'name', array('id' => $module->module));
            if ($modname) {
                $instancename = $DB->get_field($modname, 'name', array('id' => $module->instance));
                if ($instancename) {
                    return html_writer::link(new moodle_url('/mod/'.$modname.'/view.php',
                        array('id' => $this->properties->activitylink)), get_string('activitylinkname',
                        'opendsa_activity', $instancename), array('class' => 'centerpadded opendsa_activitybutton standardbutton p-r-1'));
                }
            }
        }
        return '';
    }

    /**
     * Loads the requested page.
     *
     * This function will return the requested page id as either a specialised
     * opendsa_activity_page object OR as a generic opendsa_activity_page.
     * If the page has been loaded previously it will be returned from the pages
     * array, otherwise it will be loaded from the database first
     *
     * @param int $pageid
     * @return opendsa_activity_page A opendsa_activity_page object or an object that extends it
     */
    public function load_page($pageid) {
        if (!array_key_exists($pageid, $this->pages)) {
            $manager = opendsa_activity_page_type_manager::get($this);
            $this->pages[$pageid] = $manager->load_page($pageid, $this);
        }
        return $this->pages[$pageid];
    }

    /**
     * Loads ALL of the pages for this opendsa_activity
     *
     * @return array An array containing all pages from this opendsa_activity
     */
    public function load_all_pages() {
        if (!$this->loadedallpages) {
            $manager = opendsa_activity_page_type_manager::get($this);
            $this->pages = $manager->load_all_pages($this);
            $this->loadedallpages = true;
        }
        return $this->pages;
    }

    /**
     * Duplicate the opendsa_activity page.
     *
     * @param  int $pageid Page ID of the page to duplicate.
     * @return void.
     */
    public function duplicate_page($pageid) {
        global $PAGE;
        $cm = get_coursemodule_from_instance('opendsa_activity', $this->properties->id, $this->properties->course);
        $context = context_module::instance($cm->id);
        // Load the page.
        $page = $this->load_page($pageid);
        $properties = $page->properties();
        // The create method checks to see if these properties are set and if not sets them to zero, hence the unsetting here.
        if (!$properties->qoption) {
            unset($properties->qoption);
        }
        if (!$properties->layout) {
            unset($properties->layout);
        }
        if (!$properties->display) {
            unset($properties->display);
        }

        $properties->pageid = $pageid;
        // Add text and format into the format required to create a new page.
        $properties->contents_editor = array(
            'text' => $properties->contents,
            'format' => $properties->contentsformat
        );
        $answers = $page->get_answers();
        // Answers need to be added to $properties.
        $i = 0;
        $answerids = array();
        foreach ($answers as $answer) {
            // Needs to be rearranged to work with the create function.
            $properties->answer_editor[$i] = array(
                'text' => $answer->answer,
                'format' => $answer->answerformat
            );

            $properties->response_editor[$i] = array(
              'text' => $answer->response,
              'format' => $answer->responseformat
            );
            $answerids[] = $answer->id;

            $properties->jumpto[$i] = $answer->jumpto;
            $properties->score[$i] = $answer->score;

            $i++;
        }
        // Create the duplicate page.
        $newopendsa_activitypage = opendsa_activity_page::create($properties, $this, $context, $PAGE->course->maxbytes);
        $newanswers = $newopendsa_activitypage->get_answers();
        // Copy over the file areas as well.
        $this->copy_page_files('page_contents', $pageid, $newopendsa_activitypage->id, $context->id);
        $j = 0;
        foreach ($newanswers as $answer) {
            if (isset($answer->answer) && strpos($answer->answer, '@@PLUGINFILE@@') !== false) {
                $this->copy_page_files('page_answers', $answerids[$j], $answer->id, $context->id);
            }
            if (isset($answer->response) && !is_array($answer->response) && strpos($answer->response, '@@PLUGINFILE@@') !== false) {
                $this->copy_page_files('page_responses', $answerids[$j], $answer->id, $context->id);
            }
            $j++;
        }
    }

    /**
     * Copy the files from one page to another.
     *
     * @param  string $filearea Area that the files are stored.
     * @param  int $itemid Item ID.
     * @param  int $newitemid The item ID for the new page.
     * @param  int $contextid Context ID for this page.
     * @return void.
     */
    protected function copy_page_files($filearea, $itemid, $newitemid, $contextid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_opendsa_activity', $filearea, $itemid);
        foreach ($files as $file) {
            $fieldupdates = array('itemid' => $newitemid);
            $fs->create_file_from_storedfile($fieldupdates, $file);
        }
    }

    /**
     * Determines if a jumpto value is correct or not.
     *
     * returns true if jumpto page is (logically) after the pageid page or
     * if the jumpto value is a special value.  Returns false in all other cases.
     *
     * @param int $pageid Id of the page from which you are jumping from.
     * @param int $jumpto The jumpto number.
     * @return boolean True or false after a series of tests.
     **/
    public function jumpto_is_correct($pageid, $jumpto) {
        global $DB;

        // first test the special values
        if (!$jumpto) {
            // same page
            return false;
        } elseif ($jumpto == OPENDSA_ACTIVITY_NEXTPAGE) {
            return true;
        } elseif ($jumpto == OPENDSA_ACTIVITY_UNSEENBRANCHPAGE) {
            return true;
        } elseif ($jumpto == OPENDSA_ACTIVITY_RANDOMPAGE) {
            return true;
        } elseif ($jumpto == OPENDSA_ACTIVITY_CLUSTERJUMP) {
            return true;
        } elseif ($jumpto == OPENDSA_ACTIVITY_EOL) {
            return true;
        }

        $pages = $this->load_all_pages();
        $apageid = $pages[$pageid]->nextpageid;
        while ($apageid != 0) {
            if ($jumpto == $apageid) {
                return true;
            }
            $apageid = $pages[$apageid]->nextpageid;
        }
        return false;
    }

    /**
     * Returns the time a user has remaining on this opendsa_activity
     * @param int $starttime Starttime timestamp
     * @return string
     */
    public function time_remaining($starttime) {
        $timeleft = $starttime + $this->properties->timelimit - time();
        $hours = floor($timeleft/3600);
        $timeleft = $timeleft - ($hours * 3600);
        $minutes = floor($timeleft/60);
        $secs = $timeleft - ($minutes * 60);

        if ($minutes < 10) {
            $minutes = "0$minutes";
        }
        if ($secs < 10) {
            $secs = "0$secs";
        }
        $output   = array();
        $output[] = $hours;
        $output[] = $minutes;
        $output[] = $secs;
        $output = implode(':', $output);
        return $output;
    }

    /**
     * Interprets OPENDSA_ACTIVITY_CLUSTERJUMP jumpto value.
     *
     * This will select a page randomly
     * and the page selected will be inbetween a cluster page and end of clutter or end of opendsa_activity
     * and the page selected will be a page that has not been viewed already
     * and if any pages are within a branch table or end of branch then only 1 page within
     * the branch table or end of branch will be randomly selected (sub clustering).
     *
     * @param int $pageid Id of the current page from which we are jumping from.
     * @param int $userid Id of the user.
     * @return int The id of the next page.
     **/
    public function cluster_jump($pageid, $userid=null) {
        global $DB, $USER;

        if ($userid===null) {
            $userid = $USER->id;
        }
        // get the number of retakes
        if (!$retakes = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id"=>$this->properties->id, "userid"=>$userid))) {
            $retakes = 0;
        }
        // get all the opendsa_activity_attempts aka what the user has seen
        $seenpages = array();
        if ($attempts = $this->get_attempts($retakes)) {
            foreach ($attempts as $attempt) {
                $seenpages[$attempt->pageid] = $attempt->pageid;
            }

        }

        // get the opendsa_activity pages
        $opendsa_activitypages = $this->load_all_pages();
        // find the start of the cluster
        while ($pageid != 0) { // this condition should not be satisfied... should be a cluster page
            if ($opendsa_activitypages[$pageid]->qtype == OPENDSA_ACTIVITY_PAGE_CLUSTER) {
                break;
            }
            $pageid = $opendsa_activitypages[$pageid]->prevpageid;
        }

        $clusterpages = array();
        $clusterpages = $this->get_sub_pages_of($pageid, array(OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER));
        $unseen = array();
        foreach ($clusterpages as $key=>$cluster) {
            // Remove the page if  it is in a branch table or is an endofbranch.
            if ($this->is_sub_page_of_type($cluster->id,
                    array(OPENDSA_ACTIVITY_PAGE_BRANCHTABLE), array(OPENDSA_ACTIVITY_PAGE_ENDOFBRANCH, OPENDSA_ACTIVITY_PAGE_CLUSTER))
                    || $cluster->qtype == OPENDSA_ACTIVITY_PAGE_ENDOFBRANCH) {
                unset($clusterpages[$key]);
            } else if ($cluster->qtype == OPENDSA_ACTIVITY_PAGE_BRANCHTABLE) {
                // If branchtable, check to see if any pages inside have been viewed.
                $branchpages = $this->get_sub_pages_of($cluster->id, array(OPENDSA_ACTIVITY_PAGE_BRANCHTABLE, OPENDSA_ACTIVITY_PAGE_ENDOFBRANCH));
                $flag = true;
                foreach ($branchpages as $branchpage) {
                    if (array_key_exists($branchpage->id, $seenpages)) {  // Check if any of the pages have been viewed.
                        $flag = false;
                    }
                }
                if ($flag && count($branchpages) > 0) {
                    // Add branch table.
                    $unseen[] = $cluster;
                }
            } elseif ($cluster->is_unseen($seenpages)) {
                $unseen[] = $cluster;
            }
        }

        if (count($unseen) > 0) {
            // it does not contain elements, then use exitjump, otherwise find out next page/branch
            $nextpage = $unseen[rand(0, count($unseen)-1)];
            if ($nextpage->qtype == OPENDSA_ACTIVITY_PAGE_BRANCHTABLE) {
                // if branch table, then pick a random page inside of it
                $branchpages = $this->get_sub_pages_of($nextpage->id, array(OPENDSA_ACTIVITY_PAGE_BRANCHTABLE, OPENDSA_ACTIVITY_PAGE_ENDOFBRANCH));
                return $branchpages[rand(0, count($branchpages)-1)]->id;
            } else { // otherwise, return the page's id
                return $nextpage->id;
            }
        } else {
            // seen all there is to see, leave the cluster
            if (end($clusterpages)->nextpageid == 0) {
                return OPENDSA_ACTIVITY_EOL;
            } else {
                $clusterendid = $pageid;
                while ($clusterendid != 0) { // This condition should not be satisfied... should be an end of cluster page.
                    if ($opendsa_activitypages[$clusterendid]->qtype == OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER) {
                        break;
                    }
                    $clusterendid = $opendsa_activitypages[$clusterendid]->nextpageid;
                }
                $exitjump = $DB->get_field("opendsa_activity_answers", "jumpto", array("pageid" => $clusterendid, "opendsa_activity_id" => $this->properties->id));
                if ($exitjump == OPENDSA_ACTIVITY_NEXTPAGE) {
                    $exitjump = $opendsa_activitypages[$clusterendid]->nextpageid;
                }
                if ($exitjump == 0) {
                    return OPENDSA_ACTIVITY_EOL;
                } else if (in_array($exitjump, array(OPENDSA_ACTIVITY_EOL, OPENDSA_ACTIVITY_PREVIOUSPAGE))) {
                    return $exitjump;
                } else {
                    if (!array_key_exists($exitjump, $opendsa_activitypages)) {
                        $found = false;
                        foreach ($opendsa_activitypages as $page) {
                            if ($page->id === $clusterendid) {
                                $found = true;
                            } else if ($page->qtype == OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER) {
                                $exitjump = $DB->get_field("opendsa_activity_answers", "jumpto", array("pageid" => $page->id, "opendsa_activity_id" => $this->properties->id));
                                if ($exitjump == OPENDSA_ACTIVITY_NEXTPAGE) {
                                    $exitjump = $opendsa_activitypages[$page->id]->nextpageid;
                                }
                                break;
                            }
                        }
                    }
                    if (!array_key_exists($exitjump, $opendsa_activitypages)) {
                        return OPENDSA_ACTIVITY_EOL;
                    }
                    // Check to see that the return type is not a cluster.
                    if ($opendsa_activitypages[$exitjump]->qtype == OPENDSA_ACTIVITY_PAGE_CLUSTER) {
                        // If the exitjump is a cluster then go through this function again and try to find an unseen question.
                        $exitjump = $this->cluster_jump($exitjump, $userid);
                    }
                    return $exitjump;
                }
            }
        }
    }

    /**
     * Finds all pages that appear to be a subtype of the provided pageid until
     * an end point specified within $ends is encountered or no more pages exist
     *
     * @param int $pageid
     * @param array $ends An array of OPENDSA_ACTIVITY_PAGE_* types that signify an end of
     *               the subtype
     * @return array An array of specialised opendsa_activity_page objects
     */
    public function get_sub_pages_of($pageid, array $ends) {
        $opendsa_activitypages = $this->load_all_pages();
        $pageid = $opendsa_activitypages[$pageid]->nextpageid;  // move to the first page after the branch table
        $pages = array();

        while (true) {
            if ($pageid == 0 || in_array($opendsa_activitypages[$pageid]->qtype, $ends)) {
                break;
            }
            $pages[] = $opendsa_activitypages[$pageid];
            $pageid = $opendsa_activitypages[$pageid]->nextpageid;
        }

        return $pages;
    }

    /**
     * Checks to see if the specified page[id] is a subpage of a type specified in
     * the $types array, until either there are no more pages of we find a type
     * corresponding to that of a type specified in $ends
     *
     * @param int $pageid The id of the page to check
     * @param array $types An array of types that would signify this page was a subpage
     * @param array $ends An array of types that mean this is not a subpage
     * @return bool
     */
    public function is_sub_page_of_type($pageid, array $types, array $ends) {
        $pages = $this->load_all_pages();
        $pageid = $pages[$pageid]->prevpageid; // move up one

        array_unshift($ends, 0);
        // go up the pages till branch table
        while (true) {
            if ($pageid==0 || in_array($pages[$pageid]->qtype, $ends)) {
                return false;
            } else if (in_array($pages[$pageid]->qtype, $types)) {
                return true;
            }
            $pageid = $pages[$pageid]->prevpageid;
        }
    }

    /**
     * Move a page resorting all other pages.
     *
     * @param int $pageid
     * @param int $after
     * @return void
     */
    public function resort_pages($pageid, $after) {
        global $CFG;

        $cm = get_coursemodule_from_instance('opendsa_activity', $this->properties->id, $this->properties->course);
        $context = context_module::instance($cm->id);

        $pages = $this->load_all_pages();

        if (!array_key_exists($pageid, $pages) || ($after!=0 && !array_key_exists($after, $pages))) {
            print_error('cannotfindpages', 'opendsa_activity', "$CFG->wwwroot/mod/opendsa_activity/edit.php?id=$cm->id");
        }

        $pagetomove = clone($pages[$pageid]);
        unset($pages[$pageid]);

        $pageids = array();
        if ($after === 0) {
            $pageids['p0'] = $pageid;
        }
        foreach ($pages as $page) {
            $pageids[] = $page->id;
            if ($page->id == $after) {
                $pageids[] = $pageid;
            }
        }

        $pageidsref = $pageids;
        reset($pageidsref);
        $prev = 0;
        $next = next($pageidsref);
        foreach ($pageids as $pid) {
            if ($pid === $pageid) {
                $page = $pagetomove;
            } else {
                $page = $pages[$pid];
            }
            if ($page->prevpageid != $prev || $page->nextpageid != $next) {
                $page->move($next, $prev);

                if ($pid === $pageid) {
                    // We will trigger an event.
                    $pageupdated = array('next' => $next, 'prev' => $prev);
                }
            }

            $prev = $page->id;
            $next = next($pageidsref);
            if (!$next) {
                $next = 0;
            }
        }

        // Trigger an event: page moved.
        if (!empty($pageupdated)) {
            $eventparams = array(
                'context' => $context,
                'objectid' => $pageid,
                'other' => array(
                    'pagetype' => $page->get_typestring(),
                    'prevpageid' => $pageupdated['prev'],
                    'nextpageid' => $pageupdated['next']
                )
            );
            $event = \mod_opendsa_activity\event\page_moved::create($eventparams);
            $event->trigger();
        }

    }

    /**
     * Return the opendsa_activity context object.
     *
     * @return stdClass context
     * @since  Moodle 3.3
     */
    public function get_context() {
        if ($this->context == null) {
            $this->context = context_module::instance($this->get_cm()->id);
        }
        return $this->context;
    }

    /**
     * Set the opendsa_activity course module object.
     *
     * @param stdClass $cm course module objct
     * @since  Moodle 3.3
     */
    private function set_cm($cm) {
        $this->cm = $cm;
    }

    /**
     * Return the opendsa_activity course module object.
     *
     * @return stdClass course module
     * @since  Moodle 3.3
     */
    public function get_cm() {
        if ($this->cm == null) {
            $this->cm = get_coursemodule_from_instance('opendsa_activity', $this->properties->id);
        }
        return $this->cm;
    }

    /**
     * Set the opendsa_activity course object.
     *
     * @param stdClass $course course objct
     * @since  Moodle 3.3
     */
    private function set_courserecord($course) {
        $this->courserecord = $course;
    }

    /**
     * Return the opendsa_activity course object.
     *
     * @return stdClass course
     * @since  Moodle 3.3
     */
    public function get_courserecord() {
        global $DB;

        if ($this->courserecord == null) {
            $this->courserecord = $DB->get_record('course', array('id' => $this->properties->course));
        }
        return $this->courserecord;
    }

    /**
     * Check if the user can manage the opendsa_activity activity.
     *
     * @return bool true if the user can manage the opendsa_activity
     * @since  Moodle 3.3
     */
    public function can_manage() {
        return has_capability('mod/opendsa_activity:manage', $this->get_context());
    }

    /**
     * Check if time restriction is applied.
     *
     * @return mixed false if  there aren't restrictions or an object with the restriction information
     * @since  Moodle 3.3
     */
    public function get_time_restriction_status() {
        if ($this->can_manage()) {
            return false;
        }

        if (!$this->is_accessible()) {
            if ($this->properties->deadline != 0 && time() > $this->properties->deadline) {
                $status = ['reason' => 'opendsa_activityclosed', 'time' => $this->properties->deadline];
            } else {
                $status = ['reason' => 'opendsa_activityopen', 'time' => $this->properties->available];
            }
            return (object) $status;
        }
        return false;
    }

    /**
     * Check if password restriction is applied.
     *
     * @param string $userpassword the user password to check (if the restriction is set)
     * @return mixed false if there aren't restrictions or an object with the restriction information
     * @since  Moodle 3.3
     */
    public function get_password_restriction_status($userpassword) {
        global $USER;
        if ($this->can_manage()) {
            return false;
        }

        if ($this->properties->usepassword && empty($USER->opendsa_activityloggedin[$this->id])) {
            $correctpass = false;
            if (!empty($userpassword) &&
                    (($this->properties->password == md5(trim($userpassword))) || ($this->properties->password == trim($userpassword)))) {
                // With or without md5 for backward compatibility (MDL-11090).
                $correctpass = true;
                $USER->opendsa_activityloggedin[$this->id] = true;
            } else if (isset($this->properties->extrapasswords)) {
                // Group overrides may have additional passwords.
                foreach ($this->properties->extrapasswords as $password) {
                    if (strcmp($password, md5(trim($userpassword))) === 0 || strcmp($password, trim($userpassword)) === 0) {
                        $correctpass = true;
                        $USER->opendsa_activityloggedin[$this->id] = true;
                    }
                }
            }
            return !$correctpass;
        }
        return false;
    }

    /**
     * Check if dependencies restrictions are applied.
     *
     * @return mixed false if there aren't restrictions or an object with the restriction information
     * @since  Moodle 3.3
     */
    public function get_dependencies_restriction_status() {
        global $DB, $USER;
        if ($this->can_manage()) {
            return false;
        }

        if ($dependentopendsa_activity = $DB->get_record('opendsa_activity', array('id' => $this->properties->dependency))) {
            // Lesson exists, so we can proceed.
            $conditions = unserialize($this->properties->conditions);
            // Assume false for all.
            $errors = array();
            // Check for the timespent condition.
            if ($conditions->timespent) {
                $timespent = false;
                if ($attempttimes = $DB->get_records('opendsa_activity_timer', array("userid" => $USER->id, "opendsa_activity_id" => $dependentopendsa_activity->id))) {
                    // Go through all the times and test to see if any of them satisfy the condition.
                    foreach ($attempttimes as $attempttime) {
                        $duration = $attempttime->opendsa_activitytime - $attempttime->starttime;
                        if ($conditions->timespent < $duration / 60) {
                            $timespent = true;
                        }
                    }
                }
                if (!$timespent) {
                    $errors[] = get_string('timespenterror', 'opendsa_activity', $conditions->timespent);
                }
            }
            // Check for the gradebetterthan condition.
            if ($conditions->gradebetterthan) {
                $gradebetterthan = false;
                if ($studentgrades = $DB->get_records('opendsa_activity_grades', array("userid" => $USER->id, "opendsa_activity_id" => $dependentopendsa_activity->id))) {
                    // Go through all the grades and test to see if any of them satisfy the condition.
                    foreach ($studentgrades as $studentgrade) {
                        if ($studentgrade->grade >= $conditions->gradebetterthan) {
                            $gradebetterthan = true;
                        }
                    }
                }
                if (!$gradebetterthan) {
                    $errors[] = get_string('gradebetterthanerror', 'opendsa_activity', $conditions->gradebetterthan);
                }
            }
            // Check for the completed condition.
            if ($conditions->completed) {
                if (!$DB->count_records('opendsa_activity_grades', array('userid' => $USER->id, 'opendsa_activity_id' => $dependentopendsa_activity->id))) {
                    $errors[] = get_string('completederror', 'opendsa_activity');
                }
            }
            if (!empty($errors)) {
                return (object) ['errors' => $errors, 'dependentopendsa_activity' => $dependentopendsa_activity];
            }
        }
        return false;
    }

    /**
     * Check if the opendsa_activity is in review mode. (The user already finished it and retakes are not allowed).
     *
     * @return bool true if is in review mode
     * @since  Moodle 3.3
     */
    public function is_in_review_mode() {
        global $DB, $USER;

        $userhasgrade = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id));
        if ($userhasgrade && !$this->properties->retake) {
            return true;
        }
        return false;
    }

    /**
     * Return the last page the current user saw.
     *
     * @param int $retriescount the number of retries for the opendsa_activity (the last retry number).
     * @return mixed false if the user didn't see the opendsa_activity or the last page id
     */
    public function get_last_page_seen($retriescount) {
        global $DB, $USER;

        $lastpageseen = false;
        $allattempts = $this->get_attempts($retriescount);
        if (!empty($allattempts)) {
            $attempt = end($allattempts);
            $attemptpage = $this->load_page($attempt->pageid);
            $jumpto = $DB->get_field('opendsa_activity_answers', 'jumpto', array('id' => $attempt->answerid));
            // Convert the jumpto to a proper page id.
            if ($jumpto == 0) {
                // Check if a question has been incorrectly answered AND no more attempts at it are left.
                $nattempts = $this->get_attempts($attempt->retry, false, $attempt->pageid, $USER->id);
                if (count($nattempts) >= $this->properties->maxattempts) {
                    $lastpageseen = $this->get_next_page($attemptpage->nextpageid);
                } else {
                    $lastpageseen = $attempt->pageid;
                }
            } else if ($jumpto == OPENDSA_ACTIVITY_NEXTPAGE) {
                $lastpageseen = $this->get_next_page($attemptpage->nextpageid);
            } else if ($jumpto == OPENDSA_ACTIVITY_CLUSTERJUMP) {
                $lastpageseen = $this->cluster_jump($attempt->pageid);
            } else {
                $lastpageseen = $jumpto;
            }
        }

        if ($branchtables = $this->get_content_pages_viewed($retriescount, $USER->id, 'timeseen DESC')) {
            // In here, user has viewed a branch table.
            $lastbranchtable = current($branchtables);
            if (count($allattempts) > 0) {
                if ($lastbranchtable->timeseen > $attempt->timeseen) {
                    // This branch table was viewed more recently than the question page.
                    if (!empty($lastbranchtable->nextpageid)) {
                        $lastpageseen = $lastbranchtable->nextpageid;
                    } else {
                        // Next page ID did not exist prior to MDL-34006.
                        $lastpageseen = $lastbranchtable->pageid;
                    }
                }
            } else {
                // Has not answered any questions but has viewed a branch table.
                if (!empty($lastbranchtable->nextpageid)) {
                    $lastpageseen = $lastbranchtable->nextpageid;
                } else {
                    // Next page ID did not exist prior to MDL-34006.
                    $lastpageseen = $lastbranchtable->pageid;
                }
            }
        }
        return $lastpageseen;
    }

    /**
     * Return the number of retries in a opendsa_activity for a given user.
     *
     * @param  int $userid the user id
     * @return int the retries count
     * @since  Moodle 3.3
     */
    public function count_user_retries($userid) {
        global $DB;

        return $DB->count_records('opendsa_activity_grades', array("opendsa_activity_id" => $this->properties->id, "userid" => $userid));
    }

    /**
     * Check if a user left a timed session.
     *
     * @param int $retriescount the number of retries for the opendsa_activity (the last retry number).
     * @return true if the user left the timed session
     * @since  Moodle 3.3
     */
    public function left_during_timed_session($retriescount) {
        global $DB, $USER;

        $conditions = array('opendsa_activity_id' => $this->properties->id, 'userid' => $USER->id, 'retry' => $retriescount);
        return $DB->count_records('opendsa_activity_attempts', $conditions) > 0 || $DB->count_records('opendsa_activity_branch', $conditions) > 0;
    }

    /**
     * Trigger module viewed event and set the module viewed for completion.
     *
     * @since  Moodle 3.3
     */
    public function set_module_viewed() {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        // Trigger module viewed event.
        $event = \mod_opendsa_activity\event\course_module_viewed::create(array(
            'objectid' => $this->properties->id,
            'context' => $this->get_context()
        ));
        $event->add_record_snapshot('course_modules', $this->get_cm());
        $event->add_record_snapshot('course', $this->get_courserecord());
        $event->trigger();

        // Mark as viewed.
        $completion = new completion_info($this->get_courserecord());
        $completion->set_module_viewed($this->get_cm());
    }

    /**
     * Return the timers in the current opendsa_activity for the given user.
     *
     * @param  int      $userid    the user id
     * @param  string   $sort      an order to sort the results in (optional, a valid SQL ORDER BY parameter).
     * @param  string   $fields    a comma separated list of fields to return
     * @param  int      $limitfrom return a subset of records, starting at this point (optional).
     * @param  int      $limitnum  return a subset comprising this many records in total (optional, required if $limitfrom is set).
     * @return array    list of timers for the given user in the opendsa_activity
     * @since  Moodle 3.3
     */
    public function get_user_timers($userid = null, $sort = '', $fields = '*', $limitfrom = 0, $limitnum = 0) {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        $params = array('opendsa_activity_id' => $this->properties->id, 'userid' => $userid);
        return $DB->get_records('opendsa_activity_timer', $params, $sort, $fields, $limitfrom, $limitnum);
    }

    /**
     * Check if the user is out of time in a timed opendsa_activity.
     *
     * @param  stdClass $timer timer object
     * @return bool True if the user is on time, false is the user ran out of time
     * @since  Moodle 3.3
     */
    public function check_time($timer) {
        if ($this->properties->timelimit) {
            $timeleft = $timer->starttime + $this->properties->timelimit - time();
            if ($timeleft <= 0) {
                // Out of time.
                $this->add_message(get_string('eolstudentoutoftime', 'opendsa_activity'));
                return false;
            } else if ($timeleft < 60) {
                // One minute warning.
                $this->add_message(get_string('studentoneminwarning', 'opendsa_activity'));
            }
        }
        return true;
    }

    /**
     * Add different informative messages to the given page.
     *
     * @param opendsa_activity_page $page page object
     * @param reviewmode $bool whether we are in review mode or not
     * @since  Moodle 3.3
     */
    public function add_messages_on_page_view(opendsa_activity_page $page, $reviewmode) {
        global $DB, $USER;

        if (!$this->can_manage()) {
            if ($page->qtype == OPENDSA_ACTIVITY_PAGE_BRANCHTABLE && $this->properties->minquestions) {
                // Tell student how many questions they have seen, how many are required and their grade.
                $ntries = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id));
                $gradeinfo = opendsa_activity_grade($this, $ntries);
                if ($gradeinfo->attempts) {
                    if ($gradeinfo->nquestions < $this->properties->minquestions) {
                        $a = new stdClass;
                        $a->nquestions   = $gradeinfo->nquestions;
                        $a->minquestions = $this->properties->minquestions;
                        $this->add_message(get_string('numberofpagesviewednotice', 'opendsa_activity', $a));
                    }

                    if (!$reviewmode && $this->properties->ongoing) {
                        $this->add_message(get_string("numberofcorrectanswers", "opendsa_activity", $gradeinfo->earned), 'notify');
                        if ($this->properties->grade != GRADE_TYPE_NONE) {
                            $a = new stdClass;
                            $a->grade = number_format($gradeinfo->grade * $this->properties->grade / 100, 1);
                            $a->total = $this->properties->grade;
                            $this->add_message(get_string('yourcurrentgradeisoutof', 'opendsa_activity', $a), 'notify');
                        }
                    }
                }
            }
        } else {
            if ($this->properties->timelimit) {
                $this->add_message(get_string('teachertimerwarning', 'opendsa_activity'));
            }
            if (opendsa_activity_display_teacher_warning($this)) {
                // This is the warning msg for teachers to inform them that cluster
                // and unseen does not work while logged in as a teacher.
                $warningvars = new stdClass();
                $warningvars->cluster = get_string('clusterjump', 'opendsa_activity');
                $warningvars->unseen = get_string('unseenpageinbranch', 'opendsa_activity');
                $this->add_message(get_string('teacherjumpwarning', 'opendsa_activity', $warningvars));
            }
        }
    }

    /**
     * Get the ongoing score message for the user (depending on the user permission and opendsa_activity settings).
     *
     * @return str the ongoing score message
     * @since  Moodle 3.3
     */
    public function get_ongoing_score_message() {
        global $USER, $DB;

        $context = $this->get_context();

        if (has_capability('mod/opendsa_activity:manage', $context)) {
            return get_string('teacherongoingwarning', 'opendsa_activity');
        } else {
            $ntries = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id));
            if (isset($USER->modattempts[$this->properties->id])) {
                $ntries--;
            }
            $gradeinfo = opendsa_activity_grade($this, $ntries);
            $a = new stdClass;
            if ($this->properties->custom) {
                $a->score = $gradeinfo->earned;
                $a->currenthigh = $gradeinfo->total;
                return get_string("ongoingcustom", "opendsa_activity", $a);
            } else {
                $a->correct = $gradeinfo->earned;
                $a->viewed = $gradeinfo->attempts;
                return get_string("ongoingnormal", "opendsa_activity", $a);
            }
        }
    }

    /**
     * Calculate the progress of the current user in the opendsa_activity.
     *
     * @return int the progress (scale 0-100)
     * @since  Moodle 3.3
     */
    public function calculate_progress() {
        global $USER, $DB;

        // Check if the user is reviewing the attempt.
        if (isset($USER->modattempts[$this->properties->id])) {
            return 100;
        }

        // All of the opendsa_activity pages.
        $pages = $this->load_all_pages();
        foreach ($pages as $page) {
            if ($page->prevpageid == 0) {
                $pageid = $page->id;  // Find the first page id.
                break;
            }
        }

        // Current attempt number.
        if (!$ntries = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id))) {
            $ntries = 0;  // May not be necessary.
        }

        $viewedpageids = array();
        if ($attempts = $this->get_attempts($ntries, false)) {
            foreach ($attempts as $attempt) {
                $viewedpageids[$attempt->pageid] = $attempt;
            }
        }

        $viewedbranches = array();
        // Collect all of the branch tables viewed.
        if ($branches = $this->get_content_pages_viewed($ntries, $USER->id, 'timeseen ASC', 'id, pageid')) {
            foreach ($branches as $branch) {
                $viewedbranches[$branch->pageid] = $branch;
            }
            $viewedpageids = array_merge($viewedpageids, $viewedbranches);
        }

        // Filter out the following pages:
        // - End of Cluster
        // - End of Branch
        // - Pages found inside of Clusters
        // Do not filter out Cluster Page(s) because we count a cluster as one.
        // By keeping the cluster page, we get our 1.
        $validpages = array();
        while ($pageid != 0) {
            $pageid = $pages[$pageid]->valid_page_and_view($validpages, $viewedpageids);
        }

        // Progress calculation as a percent.
        $progress = round(count($viewedpageids) / count($validpages), 2) * 100;
        return (int) $progress;
    }

    /**
     * Calculate the correct page and prepare contents for a given page id (could be a page jump id).
     *
     * @param  int $pageid the given page id
     * @param  mod_opendsa_activity_renderer $opendsa_activityoutput the opendsa_activity output rendered
     * @param  bool $reviewmode whether we are in review mode or not
     * @param  bool $redirect  Optional, default to true. Set to false to avoid redirection and return the page to redirect.
     * @return array the page object and contents
     * @throws moodle_exception
     * @since  Moodle 3.3
     */
    public function prepare_page_and_contents($pageid, $opendsa_activityoutput, $reviewmode, $redirect = true) {
        global $USER, $CFG;

        $page = $this->load_page($pageid);
        // Check if the page is of a special type and if so take any nessecary action.
        $newpageid = $page->callback_on_view($this->can_manage(), $redirect);

        // Avoid redirections returning the jump to special page id.
        if (!$redirect && is_numeric($newpageid) && $newpageid < 0) {
            return array($newpageid, null, null);
        }

        if (is_numeric($newpageid)) {
            $page = $this->load_page($newpageid);
        }

        // Add different informative messages to the given page.
        $this->add_messages_on_page_view($page, $reviewmode);

        if (is_array($page->answers) && count($page->answers) > 0) {
            // This is for modattempts option.  Find the users previous answer to this page,
            // and then display it below in answer processing.
            if (isset($USER->modattempts[$this->properties->id])) {
                $retries = $this->count_user_retries($USER->id);
                if (!$attempts = $this->get_attempts($retries - 1, false, $page->id)) {
                    throw new moodle_exception('cannotfindpreattempt', 'opendsa_activity');
                }
                $attempt = end($attempts);
                $USER->modattempts[$this->properties->id] = $attempt;
            } else {
                $attempt = false;
            }
            $opendsa_activitycontent = $opendsa_activityoutput->display_page($this, $page, $attempt);
        } else {
            require_once($CFG->dirroot . '/mod/opendsa_activity/view_form.php');
            $data = new stdClass;
            $data->id = $this->get_cm()->id;
            $data->pageid = $page->id;
            $data->newpageid = $this->get_next_page($page->nextpageid);

            $customdata = array(
                'title'     => $page->title,
                'contents'  => $page->get_contents()
            );
            $mform = new opendsa_activity_page_without_answers($CFG->wwwroot.'/mod/opendsa_activity/continue.php', $customdata);
            $mform->set_data($data);
            ob_start();
            $mform->display();
            $opendsa_activitycontent = ob_get_contents();
            ob_end_clean();
        }

        return array($page->id, $page, $opendsa_activitycontent);
    }

    /**
     * This returns a real page id to jump to (or OPENDSA_ACTIVITY_EOL) after processing page responses.
     *
     * @param  opendsa_activity_page $page      opendsa_activity page
     * @param  int         $newpageid the new page id
     * @return int the real page to jump to (or end of opendsa_activity)
     * @since  Moodle 3.3
     */
    public function calculate_new_page_on_jump(opendsa_activity_page $page, $newpageid) {
        global $USER, $DB;

        $canmanage = $this->can_manage();

        if (isset($USER->modattempts[$this->properties->id])) {
            // Make sure if the student is reviewing, that he/she sees the same pages/page path that he/she saw the first time.
            if ($USER->modattempts[$this->properties->id]->pageid == $page->id && $page->nextpageid == 0) {
                // Remember, this session variable holds the pageid of the last page that the user saw.
                $newpageid = OPENDSA_ACTIVITY_EOL;
            } else {
                $nretakes = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id));
                $nretakes--; // Make sure we are looking at the right try.
                $attempts = $DB->get_records("opendsa_activity_attempts", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id, "retry" => $nretakes), "timeseen", "id, pageid");
                $found = false;
                $temppageid = 0;
                // Make sure that the newpageid always defaults to something valid.
                $newpageid = OPENDSA_ACTIVITY_EOL;
                foreach ($attempts as $attempt) {
                    if ($found && $temppageid != $attempt->pageid) {
                        // Now try to find the next page, make sure next few attempts do no belong to current page.
                        $newpageid = $attempt->pageid;
                        break;
                    }
                    if ($attempt->pageid == $page->id) {
                        $found = true; // If found current page.
                        $temppageid = $attempt->pageid;
                    }
                }
            }
        } else if ($newpageid != OPENDSA_ACTIVITY_CLUSTERJUMP && $page->id != 0 && $newpageid > 0) {
            // Going to check to see if the page that the user is going to view next, is a cluster page.
            // If so, dont display, go into the cluster.
            // The $newpageid > 0 is used to filter out all of the negative code jumps.
            $newpage = $this->load_page($newpageid);
            if ($overridenewpageid = $newpage->override_next_page($newpageid)) {
                $newpageid = $overridenewpageid;
            }
        } else if ($newpageid == OPENDSA_ACTIVITY_UNSEENBRANCHPAGE) {
            if ($canmanage) {
                if ($page->nextpageid == 0) {
                    $newpageid = OPENDSA_ACTIVITY_EOL;
                } else {
                    $newpageid = $page->nextpageid;
                }
            } else {
                $newpageid = opendsa_activity_unseen_question_jump($this, $USER->id, $page->id);
            }
        } else if ($newpageid == OPENDSA_ACTIVITY_PREVIOUSPAGE) {
            $newpageid = $page->prevpageid;
        } else if ($newpageid == OPENDSA_ACTIVITY_RANDOMPAGE) {
            $newpageid = opendsa_activity_random_question_jump($this, $page->id);
        } else if ($newpageid == OPENDSA_ACTIVITY_CLUSTERJUMP) {
            if ($canmanage) {
                if ($page->nextpageid == 0) {  // If teacher, go to next page.
                    $newpageid = OPENDSA_ACTIVITY_EOL;
                } else {
                    $newpageid = $page->nextpageid;
                }
            } else {
                $newpageid = $this->cluster_jump($page->id);
            }
        } else if ($newpageid == 0) {
            $newpageid = $page->id;
        } else if ($newpageid == OPENDSA_ACTIVITY_NEXTPAGE) {
            $newpageid = $this->get_next_page($page->nextpageid);
        }

        return $newpageid;
    }

    /**
     * Process page responses.
     *
     * @param opendsa_activity_page $page page object
     * @since  Moodle 3.3
     */
    public function process_page_responses(opendsa_activity_page $page) {
        $context = $this->get_context();

        // Check the page has answers [MDL-25632].
        if (count($page->answers) > 0) {
            $result = $page->record_attempt($context);
        } else {
            // The page has no answers so we will just progress to the next page in the
            // sequence (as set by newpageid).
            $result = new stdClass;
            $result->newpageid       = optional_param('newpageid', $page->nextpageid, PARAM_INT);
            $result->nodefaultresponse  = true;
            $result->inmediatejump = false;
        }

        if ($result->inmediatejump) {
            return $result;
        }

        $result->newpageid = $this->calculate_new_page_on_jump($page, $result->newpageid);

        return $result;
    }

    /**
     * Add different informative messages to the given page.
     *
     * @param opendsa_activity_page $page page object
     * @param stdClass $result the page processing result object
     * @param bool $reviewmode whether we are in review mode or not
     * @since  Moodle 3.3
     */
    public function add_messages_on_page_process(opendsa_activity_page $page, $result, $reviewmode) {

        if ($this->can_manage()) {
            // This is the warning msg for teachers to inform them that cluster and unseen does not work while logged in as a teacher.
            if (opendsa_activity_display_teacher_warning($this)) {
                $warningvars = new stdClass();
                $warningvars->cluster = get_string("clusterjump", "opendsa_activity");
                $warningvars->unseen = get_string("unseenpageinbranch", "opendsa_activity");
                $this->add_message(get_string("teacherjumpwarning", "opendsa_activity", $warningvars));
            }
            // Inform teacher that s/he will not see the timer.
            if ($this->properties->timelimit) {
                $this->add_message(get_string("teachertimerwarning", "opendsa_activity"));
            }
        }
        // Report attempts remaining.
        if ($result->attemptsremaining != 0 && $this->properties->review && !$reviewmode) {
            $this->add_message(get_string('attemptsremaining', 'opendsa_activity', $result->attemptsremaining));
        }
    }

    /**
     * Process and return all the information for the end of opendsa_activity page.
     *
     * @param string $outoftime used to check to see if the student ran out of time
     * @return stdclass an object with all the page data ready for rendering
     * @since  Moodle 3.3
     */
    public function process_eol_page($outoftime) {
        global $DB, $USER;

        $course = $this->get_courserecord();
        $cm = $this->get_cm();
        $canmanage = $this->can_manage();

        // Init all the possible fields and values.
        $data = (object) array(
            'gradeopendsa_activity' => true,
            'notenoughtimespent' => false,
            'numberofpagesviewed' => false,
            'youshouldview' => false,
            'numberofcorrectanswers' => false,
            'displayscorewithessays' => false,
            'displayscorewithoutessays' => false,
            'yourcurrentgradeisoutof' => false,
            'eolstudentoutoftimenoanswers' => false,
            'welldone' => false,
            'progressbar' => false,
            'displayofgrade' => false,
            'reviewopendsa_activity' => false,
            'modattemptsnoteacher' => false,
            'activitylink' => false,
            'progresscompleted' => false,
        );

        $ntries = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id));
        if (isset($USER->modattempts[$this->properties->id])) {
            $ntries--;  // Need to look at the old attempts :).
        }

        $gradeinfo = opendsa_activity_grade($this, $ntries);
        $data->gradeinfo = $gradeinfo;
        if ($this->properties->custom && !$canmanage) {
            // Before we calculate the custom score make sure they answered the minimum
            // number of questions. We only need to do this for custom scoring as we can
            // not get the miniumum score the user should achieve. If we are not using
            // custom scoring (so all questions are valued as 1) then we simply check if
            // they answered more than the minimum questions, if not, we mark it out of the
            // number specified in the minimum questions setting - which is done in opendsa_activity_grade().
            // Get the number of answers given.
            if ($gradeinfo->nquestions < $this->properties->minquestions) {
                $data->gradeopendsa_activity = false;
                $a = new stdClass;
                $a->nquestions = $gradeinfo->nquestions;
                $a->minquestions = $this->properties->minquestions;
                $this->add_message(get_string('numberofpagesviewednotice', 'opendsa_activity', $a));
            }
        }

        if (!$canmanage) {
            if ($data->gradeopendsa_activity) {
                // Store this now before any modifications to pages viewed.
                $progresscompleted = $this->calculate_progress();

                // Update the clock / get time information for this user.
                $this->stop_timer();

                // Update completion state.
                $completion = new completion_info($course);
                if ($completion->is_enabled($cm) && $this->properties->completionendreached) {
                    $completion->update_state($cm, COMPLETION_COMPLETE);
                }

                if ($this->properties->completiontimespent > 0) {
                    $duration = $DB->get_field_sql(
                        "SELECT SUM(opendsa_activitytime - starttime)
                                       FROM {opendsa_activity_timer}
                                      WHERE opendsa_activity_id = :opendsa_activity_id
                                        AND userid = :userid",
                        array('userid' => $USER->id, 'opendsa_activity_id' => $this->properties->id));
                    if (!$duration) {
                        $duration = 0;
                    }

                    // If student has not spend enough time in the opendsa_activity, display a message.
                    if ($duration < $this->properties->completiontimespent) {
                        $a = new stdClass;
                        $a->timespentraw = $duration;
                        $a->timespent = format_time($duration);
                        $a->timerequiredraw = $this->properties->completiontimespent;
                        $a->timerequired = format_time($this->properties->completiontimespent);
                        $data->notenoughtimespent = $a;
                    }
                }

                if ($gradeinfo->attempts) {
                    if (!$this->properties->custom) {
                        $data->numberofpagesviewed = $gradeinfo->nquestions;
                        if ($this->properties->minquestions) {
                            if ($gradeinfo->nquestions < $this->properties->minquestions) {
                                $data->youshouldview = $this->properties->minquestions;
                            }
                        }
                        $data->numberofcorrectanswers = $gradeinfo->earned;
                    }
                    $a = new stdClass;
                    $a->score = $gradeinfo->earned;
                    $a->grade = $gradeinfo->total;
                    if ($gradeinfo->nmanual) {
                        $a->tempmaxgrade = $gradeinfo->total - $gradeinfo->manualpoints;
                        $a->essayquestions = $gradeinfo->nmanual;
                        $data->displayscorewithessays = $a;
                    } else {
                        $data->displayscorewithoutessays = $a;
                    }
                    if ($this->properties->grade != GRADE_TYPE_NONE) {
                        $a = new stdClass;
                        $a->grade = number_format($gradeinfo->grade * $this->properties->grade / 100, 1);
                        $a->total = $this->properties->grade;
                        $data->yourcurrentgradeisoutof = $a;
                    }

                    $grade = new stdClass();
                    $grade->opendsa_activity_id = $this->properties->id;
                    $grade->userid = $USER->id;
                    $grade->grade = $gradeinfo->grade;
                    $grade->completed = time();
                    if (isset($USER->modattempts[$this->properties->id])) { // If reviewing, make sure update old grade record.
                        if (!$grades = $DB->get_records("opendsa_activity_grades",
                            array("opendsa_activity_id" => $this->properties->id, "userid" => $USER->id), "completed DESC", '*', 0, 1)) {
                            throw new moodle_exception('cannotfindgrade', 'opendsa_activity');
                        }
                        $oldgrade = array_shift($grades);
                        $grade->id = $oldgrade->id;
                        $DB->update_record("opendsa_activity_grades", $grade);
                    } else {
                        $newgradeid = $DB->insert_record("opendsa_activity_grades", $grade);
                    }
                } else {
                    if ($this->properties->timelimit) {
                        if ($outoftime == 'normal') {
                            $grade = new stdClass();
                            $grade->opendsa_activity_id = $this->properties->id;
                            $grade->userid = $USER->id;
                            $grade->grade = 0;
                            $grade->completed = time();
                            $newgradeid = $DB->insert_record("opendsa_activity_grades", $grade);
                            $data->eolstudentoutoftimenoanswers = true;
                        }
                    } else {
                        $data->welldone = true;
                    }
                }

                // Update central gradebook.
                opendsa_activity_update_grades($this, $USER->id);
                $data->progresscompleted = $progresscompleted;
            }
        } else {
            // Display for teacher.
            if ($this->properties->grade != GRADE_TYPE_NONE) {
                $data->displayofgrade = true;
            }
        }

        if ($this->properties->modattempts && !$canmanage) {
            // Make sure if the student is reviewing, that he/she sees the same pages/page path that he/she saw the first time
            // look at the attempt records to find the first QUESTION page that the user answered, then use that page id
            // to pass to view again.  This is slick cause it wont call the empty($pageid) code
            // $ntries is decremented above.
            if (!$attempts = $this->get_attempts($ntries)) {
                $attempts = array();
                $url = new moodle_url('/mod/opendsa_activity/view.php', array('id' => $cm->id));
            } else {
                $firstattempt = current($attempts);
                $pageid = $firstattempt->pageid;
                // If the student wishes to review, need to know the last question page that the student answered.
                // This will help to make sure that the student can leave the opendsa_activity via pushing the continue button.
                $lastattempt = end($attempts);
                $USER->modattempts[$this->properties->id] = $lastattempt->pageid;

                $url = new moodle_url('/mod/opendsa_activity/view.php', array('id' => $cm->id, 'pageid' => $pageid));
            }
            $data->reviewopendsa_activity = $url->out(false);
        } else if ($this->properties->modattempts && $canmanage) {
            $data->modattemptsnoteacher = true;
        }

        if ($this->properties->activitylink) {
            $data->activitylink = $this->link_for_activitylink();
        }
        return $data;
    }
}


/**
 * Abstract class to provide a core functions to the all opendsa_activity classes
 *
 * This class should be abstracted by ALL classes with the opendsa_activity module to ensure
 * that all classes within this module can be interacted with in the same way.
 *
 * This class provides the user with a basic properties array that can be fetched
 * or set via magic methods, or alternatively by defining methods get_blah() or
 * set_blah() within the extending object.
 *
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class opendsa_activity_base {

    /**
     * An object containing properties
     * @var stdClass
     */
    protected $properties;

    /**
     * The constructor
     * @param stdClass $properties
     */
    public function __construct($properties) {
        $this->properties = (object)$properties;
    }

    /**
     * Magic property method
     *
     * Attempts to call a set_$key method if one exists otherwise falls back
     * to simply set the property
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value) {
        if (method_exists($this, 'set_'.$key)) {
            $this->{'set_'.$key}($value);
        }
        $this->properties->{$key} = $value;
    }

    /**
     * Magic get method
     *
     * Attempts to call a get_$key method to return the property and ralls over
     * to return the raw property
     *
     * @param str $key
     * @return mixed
     */
    public function __get($key) {
        if (method_exists($this, 'get_'.$key)) {
            return $this->{'get_'.$key}();
        }
        return $this->properties->{$key};
    }

    /**
     * Stupid PHP needs an isset magic method if you use the get magic method and
     * still want empty calls to work.... blah ~!
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key) {
        if (method_exists($this, 'get_'.$key)) {
            $val = $this->{'get_'.$key}();
            return !empty($val);
        }
        return !empty($this->properties->{$key});
    }

    //NOTE: E_STRICT does not allow to change function signature!

    /**
     * If implemented should create a new instance, save it in the DB and return it
     */
    //public static function create() {}
    /**
     * If implemented should load an instance from the DB and return it
     */
    //public static function load() {}
    /**
     * Fetches all of the properties of the object
     * @return stdClass
     */
    public function properties() {
        return $this->properties;
    }
}


/**
 * Abstract class representation of a page associated with a opendsa_activity.
 *
 * This class should MUST be extended by all specialised page types defined in
 * mod/opendsa_activity/pagetypes/.
 * There are a handful of abstract methods that need to be defined as well as
 * severl methods that can optionally be defined in order to make the page type
 * operate in the desired way
 *
 * Database properties
 * @property int $id The id of this opendsa_activity page
 * @property int $opendsa_activity_id The id of the opendsa_activity this page belongs to
 * @property int $prevpageid The id of the page before this one
 * @property int $nextpageid The id of the next page in the page sequence
 * @property int $qtype Identifies the page type of this page
 * @property int $qoption Used to record page type specific options
 * @property int $layout Used to record page specific layout selections
 * @property int $display Used to record page specific display selections
 * @property int $timecreated Timestamp for when the page was created
 * @property int $timemodified Timestamp for when the page was last modified
 * @property string $title The title of this page
 * @property string $contents The rich content shown to describe the page
 * @property int $contentsformat The format of the contents field
 *
 * Calculated properties
 * @property-read array $answers An array of answers for this page
 * @property-read bool $displayinmenublock Toggles display in the left menu block
 * @property-read array $jumps An array containing all the jumps this page uses
 * @property-read opendsa_activity $opendsa_activity The opendsa_activity this page belongs to
 * @property-read int $type The type of the page [question | structure]
 * @property-read typeid The unique identifier for the page type
 * @property-read typestring The string that describes this page type
 *
 * @abstract
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class opendsa_activity_page extends opendsa_activity_base {

    /**
     * A reference to the opendsa_activity this page belongs to
     * @var opendsa_activity
     */
    protected $opendsa_activity = null;
    /**
     * Contains the answers to this opendsa_activity_page once loaded
     * @var null|array
     */
    protected $answers = null;
    /**
     * This sets the type of the page, can be one of the constants defined below
     * @var int
     */
    protected $type = 0;

    /**
     * Constants used to identify the type of the page
     */
    const TYPE_QUESTION = 0;
    const TYPE_STRUCTURE = 1;

    /**
     * Constant used as a delimiter when parsing multianswer questions
     */
    const MULTIANSWER_DELIMITER = '@^#|';

    /**
     * This method should return the integer used to identify the page type within
     * the database and throughout code. This maps back to the defines used in 1.x
     * @abstract
     * @return int
     */
    abstract protected function get_typeid();
    /**
     * This method should return the string that describes the pagetype
     * @abstract
     * @return string
     */
    abstract protected function get_typestring();

    /**
     * This method gets called to display the page to the user taking the opendsa_activity
     * @abstract
     * @param object $renderer
     * @param object $attempt
     * @return string
     */
    abstract public function display($renderer, $attempt);

    /**
     * Creates a new opendsa_activity_page within the database and returns the correct pagetype
     * object to use to interact with the new opendsa_activity
     *
     * @final
     * @static
     * @param object $properties
     * @param opendsa_activity $opendsa_activity
     * @return opendsa_activity_page Specialised object that extends opendsa_activity_page
     */
    final public static function create($properties, opendsa_activity $opendsa_activity, $context, $maxbytes) {
        global $DB;
        $newpage = new stdClass;
        $newpage->title = $properties->title;
        $newpage->contents = $properties->contents_editor['text'];
        $newpage->contentsformat = $properties->contents_editor['format'];
        $newpage->opendsa_activity_id = $opendsa_activity->id;
        $newpage->timecreated = time();
        $newpage->qtype = $properties->qtype;
        $newpage->qoption = (isset($properties->qoption))?1:0;
        $newpage->layout = (isset($properties->layout))?1:0;
        $newpage->display = (isset($properties->display))?1:0;
        $newpage->prevpageid = 0; // this is a first page
        $newpage->nextpageid = 0; // this is the only page

        if ($properties->pageid) {
            $prevpage = $DB->get_record("opendsa_activity_pages", array("id" => $properties->pageid), 'id, nextpageid');
            if (!$prevpage) {
                print_error('cannotfindpages', 'opendsa_activity');
            }
            $newpage->prevpageid = $prevpage->id;
            $newpage->nextpageid = $prevpage->nextpageid;
        } else {
            $nextpage = $DB->get_record('opendsa_activity_pages', array('opendsa_activity_id'=>$opendsa_activity->id, 'prevpageid'=>0), 'id');
            if ($nextpage) {
                // This is the first page, there are existing pages put this at the start
                $newpage->nextpageid = $nextpage->id;
            }
        }

        $newpage->id = $DB->insert_record("opendsa_activity_pages", $newpage);

        $editor = new stdClass;
        $editor->id = $newpage->id;
        $editor->contents_editor = $properties->contents_editor;
        $editor = file_postupdate_standard_editor($editor, 'contents', array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$maxbytes), $context, 'mod_opendsa_activity', 'page_contents', $editor->id);
        $DB->update_record("opendsa_activity_pages", $editor);

        if ($newpage->prevpageid > 0) {
            $DB->set_field("opendsa_activity_pages", "nextpageid", $newpage->id, array("id" => $newpage->prevpageid));
        }
        if ($newpage->nextpageid > 0) {
            $DB->set_field("opendsa_activity_pages", "prevpageid", $newpage->id, array("id" => $newpage->nextpageid));
        }

        $page = opendsa_activity_page::load($newpage, $opendsa_activity);
        $page->create_answers($properties);

        // Trigger an event: page created.
        $eventparams = array(
            'context' => $context,
            'objectid' => $newpage->id,
            'other' => array(
                'pagetype' => $page->get_typestring()
                )
            );
        $event = \mod_opendsa_activity\event\page_created::create($eventparams);
        $snapshot = clone($newpage);
        $snapshot->timemodified = 0;
        $event->add_record_snapshot('opendsa_activity_pages', $snapshot);
        $event->trigger();

        $opendsa_activity->add_message(get_string('insertedpage', 'opendsa_activity').': '.format_string($newpage->title, true), 'notifysuccess');

        return $page;
    }

    /**
     * This method loads a page object from the database and returns it as a
     * specialised object that extends opendsa_activity_page
     *
     * @final
     * @static
     * @param int $id
     * @param opendsa_activity $opendsa_activity
     * @return opendsa_activity_page Specialised opendsa_activity_page object
     */
    final public static function load($id, opendsa_activity $opendsa_activity) {
        global $DB;

        if (is_object($id) && !empty($id->qtype)) {
            $page = $id;
        } else {
            $page = $DB->get_record("opendsa_activity_pages", array("id" => $id));
            if (!$page) {
                print_error('cannotfindpages', 'opendsa_activity');
            }
        }
        $manager = opendsa_activity_page_type_manager::get($opendsa_activity);

        $class = 'opendsa_activity_page_type_'.$manager->get_page_type_idstring($page->qtype);
        if (!class_exists($class)) {
            $class = 'opendsa_activity_page';
        }

        return new $class($page, $opendsa_activity);
    }

    /**
     * Deletes a opendsa_activity_page from the database as well as any associated records.
     * @final
     * @return bool
     */
    final public function delete() {
        global $DB;

        $cm = get_coursemodule_from_instance('opendsa_activity', $this->opendsa_activity->id, $this->opendsa_activity->course);
        $context = context_module::instance($cm->id);

        // Delete files associated with attempts.
        $fs = get_file_storage();
        if ($attempts = $DB->get_records('opendsa_activity_attempts', array("pageid" => $this->properties->id))) {
            foreach ($attempts as $attempt) {
                $fs->delete_area_files($context->id, 'mod_opendsa_activity', 'essay_responses', $attempt->id);
                $fs->delete_area_files($context->id, 'mod_opendsa_activity', 'essay_answers', $attempt->id);
            }
        }

        // Then delete all the associated records...
        $DB->delete_records("opendsa_activity_attempts", array("pageid" => $this->properties->id));

        $DB->delete_records("opendsa_activity_branch", array("pageid" => $this->properties->id));

        // Delete files related to answers and responses.
        if ($answers = $DB->get_records("opendsa_activity_answers", array("pageid" => $this->properties->id))) {
            foreach ($answers as $answer) {
                $fs->delete_area_files($context->id, 'mod_opendsa_activity', 'page_answers', $answer->id);
                $fs->delete_area_files($context->id, 'mod_opendsa_activity', 'page_responses', $answer->id);
            }
        }

        // ...now delete the answers...
        $DB->delete_records("opendsa_activity_answers", array("pageid" => $this->properties->id));
        // ..and the page itself
        $DB->delete_records("opendsa_activity_pages", array("id" => $this->properties->id));

        // Trigger an event: page deleted.
        $eventparams = array(
            'context' => $context,
            'objectid' => $this->properties->id,
            'other' => array(
                'pagetype' => $this->get_typestring()
                )
            );
        $event = \mod_opendsa_activity\event\page_deleted::create($eventparams);
        $event->add_record_snapshot('opendsa_activity_pages', $this->properties);
        $event->trigger();

        // Delete files associated with this page.
        $fs->delete_area_files($context->id, 'mod_opendsa_activity', 'page_contents', $this->properties->id);

        // repair the hole in the linkage
        if (!$this->properties->prevpageid && !$this->properties->nextpageid) {
            //This is the only page, no repair needed
        } elseif (!$this->properties->prevpageid) {
            // this is the first page...
            $page = $this->opendsa_activity->load_page($this->properties->nextpageid);
            $page->move(null, 0);
        } elseif (!$this->properties->nextpageid) {
            // this is the last page...
            $page = $this->opendsa_activity->load_page($this->properties->prevpageid);
            $page->move(0);
        } else {
            // page is in the middle...
            $prevpage = $this->opendsa_activity->load_page($this->properties->prevpageid);
            $nextpage = $this->opendsa_activity->load_page($this->properties->nextpageid);

            $prevpage->move($nextpage->id);
            $nextpage->move(null, $prevpage->id);
        }
        return true;
    }

    /**
     * Moves a page by updating its nextpageid and prevpageid values within
     * the database
     *
     * @final
     * @param int $nextpageid
     * @param int $prevpageid
     */
    final public function move($nextpageid=null, $prevpageid=null) {
        global $DB;
        if ($nextpageid === null) {
            $nextpageid = $this->properties->nextpageid;
        }
        if ($prevpageid === null) {
            $prevpageid = $this->properties->prevpageid;
        }
        $obj = new stdClass;
        $obj->id = $this->properties->id;
        $obj->prevpageid = $prevpageid;
        $obj->nextpageid = $nextpageid;
        $DB->update_record('opendsa_activity_pages', $obj);
    }

    /**
     * Returns the answers that are associated with this page in the database
     *
     * @final
     * @return array
     */
    final public function get_answers() {
        global $DB;
        if ($this->answers === null) {
            $this->answers = array();
            $answers = $DB->get_records('opendsa_activity_answers', array('pageid'=>$this->properties->id, 'opendsa_activity_id'=>$this->opendsa_activity->id), 'id');
            if (!$answers) {
                // It is possible that a opendsa_activity upgraded from Moodle 1.9 still
                // contains questions without any answers [MDL-25632].
                // debugging(get_string('cannotfindanswer', 'opendsa_activity'));
                return array();
            }
            foreach ($answers as $answer) {
                $this->answers[count($this->answers)] = new opendsa_activity_page_answer($answer);
            }
        }
        return $this->answers;
    }

    /**
     * Returns the opendsa_activity this page is associated with
     * @final
     * @return opendsa_activity
     */
    final protected function get_opendsa_activity() {
        return $this->opendsa_activity;
    }

    /**
     * Returns the type of page this is. Not to be confused with page type
     * @final
     * @return int
     */
    final protected function get_type() {
        return $this->type;
    }

    /**
     * Records an attempt at this page
     *
     * @final
     * @global moodle_database $DB
     * @param stdClass $context
     * @return stdClass Returns the result of the attempt
     */
    final public function record_attempt($context) {
        global $DB, $USER, $OUTPUT, $PAGE;

        /**
         * This should be overridden by each page type to actually check the response
         * against what ever custom criteria they have defined
         */
        $result = $this->check_answer();

        // Processes inmediate jumps.
        if ($result->inmediatejump) {
            return $result;
        }

        $result->attemptsremaining  = 0;
        $result->maxattemptsreached = false;

        if ($result->noanswer) {
            $result->newpageid = $this->properties->id; // display same page again
            $result->feedback  = get_string('noanswer', 'opendsa_activity');
        } else {
            if (!has_capability('mod/opendsa_activity:manage', $context)) {
                $nretakes = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id"=>$this->opendsa_activity->id, "userid"=>$USER->id));

                // Get the number of attempts that have been made on this question for this student and retake,
                $nattempts = $DB->count_records('opendsa_activity_attempts', array('opendsa_activity_id' => $this->opendsa_activity->id,
                    'userid' => $USER->id, 'pageid' => $this->properties->id, 'retry' => $nretakes));

                // Check if they have reached (or exceeded) the maximum number of attempts allowed.
                if ($nattempts >= $this->opendsa_activity->maxattempts) {
                    $result->maxattemptsreached = true;
                    $result->feedback = get_string('maximumnumberofattemptsreached', 'opendsa_activity');
                    $result->newpageid = $this->opendsa_activity->get_next_page($this->properties->nextpageid);
                    return $result;
                }

                // record student's attempt
                $attempt = new stdClass;
                $attempt->opendsa_activity_id = $this->opendsa_activity->id;
                $attempt->pageid = $this->properties->id;
                $attempt->userid = $USER->id;
                $attempt->answerid = $result->answerid;
                $attempt->retry = $nretakes;
                $attempt->correct = $result->correctanswer;
                if($result->userresponse !== null) {
                    $attempt->useranswer = $result->userresponse;
                }

                $attempt->timeseen = time();
                // if allow modattempts, then update the old attempt record, otherwise, insert new answer record
                $userisreviewing = false;
                if (isset($USER->modattempts[$this->opendsa_activity->id])) {
                    $attempt->retry = $nretakes - 1; // they are going through on review, $nretakes will be too high
                    $userisreviewing = true;
                }

                // Only insert a record if we are not reviewing the opendsa_activity.
                if (!$userisreviewing) {
                    if ($this->opendsa_activity->retake || (!$this->opendsa_activity->retake && $nretakes == 0)) {
                        $attempt->id = $DB->insert_record("opendsa_activity_attempts", $attempt);

                        list($updatedattempt, $updatedresult) = $this->on_after_write_attempt($attempt, $result);
                        if ($updatedattempt) {
                            $attempt = $updatedattempt;
                            $result = $updatedresult;
                            $DB->update_record("opendsa_activity_attempts", $attempt);
                        }

                        // Trigger an event: question answered.
                        $eventparams = array(
                            'context' => context_module::instance($PAGE->cm->id),
                            'objectid' => $this->properties->id,
                            'other' => array(
                                'pagetype' => $this->get_typestring()
                                )
                            );
                        $event = \mod_opendsa_activity\event\question_answered::create($eventparams);
                        $event->add_record_snapshot('opendsa_activity_attempts', $attempt);
                        $event->trigger();

                        // Increase the number of attempts made.
                        $nattempts++;
                    }
                } else {
                    // When reviewing the opendsa_activity, the existing attemptid is also needed for the filearea options.
                    $params = [
                        'opendsa_activity_id' => $attempt->opendsa_activity_id,
                        'pageid' => $attempt->pageid,
                        'userid' => $attempt->userid,
                        'answerid' => $attempt->answerid,
                        'retry' => $attempt->retry
                    ];
                    $attempt->id = $DB->get_field('opendsa_activity_attempts', 'id', $params);
                }
                // "number of attempts remaining" message if $this->opendsa_activity->maxattempts > 1
                // displaying of message(s) is at the end of page for more ergonomic display
                if (!$result->correctanswer && ($result->newpageid == 0)) {
                    // retreive the number of attempts left counter for displaying at bottom of feedback page
                    if ($nattempts >= $this->opendsa_activity->maxattempts) {
                        if ($this->opendsa_activity->maxattempts > 1) { // don't bother with message if only one attempt
                            $result->maxattemptsreached = true;
                        }
                        $result->newpageid = OPENDSA_ACTIVITY_NEXTPAGE;
                    } else if ($this->opendsa_activity->maxattempts > 1) { // don't bother with message if only one attempt
                        $result->attemptsremaining = $this->opendsa_activity->maxattempts - $nattempts;
                    }
                }
            }

            // Determine default feedback if necessary
            if (empty($result->response)) {
                if (!$this->opendsa_activity->feedback && !$result->noanswer && !($this->opendsa_activity->review & !$result->correctanswer && !$result->isessayquestion)) {
                    // These conditions have been met:
                    //  1. The opendsa_activity manager has not supplied feedback to the student
                    //  2. Not displaying default feedback
                    //  3. The user did provide an answer
                    //  4. We are not reviewing with an incorrect answer (and not reviewing an essay question)

                    $result->nodefaultresponse = true;  // This will cause a redirect below
                } else if ($result->isessayquestion) {
                    $result->response = get_string('defaultessayresponse', 'opendsa_activity');
                } else if ($result->correctanswer) {
                    $result->response = get_string('thatsthecorrectanswer', 'opendsa_activity');
                } else {
                    $result->response = get_string('thatsthewronganswer', 'opendsa_activity');
                }
            }

            if ($result->response) {
                if ($this->opendsa_activity->review && !$result->correctanswer && !$result->isessayquestion) {
                    $nretakes = $DB->count_records("opendsa_activity_grades", array("opendsa_activity_id"=>$this->opendsa_activity->id, "userid"=>$USER->id));
                    $qattempts = $DB->count_records("opendsa_activity_attempts", array("userid"=>$USER->id, "retry"=>$nretakes, "pageid"=>$this->properties->id));
                    if ($qattempts == 1) {
                        $result->feedback = $OUTPUT->box(get_string("firstwrong", "opendsa_activity"), 'feedback');
                    } else {
                        if (!$result->maxattemptsreached) {
                            $result->feedback = $OUTPUT->box(get_string("secondpluswrong", "opendsa_activity"), 'feedback');
                        } else {
                            $result->feedback = $OUTPUT->box(get_string("finalwrong", "opendsa_activity"), 'feedback');
                        }
                    }
                } else {
                    $result->feedback = '';
                }
                $class = 'response';
                if ($result->correctanswer) {
                    $class .= ' correct'; // CSS over-ride this if they exist (!important).
                } else if (!$result->isessayquestion) {
                    $class .= ' incorrect'; // CSS over-ride this if they exist (!important).
                }
                $options = new stdClass;
                $options->noclean = true;
                $options->para = true;
                $options->overflowdiv = true;
                $options->context = $context;
                $options->attemptid = isset($attempt) ? $attempt->id : null;

                $result->feedback .= $OUTPUT->box(format_text($this->get_contents(), $this->properties->contentsformat, $options),
                        'generalbox boxaligncenter p-y-1');
                $result->feedback .= '<div class="correctanswer generalbox"><em>'
                        . get_string("youranswer", "opendsa_activity").'</em> : <div class="studentanswer mt-2 mb-2">';

                // Create a table containing the answers and responses.
                $table = new html_table();
                // Multianswer allowed.
                if ($this->properties->qoption) {
                    $studentanswerarray = explode(self::MULTIANSWER_DELIMITER, $result->studentanswer);
                    $responsearr = explode(self::MULTIANSWER_DELIMITER, $result->response);
                    $studentanswerresponse = array_combine($studentanswerarray, $responsearr);

                    foreach ($studentanswerresponse as $answer => $response) {
                        // Add a table row containing the answer.
                        $studentanswer = $this->format_answer($answer, $context, $result->studentanswerformat, $options);
                        $table->data[] = array($studentanswer);
                        // If the response exists, add a table row containing the response. If not, add en empty row.
                        if (!empty(trim($response))) {
                            $studentresponse = isset($result->responseformat) ?
                                $this->format_response($response, $context, $result->responseformat, $options) : $response;
                            $studentresponsecontent = html_writer::div('<em>' . get_string("response", "opendsa_activity") .
                                '</em>: <br/>' . $studentresponse, $class);
                            $table->data[] = array($studentresponsecontent);
                        } else {
                            $table->data[] = array('');
                        }
                    }
                } else {
                    // Add a table row containing the answer.
                    $studentanswer = $this->format_answer($result->studentanswer, $context, $result->studentanswerformat, $options);
                    $table->data[] = array($studentanswer);
                    // If the response exists, add a table row containing the response. If not, add en empty row.
                    if (!empty(trim($result->response))) {
                        $studentresponse = isset($result->responseformat) ?
                            $this->format_response($result->response, $context, $result->responseformat,
                                $result->answerid, $options) : $result->response;
                        $studentresponsecontent = html_writer::div('<em>' . get_string("response", "opendsa_activity") .
                            '</em>: <br/>' . $studentresponse, $class);
                        $table->data[] = array($studentresponsecontent);
                    } else {
                        $table->data[] = array('');
                    }
                }

                $result->feedback .= html_writer::table($table).'</div></div>';
            }
        }
        return $result;
    }

    /**
     * Formats the answer. Override for custom formatting.
     *
     * @param string $answer
     * @param context $context
     * @param int $answerformat
     * @return string Returns formatted string
     */
    public function format_answer($answer, $context, $answerformat, $options = []) {

        if (is_object($options)) {
            $options = (array) $options;
        }

        if (empty($options['context'])) {
            $options['context'] = $context;
        }

        if (empty($options['para'])) {
            $options['para'] = true;
        }

        return format_text($answer, $answerformat, $options);
    }

    /**
     * Formats the response
     *
     * @param string $response
     * @param context $context
     * @param int $responseformat
     * @param int $answerid
     * @param stdClass $options
     * @return string Returns formatted string
     */
    private function format_response($response, $context, $responseformat, $answerid, $options) {

        $convertstudentresponse = file_rewrite_pluginfile_urls($response, 'pluginfile.php',
            $context->id, 'mod_opendsa_activity', 'page_responses', $answerid);

        return format_text($convertstudentresponse, $responseformat, $options);
    }

    /**
     * Returns the string for a jump name
     *
     * @final
     * @param int $jumpto Jump code or page ID
     * @return string
     **/
    final protected function get_jump_name($jumpto) {
        global $DB;
        static $jumpnames = array();

        if (!array_key_exists($jumpto, $jumpnames)) {
            if ($jumpto == OPENDSA_ACTIVITY_THISPAGE) {
                $jumptitle = get_string('thispage', 'opendsa_activity');
            } elseif ($jumpto == OPENDSA_ACTIVITY_NEXTPAGE) {
                $jumptitle = get_string('nextpage', 'opendsa_activity');
            } elseif ($jumpto == OPENDSA_ACTIVITY_EOL) {
                $jumptitle = get_string('endofopendsa_activity', 'opendsa_activity');
            } elseif ($jumpto == OPENDSA_ACTIVITY_UNSEENBRANCHPAGE) {
                $jumptitle = get_string('unseenpageinbranch', 'opendsa_activity');
            } elseif ($jumpto == OPENDSA_ACTIVITY_PREVIOUSPAGE) {
                $jumptitle = get_string('previouspage', 'opendsa_activity');
            } elseif ($jumpto == OPENDSA_ACTIVITY_RANDOMPAGE) {
                $jumptitle = get_string('randompageinbranch', 'opendsa_activity');
            } elseif ($jumpto == OPENDSA_ACTIVITY_RANDOMBRANCH) {
                $jumptitle = get_string('randombranch', 'opendsa_activity');
            } elseif ($jumpto == OPENDSA_ACTIVITY_CLUSTERJUMP) {
                $jumptitle = get_string('clusterjump', 'opendsa_activity');
            } else {
                if (!$jumptitle = $DB->get_field('opendsa_activity_pages', 'title', array('id' => $jumpto))) {
                    $jumptitle = '<strong>'.get_string('notdefined', 'opendsa_activity').'</strong>';
                }
            }
            $jumpnames[$jumpto] = format_string($jumptitle,true);
        }

        return $jumpnames[$jumpto];
    }

    /**
     * Constructor method
     * @param object $properties
     * @param opendsa_activity $opendsa_activity
     */
    public function __construct($properties, opendsa_activity $opendsa_activity) {
        parent::__construct($properties);
        $this->opendsa_activity = $opendsa_activity;
    }

    /**
     * Returns the score for the attempt
     * This may be overridden by page types that require manual grading
     * @param array $answers
     * @param object $attempt
     * @return int
     */
    public function earned_score($answers, $attempt) {
        return $answers[$attempt->answerid]->score;
    }

    /**
     * This is a callback method that can be override and gets called when ever a page
     * is viewed
     *
     * @param bool $canmanage True if the user has the manage cap
     * @param bool $redirect  Optional, default to true. Set to false to avoid redirection and return the page to redirect.
     * @return mixed
     */
    public function callback_on_view($canmanage, $redirect = true) {
        return true;
    }

    /**
     * save editor answers files and update answer record
     *
     * @param object $context
     * @param int $maxbytes
     * @param object $answer
     * @param object $answereditor
     * @param object $responseeditor
     */
    public function save_answers_files($context, $maxbytes, &$answer, $answereditor = '', $responseeditor = '') {
        global $DB;
        if (isset($answereditor['itemid'])) {
            $answer->answer = file_save_draft_area_files($answereditor['itemid'],
                    $context->id, 'mod_opendsa_activity', 'page_answers', $answer->id,
                    array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $maxbytes),
                    $answer->answer, null);
            $DB->set_field('opendsa_activity_answers', 'answer', $answer->answer, array('id' => $answer->id));
        }
        if (isset($responseeditor['itemid'])) {
            $answer->response = file_save_draft_area_files($responseeditor['itemid'],
                    $context->id, 'mod_opendsa_activity', 'page_responses', $answer->id,
                    array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $maxbytes),
                    $answer->response, null);
            $DB->set_field('opendsa_activity_answers', 'response', $answer->response, array('id' => $answer->id));
        }
    }

    /**
     * Rewrite urls in response and optionality answer of a question answer
     *
     * @param object $answer
     * @param bool $rewriteanswer must rewrite answer
     * @return object answer with rewritten urls
     */
    public static function rewrite_answers_urls($answer, $rewriteanswer = true) {
        global $PAGE;

        $context = context_module::instance($PAGE->cm->id);
        if ($rewriteanswer) {
            $answer->answer = file_rewrite_pluginfile_urls($answer->answer, 'pluginfile.php', $context->id,
                    'mod_opendsa_activity', 'page_answers', $answer->id);
        }
        $answer->response = file_rewrite_pluginfile_urls($answer->response, 'pluginfile.php', $context->id,
                'mod_opendsa_activity', 'page_responses', $answer->id);

        return $answer;
    }

    /**
     * Updates a opendsa_activity page and its answers within the database
     *
     * @param object $properties
     * @return bool
     */
    public function update($properties, $context = null, $maxbytes = null) {
        global $DB, $PAGE;
        $answers  = $this->get_answers();
        $properties->id = $this->properties->id;
        $properties->opendsa_activity_id = $this->opendsa_activity->id;
        if (empty($properties->qoption)) {
            $properties->qoption = '0';
        }
        if (empty($context)) {
            $context = $PAGE->context;
        }
        if ($maxbytes === null) {
            $maxbytes = get_user_max_upload_file_size($context);
        }
        $properties->timemodified = time();
        $properties = file_postupdate_standard_editor($properties, 'contents', array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$maxbytes), $context, 'mod_opendsa_activity', 'page_contents', $properties->id);
        $DB->update_record("opendsa_activity_pages", $properties);

        // Trigger an event: page updated.
        \mod_opendsa_activity\event\page_updated::create_from_opendsa_activity_page($this, $context)->trigger();

        if ($this->type == self::TYPE_STRUCTURE && $this->get_typeid() != OPENDSA_ACTIVITY_PAGE_BRANCHTABLE) {
            // These page types have only one answer to save the jump and score.
            if (count($answers) > 1) {
                $answer = array_shift($answers);
                foreach ($answers as $a) {
                    $DB->delete_records('opendsa_activity_answers', array('id' => $a->id));
                }
            } else if (count($answers) == 1) {
                $answer = array_shift($answers);
            } else {
                $answer = new stdClass;
                $answer->opendsa_activity_id = $properties->opendsa_activity_id;
                $answer->pageid = $properties->id;
                $answer->timecreated = time();
            }

            $answer->timemodified = time();
            if (isset($properties->jumpto[0])) {
                $answer->jumpto = $properties->jumpto[0];
            }
            if (isset($properties->score[0])) {
                $answer->score = $properties->score[0];
            }
            if (!empty($answer->id)) {
                $DB->update_record("opendsa_activity_answers", $answer->properties());
            } else {
                $DB->insert_record("opendsa_activity_answers", $answer);
            }
        } else {
            for ($i = 0; $i < count($properties->answer_editor); $i++) {
                if (!array_key_exists($i, $this->answers)) {
                    $this->answers[$i] = new stdClass;
                    $this->answers[$i]->opendsa_activity_id = $this->opendsa_activity->id;
                    $this->answers[$i]->pageid = $this->id;
                    $this->answers[$i]->timecreated = $this->timecreated;
                    $this->answers[$i]->answer = null;
                }

                if (isset($properties->answer_editor[$i])) {
                    if (is_array($properties->answer_editor[$i])) {
                        // Multichoice and true/false pages have an HTML editor.
                        $this->answers[$i]->answer = $properties->answer_editor[$i]['text'];
                        $this->answers[$i]->answerformat = $properties->answer_editor[$i]['format'];
                    } else {
                        // Branch tables, shortanswer and mumerical pages have only a text field.
                        $this->answers[$i]->answer = $properties->answer_editor[$i];
                        $this->answers[$i]->answerformat = FORMAT_MOODLE;
                    }
                } else {
                    // If there is no data posted which means we want to reset the stored values.
                    $this->answers[$i]->answer = null;
                }

                if (!empty($properties->response_editor[$i]) && is_array($properties->response_editor[$i])) {
                    $this->answers[$i]->response = $properties->response_editor[$i]['text'];
                    $this->answers[$i]->responseformat = $properties->response_editor[$i]['format'];
                }

                if ($this->answers[$i]->answer !== null && $this->answers[$i]->answer !== '') {
                    if (isset($properties->jumpto[$i])) {
                        $this->answers[$i]->jumpto = $properties->jumpto[$i];
                    }
                    if ($this->opendsa_activity->custom && isset($properties->score[$i])) {
                        $this->answers[$i]->score = $properties->score[$i];
                    }
                    if (!isset($this->answers[$i]->id)) {
                        $this->answers[$i]->id = $DB->insert_record("opendsa_activity_answers", $this->answers[$i]);
                    } else {
                        $DB->update_record("opendsa_activity_answers", $this->answers[$i]->properties());
                    }

                    // Save files in answers and responses.
                    if (isset($properties->response_editor[$i])) {
                        $this->save_answers_files($context, $maxbytes, $this->answers[$i],
                                $properties->answer_editor[$i], $properties->response_editor[$i]);
                    } else {
                        $this->save_answers_files($context, $maxbytes, $this->answers[$i],
                                $properties->answer_editor[$i]);
                    }

                } else if (isset($this->answers[$i]->id)) {
                    $DB->delete_records('opendsa_activity_answers', array('id' => $this->answers[$i]->id));
                    unset($this->answers[$i]);
                }
            }
        }
        return true;
    }

    /**
     * Can be set to true if the page requires a static link to create a new instance
     * instead of simply being included in the dropdown
     * @param int $previd
     * @return bool
     */
    public function add_page_link($previd) {
        return false;
    }

    /**
     * Returns true if a page has been viewed before
     *
     * @param array|int $param Either an array of pages that have been seen or the
     *                   number of retakes a user has had
     * @return bool
     */
    public function is_unseen($param) {
        global $USER, $DB;
        if (is_array($param)) {
            $seenpages = $param;
            return (!array_key_exists($this->properties->id, $seenpages));
        } else {
            $nretakes = $param;
            if (!$DB->count_records("opendsa_activity_attempts", array("pageid"=>$this->properties->id, "userid"=>$USER->id, "retry"=>$nretakes))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks to see if a page has been answered previously
     * @param int $nretakes
     * @return bool
     */
    public function is_unanswered($nretakes) {
        global $DB, $USER;
        if (!$DB->count_records("opendsa_activity_attempts", array('pageid'=>$this->properties->id, 'userid'=>$USER->id, 'correct'=>1, 'retry'=>$nretakes))) {
            return true;
        }
        return false;
    }

    /**
     * Creates answers within the database for this opendsa_activity_page. Usually only ever
     * called when creating a new page instance
     * @param object $properties
     * @return array
     */
    public function create_answers($properties) {
        global $DB, $PAGE;
        // now add the answers
        $newanswer = new stdClass;
        $newanswer->opendsa_activity_id = $this->opendsa_activity->id;
        $newanswer->pageid = $this->properties->id;
        $newanswer->timecreated = $this->properties->timecreated;

        $cm = get_coursemodule_from_instance('opendsa_activity', $this->opendsa_activity->id, $this->opendsa_activity->course);
        $context = context_module::instance($cm->id);

        $answers = array();

        for ($i = 0; $i < ($this->opendsa_activity->maxanswers + 1); $i++) {
            $answer = clone($newanswer);

            if (isset($properties->answer_editor[$i])) {
                if (is_array($properties->answer_editor[$i])) {
                    // Multichoice and true/false pages have an HTML editor.
                    $answer->answer = $properties->answer_editor[$i]['text'];
                    $answer->answerformat = $properties->answer_editor[$i]['format'];
                } else {
                    // Branch tables, shortanswer and mumerical pages have only a text field.
                    $answer->answer = $properties->answer_editor[$i];
                    $answer->answerformat = FORMAT_MOODLE;
                }
            }
            if (!empty($properties->response_editor[$i]) && is_array($properties->response_editor[$i])) {
                $answer->response = $properties->response_editor[$i]['text'];
                $answer->responseformat = $properties->response_editor[$i]['format'];
            }

            if (isset($answer->answer) && $answer->answer != '') {
                if (isset($properties->jumpto[$i])) {
                    $answer->jumpto = $properties->jumpto[$i];
                }
                if ($this->opendsa_activity->custom && isset($properties->score[$i])) {
                    $answer->score = $properties->score[$i];
                }
                $answer->id = $DB->insert_record("opendsa_activity_answers", $answer);
                if (isset($properties->response_editor[$i])) {
                    $this->save_answers_files($context, $PAGE->course->maxbytes, $answer,
                            $properties->answer_editor[$i], $properties->response_editor[$i]);
                } else {
                    $this->save_answers_files($context, $PAGE->course->maxbytes, $answer,
                            $properties->answer_editor[$i]);
                }
                $answers[$answer->id] = new opendsa_activity_page_answer($answer);
            }
        }

        $this->answers = $answers;
        return $answers;
    }

    /**
     * This method MUST be overridden by all question page types, or page types that
     * wish to score a page.
     *
     * The structure of result should always be the same so it is a good idea when
     * overriding this method on a page type to call
     * <code>
     * $result = parent::check_answer();
     * </code>
     * before modifying it as required.
     *
     * @return stdClass
     */
    public function check_answer() {
        $result = new stdClass;
        $result->answerid        = 0;
        $result->noanswer        = false;
        $result->correctanswer   = false;
        $result->isessayquestion = false;   // use this to turn off review button on essay questions
        $result->response        = '';
        $result->newpageid       = 0;       // stay on the page
        $result->studentanswer   = '';      // use this to store student's answer(s) in order to display it on feedback page
        $result->studentanswerformat = FORMAT_MOODLE;
        $result->userresponse    = null;
        $result->feedback        = '';
        // Store data that was POSTd by a form. This is currently used to perform any logic after the 1st write to the db
        // of the attempt.
        $result->postdata        = false;
        $result->nodefaultresponse  = false; // Flag for redirecting when default feedback is turned off
        $result->inmediatejump = false; // Flag to detect when we should do a jump from the page without further processing.
        return $result;
    }

    /**
     * Do any post persistence processing logic of an attempt. E.g. in cases where we need update file urls in an editor
     * and we need to have the id of the stored attempt. Should be overridden in each individual child
     * pagetype on a as required basis
     *
     * @param object $attempt The attempt corresponding to the db record
     * @param object $result The result from the 'check_answer' method
     * @return array False if nothing to be modified, updated $attempt and $result if update required.
     */
    public function on_after_write_attempt($attempt, $result) {
        return [false, false];
    }

    /**
     * True if the page uses a custom option
     *
     * Should be override and set to true if the page uses a custom option.
     *
     * @return bool
     */
    public function has_option() {
        return false;
    }

    /**
     * Returns the maximum number of answers for this page given the maximum number
     * of answers permitted by the opendsa_activity.
     *
     * @param int $default
     * @return int
     */
    public function max_answers($default) {
        return $default;
    }

    /**
     * Returns the properties of this opendsa_activity page as an object
     * @return stdClass;
     */
    public function properties() {
        $properties = clone($this->properties);
        if ($this->answers === null) {
            $this->get_answers();
        }
        if (count($this->answers)>0) {
            $count = 0;
            $qtype = $properties->qtype;
            foreach ($this->answers as $answer) {
                $properties->{'answer_editor['.$count.']'} = array('text' => $answer->answer, 'format' => $answer->answerformat);
                if ($qtype != OPENDSA_ACTIVITY_PAGE_MATCHING) {
                    $properties->{'response_editor['.$count.']'} = array('text' => $answer->response, 'format' => $answer->responseformat);
                } else {
                    $properties->{'response_editor['.$count.']'} = $answer->response;
                }
                $properties->{'jumpto['.$count.']'} = $answer->jumpto;
                $properties->{'score['.$count.']'} = $answer->score;
                $count++;
            }
        }
        return $properties;
    }

    /**
     * Returns an array of options to display when choosing the jumpto for a page/answer
     * @static
     * @param int $pageid
     * @param opendsa_activity $opendsa_activity
     * @return array
     */
    public static function get_jumptooptions($pageid, opendsa_activity $opendsa_activity) {
        global $DB;
        $jump = array();
        $jump[0] = get_string("thispage", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_NEXTPAGE] = get_string("nextpage", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_PREVIOUSPAGE] = get_string("previouspage", "opendsa_activity");
        $jump[OPENDSA_ACTIVITY_EOL] = get_string("endofopendsa_activity", "opendsa_activity");

        if ($pageid == 0) {
            return $jump;
        }

        $pages = $opendsa_activity->load_all_pages();
        if ($pages[$pageid]->qtype == OPENDSA_ACTIVITY_PAGE_BRANCHTABLE || $opendsa_activity->is_sub_page_of_type($pageid, array(OPENDSA_ACTIVITY_PAGE_BRANCHTABLE), array(OPENDSA_ACTIVITY_PAGE_ENDOFBRANCH, OPENDSA_ACTIVITY_PAGE_CLUSTER))) {
            $jump[OPENDSA_ACTIVITY_UNSEENBRANCHPAGE] = get_string("unseenpageinbranch", "opendsa_activity");
            $jump[OPENDSA_ACTIVITY_RANDOMPAGE] = get_string("randompageinbranch", "opendsa_activity");
        }
        if($pages[$pageid]->qtype == OPENDSA_ACTIVITY_PAGE_CLUSTER || $opendsa_activity->is_sub_page_of_type($pageid, array(OPENDSA_ACTIVITY_PAGE_CLUSTER), array(OPENDSA_ACTIVITY_PAGE_ENDOFCLUSTER))) {
            $jump[OPENDSA_ACTIVITY_CLUSTERJUMP] = get_string("clusterjump", "opendsa_activity");
        }
        if (!optional_param('firstpage', 0, PARAM_INT)) {
            $apageid = $DB->get_field("opendsa_activity_pages", "id", array("opendsa_activity_id" => $opendsa_activity->id, "prevpageid" => 0));
            while (true) {
                if ($apageid) {
                    $title = $DB->get_field("opendsa_activity_pages", "title", array("id" => $apageid));
                    $jump[$apageid] = strip_tags(format_string($title,true));
                    $apageid = $DB->get_field("opendsa_activity_pages", "nextpageid", array("id" => $apageid));
                } else {
                    // last page reached
                    break;
                }
            }
        }
        return $jump;
    }
    /**
     * Returns the contents field for the page properly formatted and with plugin
     * file url's converted
     * @return string
     */
    public function get_contents() {
        global $PAGE;
        if (!empty($this->properties->contents)) {
            if (!isset($this->properties->contentsformat)) {
                $this->properties->contentsformat = FORMAT_HTML;
            }
            $context = context_module::instance($PAGE->cm->id);
            $contents = file_rewrite_pluginfile_urls($this->properties->contents, 'pluginfile.php', $context->id, 'mod_opendsa_activity',
                                                     'page_contents', $this->properties->id);  // Must do this BEFORE format_text()!
            return format_text($contents, $this->properties->contentsformat,
                               array('context' => $context, 'noclean' => true,
                                     'overflowdiv' => true));  // Page edit is marked with XSS, we want all content here.
        } else {
            return '';
        }
    }

    /**
     * Set to true if this page should display in the menu block
     * @return bool
     */
    protected function get_displayinmenublock() {
        return false;
    }

    /**
     * Get the string that describes the options of this page type
     * @return string
     */
    public function option_description_string() {
        return '';
    }

    /**
     * Updates a table with the answers for this page
     * @param html_table $table
     * @return html_table
     */
    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $i = 1;
        foreach ($answers as $answer) {
            $cells = array();
            $cells[] = '<label>' . get_string('jump', 'opendsa_activity') . ' ' . $i . '</label>:';
            $cells[] = $this->get_jump_name($answer->jumpto);
            $table->data[] = new html_table_row($cells);
            if ($i === 1){
                $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
            }
            $i++;
        }
        return $table;
    }

    /**
     * Determines if this page should be grayed out on the management/report screens
     * @return int 0 or 1
     */
    protected function get_grayout() {
        return 0;
    }

    /**
     * Adds stats for this page to the &pagestats object. This should be defined
     * for all page types that grade
     * @param array $pagestats
     * @param int $tries
     * @return bool
     */
    public function stats(array &$pagestats, $tries) {
        return true;
    }

    /**
     * Formats the answers of this page for a report
     *
     * @param object $answerpage
     * @param object $answerdata
     * @param object $useranswer
     * @param array $pagestats
     * @param int $i Count of first level answers
     * @param int $n Count of second level answers
     * @return object The answer page for this
     */
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  //I'll use it widely in this page
        foreach ($answers as $answer) {
            $data = get_string('jumpsto', 'opendsa_activity', $this->get_jump_name($answer->jumpto));
            $answerdata->answers[] = array($data, "");
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }

    /**
     * Gets an array of the jumps used by the answers of this page
     *
     * @return array
     */
    public function get_jumps() {
        global $DB;
        $jumps = array();
        $params = array ("opendsa_activity_id" => $this->opendsa_activity->id, "pageid" => $this->properties->id);
        if ($answers = $this->get_answers()) {
            foreach ($answers as $answer) {
                $jumps[] = $this->get_jump_name($answer->jumpto);
            }
        } else {
            $jumps[] = $this->get_jump_name($this->properties->nextpageid);
        }
        return $jumps;
    }
    /**
     * Informs whether this page type require manual grading or not
     * @return bool
     */
    public function requires_manual_grading() {
        return false;
    }

    /**
     * A callback method that allows a page to override the next page a user will
     * see during when this page is being completed.
     * @return false|int
     */
    public function override_next_page() {
        return false;
    }

    /**
     * This method is used to determine if this page is a valid page
     *
     * @param array $validpages
     * @param array $pageviews
     * @return int The next page id to check
     */
    public function valid_page_and_view(&$validpages, &$pageviews) {
        $validpages[$this->properties->id] = 1;
        return $this->properties->nextpageid;
    }

    /**
     * Get files from the page area file.
     *
     * @param bool $includedirs whether or not include directories
     * @param int $updatedsince return files updated since this time
     * @return array list of stored_file objects
     * @since  Moodle 3.2
     */
    public function get_files($includedirs = true, $updatedsince = 0) {
        $fs = get_file_storage();
        return $fs->get_area_files($this->opendsa_activity->context->id, 'mod_opendsa_activity', 'page_contents', $this->properties->id,
                                    'itemid, filepath, filename', $includedirs, $updatedsince);
    }

    /**
     * Make updates to the form data if required.
     *
     * @since Moodle 3.7
     * @param stdClass $data The form data to update.
     * @return stdClass The updated fom data.
     */
    public function update_form_data(stdClass $data) : stdClass {
        return $data;
    }
}



/**
 * Class used to represent an answer to a page
 *
 * @property int $id The ID of this answer in the database
 * @property int $opendsa_activity_id The ID of the opendsa_activity this answer belongs to
 * @property int $pageid The ID of the page this answer belongs to
 * @property int $jumpto Identifies where the user goes upon completing a page with this answer
 * @property int $grade The grade this answer is worth
 * @property int $score The score this answer will give
 * @property int $flags Used to store options for the answer
 * @property int $timecreated A timestamp of when the answer was created
 * @property int $timemodified A timestamp of when the answer was modified
 * @property string $answer The answer itself
 * @property string $response The response the user sees if selecting this answer
 *
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opendsa_activity_page_answer extends opendsa_activity_base {

    /**
     * Loads an page answer from the DB
     *
     * @param int $id
     * @return opendsa_activity_page_answer
     */
    public static function load($id) {
        global $DB;
        $answer = $DB->get_record("opendsa_activity_answers", array("id" => $id));
        return new opendsa_activity_page_answer($answer);
    }

    /**
     * Given an object of properties and a page created answer(s) and saves them
     * in the database.
     *
     * @param stdClass $properties
     * @param opendsa_activity_page $page
     * @return array
     */
    public static function create($properties, opendsa_activity_page $page) {
        return $page->create_answers($properties);
    }

    /**
     * Get files from the answer area file.
     *
     * @param bool $includedirs whether or not include directories
     * @param int $updatedsince return files updated since this time
     * @return array list of stored_file objects
     * @since  Moodle 3.2
     */
    public function get_files($includedirs = true, $updatedsince = 0) {

        $opendsa_activity = opendsa_activity::load($this->properties->opendsa_activity_id);
        $fs = get_file_storage();
        $answerfiles = $fs->get_area_files($opendsa_activity->context->id, 'mod_opendsa_activity', 'page_answers', $this->properties->id,
                                            'itemid, filepath, filename', $includedirs, $updatedsince);
        $responsefiles = $fs->get_area_files($opendsa_activity->context->id, 'mod_opendsa_activity', 'page_responses', $this->properties->id,
                                            'itemid, filepath, filename', $includedirs, $updatedsince);
        return array_merge($answerfiles, $responsefiles);
    }

}

/**
 * A management class for page types
 *
 * This class is responsible for managing the different pages. A manager object can
 * be retrieved by calling the following line of code:
 * <code>
 * $manager  = opendsa_activity_page_type_manager::get($opendsa_activity);
 * </code>
 * The first time the page type manager is retrieved the it includes all of the
 * different page types located in mod/opendsa_activity/pagetypes.
 *
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opendsa_activity_page_type_manager {

    /**
     * An array of different page type classes
     * @var array
     */
    protected $types = array();

    /**
     * Retrieves the opendsa_activity page type manager object
     *
     * If the object hasn't yet been created it is created here.
     *
     * @staticvar opendsa_activity_page_type_manager $pagetypemanager
     * @param opendsa_activity $opendsa_activity
     * @return opendsa_activity_page_type_manager
     */
    public static function get(opendsa_activity $opendsa_activity) {
        static $pagetypemanager;
        if (!($pagetypemanager instanceof opendsa_activity_page_type_manager)) {
            $pagetypemanager = new opendsa_activity_page_type_manager();
            $pagetypemanager->load_opendsa_activity_types($opendsa_activity);
        }
        return $pagetypemanager;
    }

    /**
     * Finds and loads all opendsa_activity page types in mod/opendsa_activity/pagetypes
     *
     * @param opendsa_activity $opendsa_activity
     */
    public function load_opendsa_activity_types(opendsa_activity $opendsa_activity) {
        global $CFG;
        $basedir = $CFG->dirroot.'/mod/opendsa_activity/pagetypes/';
        $dir = dir($basedir);
        while (false !== ($entry = $dir->read())) {
            if (strpos($entry, '.')===0 || !preg_match('#^[a-zA-Z]+\.php#i', $entry)) {
                continue;
            }
            require_once($basedir.$entry);
            $class = 'opendsa_activity_page_type_'.strtok($entry,'.');
            if (class_exists($class)) {
                $pagetype = new $class(new stdClass, $opendsa_activity);
                $this->types[$pagetype->typeid] = $pagetype;
            }
        }

    }

    /**
     * Returns an array of strings to describe the loaded page types
     *
     * @param int $type Can be used to return JUST the string for the requested type
     * @return array
     */
    public function get_page_type_strings($type=null, $special=true) {
        $types = array();
        foreach ($this->types as $pagetype) {
            if (($type===null || $pagetype->type===$type) && ($special===true || $pagetype->is_standard())) {
                $types[$pagetype->typeid] = $pagetype->typestring;
            }
        }
        return $types;
    }

    /**
     * Returns the basic string used to identify a page type provided with an id
     *
     * This string can be used to instantiate or identify the page type class.
     * If the page type id is unknown then 'unknown' is returned
     *
     * @param int $id
     * @return string
     */
    public function get_page_type_idstring($id) {
        foreach ($this->types as $pagetype) {
            if ((int)$pagetype->typeid === (int)$id) {
                return $pagetype->idstring;
            }
        }
        return 'unknown';
    }

    /**
     * Loads a page for the provided opendsa_activity given it's id
     *
     * This function loads a page from the opendsa_activity when given both the opendsa_activity it belongs
     * to as well as the page's id.
     * If the page doesn't exist an error is thrown
     *
     * @param int $pageid The id of the page to load
     * @param opendsa_activity $opendsa_activity The opendsa_activity the page belongs to
     * @return opendsa_activity_page A class that extends opendsa_activity_page
     */
    public function load_page($pageid, opendsa_activity $opendsa_activity) {
        global $DB;
        if (!($page =$DB->get_record('opendsa_activity_pages', array('id'=>$pageid, 'opendsa_activity_id'=>$opendsa_activity->id)))) {
            print_error('cannotfindpages', 'opendsa_activity');
        }
        $pagetype = get_class($this->types[$page->qtype]);
        $page = new $pagetype($page, $opendsa_activity);
        return $page;
    }

    /**
     * This function detects errors in the ordering between 2 pages and updates the page records.
     *
     * @param stdClass $page1 Either the first of 2 pages or null if the $page2 param is the first in the list.
     * @param stdClass $page1 Either the second of 2 pages or null if the $page1 param is the last in the list.
     */
    protected function check_page_order($page1, $page2) {
        global $DB;
        if (empty($page1)) {
            if ($page2->prevpageid != 0) {
                debugging("***prevpageid of page " . $page2->id . " set to 0***");
                $page2->prevpageid = 0;
                $DB->set_field("opendsa_activity_pages", "prevpageid", 0, array("id" => $page2->id));
            }
        } else if (empty($page2)) {
            if ($page1->nextpageid != 0) {
                debugging("***nextpageid of page " . $page1->id . " set to 0***");
                $page1->nextpageid = 0;
                $DB->set_field("opendsa_activity_pages", "nextpageid", 0, array("id" => $page1->id));
            }
        } else {
            if ($page1->nextpageid != $page2->id) {
                debugging("***nextpageid of page " . $page1->id . " set to " . $page2->id . "***");
                $page1->nextpageid = $page2->id;
                $DB->set_field("opendsa_activity_pages", "nextpageid", $page2->id, array("id" => $page1->id));
            }
            if ($page2->prevpageid != $page1->id) {
                debugging("***prevpageid of page " . $page2->id . " set to " . $page1->id . "***");
                $page2->prevpageid = $page1->id;
                $DB->set_field("opendsa_activity_pages", "prevpageid", $page1->id, array("id" => $page2->id));
            }
        }
    }

    /**
     * This function loads ALL pages that belong to the opendsa_activity.
     *
     * @param opendsa_activity $opendsa_activity
     * @return array An array of opendsa_activity_page_type_*
     */
    public function load_all_pages(opendsa_activity $opendsa_activity) {
        global $DB;
        if (!($pages =$DB->get_records('opendsa_activity_pages', array('opendsa_activity_id'=>$opendsa_activity->id)))) {
            return array(); // Records returned empty.
        }
        foreach ($pages as $key=>$page) {
            $pagetype = get_class($this->types[$page->qtype]);
            $pages[$key] = new $pagetype($page, $opendsa_activity);
        }

        $orderedpages = array();
        $lastpageid = 0;
        $morepages = true;
        while ($morepages) {
            $morepages = false;
            foreach ($pages as $page) {
                if ((int)$page->prevpageid === (int)$lastpageid) {
                    // Check for errors in page ordering and fix them on the fly.
                    $prevpage = null;
                    if ($lastpageid !== 0) {
                        $prevpage = $orderedpages[$lastpageid];
                    }
                    $this->check_page_order($prevpage, $page);
                    $morepages = true;
                    $orderedpages[$page->id] = $page;
                    unset($pages[$page->id]);
                    $lastpageid = $page->id;
                    if ((int)$page->nextpageid===0) {
                        break 2;
                    } else {
                        break 1;
                    }
                }
            }
        }

        // Add remaining pages and fix the nextpageid links for each page.
        foreach ($pages as $page) {
            // Check for errors in page ordering and fix them on the fly.
            $prevpage = null;
            if ($lastpageid !== 0) {
                $prevpage = $orderedpages[$lastpageid];
            }
            $this->check_page_order($prevpage, $page);
            $orderedpages[$page->id] = $page;
            unset($pages[$page->id]);
            $lastpageid = $page->id;
        }

        if ($lastpageid !== 0) {
            $this->check_page_order($orderedpages[$lastpageid], null);
        }

        return $orderedpages;
    }

    /**
     * Fetches an mform that can be used to create/edit an page
     *
     * @param int $type The id for the page type
     * @param array $arguments Any arguments to pass to the mform
     * @return opendsa_activity_add_page_form_base
     */
    public function get_page_form($type, $arguments) {
        $class = 'opendsa_activity_add_page_form_'.$this->get_page_type_idstring($type);
        if (!class_exists($class) || get_parent_class($class)!=='opendsa_activity_add_page_form_base') {
            debugging('Lesson page type unknown class requested '.$class, DEBUG_DEVELOPER);
            $class = 'opendsa_activity_add_page_form_selection';
        } else if ($class === 'opendsa_activity_add_page_form_unknown') {
            $class = 'opendsa_activity_add_page_form_selection';
        }
        return new $class(null, $arguments);
    }

    /**
     * Returns an array of links to use as add page links
     * @param int $previd The id of the previous page
     * @return array
     */
    public function get_add_page_type_links($previd) {
        global $OUTPUT;

        $links = array();

        foreach ($this->types as $key=>$type) {
            if ($link = $type->add_page_link($previd)) {
                $links[$key] = $link;
            }
        }

        return $links;
    }
}
