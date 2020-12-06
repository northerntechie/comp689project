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
 * Lesson external API
 *
 * @package    mod_opendsa_activity
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/opendsa_activity/locallib.php');

use mod_opendsa_activity\external\opendsa_activity_summary_exporter;

/**
 * Lesson external functions
 *
 * @package    mod_opendsa_activity
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class mod_opendsa_activity_external extends external_api {

    /**
     * Return a opendsa_activity record ready for being exported.
     *
     * @param  stdClass $opendsa_activityrecord opendsa_activity record
     * @param  string $password       opendsa_activity password
     * @return stdClass the opendsa_activity record ready for exporting.
     */
    protected static function get_opendsa_activity_summary_for_exporter($opendsa_activityrecord, $password = '') {
        global $USER;

        $opendsa_activity = new opendsa_activity($opendsa_activityrecord);
        $opendsa_activity->update_effective_access($USER->id);
        $opendsa_activityavailable = $opendsa_activity->get_time_restriction_status() === false;
        $opendsa_activityavailable = $opendsa_activityavailable && $opendsa_activity->get_password_restriction_status($password) === false;
        $opendsa_activityavailable = $opendsa_activityavailable && $opendsa_activity->get_dependencies_restriction_status() === false;
        $canmanage = $opendsa_activity->can_manage();

        if (!$canmanage && !$opendsa_activityavailable) {
            $fields = array('intro', 'introfiles', 'mediafiles', 'practice', 'modattempts', 'usepassword',
                'grade', 'custom', 'ongoing', 'usemaxgrade',
                'maxanswers', 'maxattempts', 'review', 'nextpagedefault', 'feedback', 'minquestions',
                'maxpages', 'timelimit', 'retake', 'mediafile', 'mediaheight', 'mediawidth',
                'mediaclose', 'slideshow', 'width', 'height', 'bgcolor', 'displayleft', 'displayleftif',
                'progressbar');

            foreach ($fields as $field) {
                unset($opendsa_activityrecord->{$field});
            }
        }

        // Fields only for managers.
        if (!$canmanage) {
            $fields = array('password', 'dependency', 'conditions', 'activitylink', 'available', 'deadline',
                            'timemodified', 'completionendreached', 'completiontimespent');

            foreach ($fields as $field) {
                unset($opendsa_activityrecord->{$field});
            }
        }
        return $opendsa_activityrecord;
    }

    /**
     * Describes the parameters for get_opendsa_activitys_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_opendsa_activitys_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of opendsa_activitys in a provided list of courses,
     * if no list is provided all opendsa_activitys that the user can view will be returned.
     *
     * @param array $courseids Array of course ids
     * @return array of opendsa_activitys details
     * @since Moodle 3.3
     */
    public static function get_opendsa_activitys_by_courses($courseids = array()) {
        global $PAGE;

        $warnings = array();
        $returnedopendsa_activitys = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_opendsa_activitys_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the opendsa_activitys in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $opendsa_activitys = get_all_instances_in_courses("opendsa_activity", $courses);
            foreach ($opendsa_activitys as $opendsa_activityrecord) {
                $context = context_module::instance($opendsa_activityrecord->coursemodule);

                // Remove fields added by get_all_instances_in_courses.
                unset($opendsa_activityrecord->coursemodule, $opendsa_activityrecord->section, $opendsa_activityrecord->visible, $opendsa_activityrecord->groupmode,
                    $opendsa_activityrecord->groupingid);

                $opendsa_activityrecord = self::get_opendsa_activity_summary_for_exporter($opendsa_activityrecord);

                $exporter = new opendsa_activity_summary_exporter($opendsa_activityrecord, array('context' => $context));
                $opendsa_activity = $exporter->export($PAGE->get_renderer('core'));
                $opendsa_activity->name = external_format_string($opendsa_activity->name, $context);
                $returnedopendsa_activitys[] = $opendsa_activity;
            }
        }
        $result = array();
        $result['opendsa_activitys'] = $returnedopendsa_activitys;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_opendsa_activitys_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_opendsa_activitys_by_courses_returns() {
        return new external_single_structure(
            array(
                'opendsa_activitys' => new external_multiple_structure(
                    opendsa_activity_summary_exporter::get_read_structure()
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for validating a opendsa_activity.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @return array array containing the opendsa_activity, course, context and course module objects
     * @since  Moodle 3.3
     */
    protected static function validate_opendsa_activity($opendsa_activity_id) {
        global $DB, $USER;

        // Request and permission validation.
        $opendsa_activityrecord = $DB->get_record('opendsa_activity', array('id' => $opendsa_activity_id), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($opendsa_activityrecord, 'opendsa_activity');

        $opendsa_activity = new opendsa_activity($opendsa_activityrecord, $cm, $course);
        $opendsa_activity->update_effective_access($USER->id);

        $context = $opendsa_activity->context;
        self::validate_context($context);

        return array($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord);
    }

    /**
     * Validates a new attempt.
     *
     * @param  opendsa_activity  $opendsa_activity opendsa_activity instance
     * @param  array   $params request parameters
     * @param  boolean $return whether to return the errors or throw exceptions
     * @return array          the errors (if return set to true)
     * @since  Moodle 3.3
     */
    protected static function validate_attempt(opendsa_activity $opendsa_activity, $params, $return = false) {
        global $USER, $CFG;

        $errors = array();

        // Avoid checkings for managers.
        if ($opendsa_activity->can_manage()) {
            return [];
        }

        // Dead line.
        if ($timerestriction = $opendsa_activity->get_time_restriction_status()) {
            $error = ["$timerestriction->reason" => userdate($timerestriction->time)];
            if (!$return) {
                throw new moodle_exception(key($error), 'opendsa_activity', '', current($error));
            }
            $errors[key($error)] = current($error);
        }

        // Password protected opendsa_activity code.
        if ($passwordrestriction = $opendsa_activity->get_password_restriction_status($params['password'])) {
            $error = ["passwordprotectedopendsa_activity" => external_format_string($opendsa_activity->name, $opendsa_activity->context->id)];
            if (!$return) {
                throw new moodle_exception(key($error), 'opendsa_activity', '', current($error));
            }
            $errors[key($error)] = current($error);
        }

        // Check for dependencies.
        if ($dependenciesrestriction = $opendsa_activity->get_dependencies_restriction_status()) {
            $errorhtmllist = implode(get_string('and', 'opendsa_activity') . ', ', $dependenciesrestriction->errors);
            $error = ["completethefollowingconditions" => $dependenciesrestriction->dependentopendsa_activity->name . $errorhtmllist];
            if (!$return) {
                throw new moodle_exception(key($error), 'opendsa_activity', '', current($error));
            }
            $errors[key($error)] = current($error);
        }

        // To check only when no page is set (starting or continuing a opendsa_activity).
        if (empty($params['pageid'])) {
            // To avoid multiple calls, store the magic property firstpage.
            $opendsa_activityfirstpage = $opendsa_activity->firstpage;
            $opendsa_activityfirstpageid = $opendsa_activityfirstpage ? $opendsa_activityfirstpage->id : false;

            // Check if the opendsa_activity does not have pages.
            if (!$opendsa_activityfirstpageid) {
                $error = ["opendsa_activitynotready2" => null];
                if (!$return) {
                    throw new moodle_exception(key($error), 'opendsa_activity');
                }
                $errors[key($error)] = current($error);
            }

            // Get the number of retries (also referenced as attempts), and the last page seen.
            $attemptscount = $opendsa_activity->count_user_retries($USER->id);
            $lastpageseen = $opendsa_activity->get_last_page_seen($attemptscount);

            // Check if the user left a timed session with no retakes.
            if ($lastpageseen !== false && $lastpageseen != OPENDSA_ACTIVITY_EOL) {
                if ($opendsa_activity->left_during_timed_session($attemptscount) && $opendsa_activity->timelimit && !$opendsa_activity->retake) {
                    $error = ["leftduringtimednoretake" => null];
                    if (!$return) {
                        throw new moodle_exception(key($error), 'opendsa_activity');
                    }
                    $errors[key($error)] = current($error);
                }
            } else if ($attemptscount > 0 && !$opendsa_activity->retake) {
                // The user finished the opendsa_activity and no retakes are allowed.
                $error = ["noretake" => null];
                if (!$return) {
                    throw new moodle_exception(key($error), 'opendsa_activity');
                }
                $errors[key($error)] = current($error);
            }
        } else {
            if (!$timers = $opendsa_activity->get_user_timers($USER->id, 'starttime DESC', '*', 0, 1)) {
                $error = ["cannotfindtimer" => null];
                if (!$return) {
                    throw new moodle_exception(key($error), 'opendsa_activity');
                }
                $errors[key($error)] = current($error);
            } else {
                $timer = current($timers);
                if (!$opendsa_activity->check_time($timer)) {
                    $error = ["eolstudentoutoftime" => null];
                    if (!$return) {
                        throw new moodle_exception(key($error), 'opendsa_activity');
                    }
                    $errors[key($error)] = current($error);
                }

                // Check if the user want to review an attempt he just finished.
                if (!empty($params['review'])) {
                    // Allow review only for attempts during active session time.
                    if ($timer->opendsa_activitytime + $CFG->sessiontimeout > time()) {
                        $ntries = $opendsa_activity->count_user_retries($USER->id);
                        $ntries--;  // Need to look at the old attempts.
                        if ($params['pageid'] == OPENDSA_ACTIVITY_EOL) {
                            if ($attempts = $opendsa_activity->get_attempts($ntries)) {
                                $lastattempt = end($attempts);
                                $USER->modattempts[$opendsa_activity->id] = $lastattempt->pageid;
                            }
                        } else {
                            if ($attempts = $opendsa_activity->get_attempts($ntries, false, $params['pageid'])) {
                                $lastattempt = end($attempts);
                                $USER->modattempts[$opendsa_activity->id] = $lastattempt;
                            }
                        }
                    }

                    if (!isset($USER->modattempts[$opendsa_activity->id])) {
                        $error = ["studentoutoftimeforreview" => null];
                        if (!$return) {
                            throw new moodle_exception(key($error), 'opendsa_activity');
                        }
                        $errors[key($error)] = current($error);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Describes the parameters for get_opendsa_activity_access_information.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_opendsa_activity_access_information_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id')
            )
        );
    }

    /**
     * Return access information for a given opendsa_activity.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @return array of warnings and the access information
     * @since Moodle 3.3
     * @throws  moodle_exception
     */
    public static function get_opendsa_activity_access_information($opendsa_activity_id) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'opendsa_activity_id' => $opendsa_activity_id
        );
        $params = self::validate_parameters(self::get_opendsa_activity_access_information_parameters(), $params);

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        $result = array();
        // Capabilities first.
        $result['canmanage'] = $opendsa_activity->can_manage();
        $result['cangrade'] = has_capability('mod/opendsa_activity:grade', $context);
        $result['canviewreports'] = has_capability('mod/opendsa_activity:viewreports', $context);

        // Status information.
        $result['reviewmode'] = $opendsa_activity->is_in_review_mode();
        $result['attemptscount'] = $opendsa_activity->count_user_retries($USER->id);
        $lastpageseen = $opendsa_activity->get_last_page_seen($result['attemptscount']);
        $result['lastpageseen'] = ($lastpageseen !== false) ? $lastpageseen : 0;
        $result['leftduringtimedsession'] = $opendsa_activity->left_during_timed_session($result['attemptscount']);
        // To avoid multiple calls, store the magic property firstpage.
        $opendsa_activityfirstpage = $opendsa_activity->firstpage;
        $result['firstpageid'] = $opendsa_activityfirstpage ? $opendsa_activityfirstpage->id : 0;

        // Access restrictions now, we emulate a new attempt access to get the possible warnings.
        $result['preventaccessreasons'] = [];
        $validationerrors = self::validate_attempt($opendsa_activity, ['password' => ''], true);
        foreach ($validationerrors as $reason => $data) {
            $result['preventaccessreasons'][] = [
                'reason' => $reason,
                'data' => $data,
                'message' => get_string($reason, 'opendsa_activity', $data),
            ];
        }
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_opendsa_activity_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_opendsa_activity_access_information_returns() {
        return new external_single_structure(
            array(
                'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can manage the opendsa_activity or not.'),
                'cangrade' => new external_value(PARAM_BOOL, 'Whether the user can grade the opendsa_activity or not.'),
                'canviewreports' => new external_value(PARAM_BOOL, 'Whether the user can view the opendsa_activity reports or not.'),
                'reviewmode' => new external_value(PARAM_BOOL, 'Whether the opendsa_activity is in review mode for the current user.'),
                'attemptscount' => new external_value(PARAM_INT, 'The number of attempts done by the user.'),
                'lastpageseen' => new external_value(PARAM_INT, 'The last page seen id.'),
                'leftduringtimedsession' => new external_value(PARAM_BOOL, 'Whether the user left during a timed session.'),
                'firstpageid' => new external_value(PARAM_INT, 'The opendsa_activity first page id.'),
                'preventaccessreasons' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'reason' => new external_value(PARAM_ALPHANUMEXT, 'Reason lang string code'),
                            'data' => new external_value(PARAM_RAW, 'Additional data'),
                            'message' => new external_value(PARAM_RAW, 'Complete html message'),
                        ),
                        'The reasons why the user cannot attempt the opendsa_activity'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_opendsa_activity.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function view_opendsa_activity_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'password' => new external_value(PARAM_RAW, 'opendsa_activity password', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param string $password optional password (the opendsa_activity may be protected)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function view_opendsa_activity($opendsa_activity_id, $password = '') {
        global $DB;

        $params = array('opendsa_activity_id' => $opendsa_activity_id, 'password' => $password);
        $params = self::validate_parameters(self::view_opendsa_activity_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);
        self::validate_attempt($opendsa_activity, $params);

        $opendsa_activity->set_module_viewed();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_opendsa_activity return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function view_opendsa_activity_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Check if the current user can retrieve opendsa_activity information (grades, attempts) about the given user.
     *
     * @param int $userid the user to check
     * @param stdClass $course course object
     * @param stdClass $cm cm object
     * @param stdClass $context context object
     * @throws moodle_exception
     * @since Moodle 3.3
     */
    protected static function check_can_view_user_data($userid, $course, $cm, $context) {
        $user = core_user::get_user($userid, '*', MUST_EXIST);
        core_user::require_active_user($user);
        // Check permissions and that if users share group (if groups enabled).
        require_capability('mod/opendsa_activity:viewreports', $context);
        if (!groups_user_groups_visible($course, $user->id, $cm)) {
            throw new moodle_exception('notingroup');
        }
    }

    /**
     * Describes the parameters for get_questions_attempts.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_questions_attempts_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'attempt' => new external_value(PARAM_INT, 'opendsa_activity attempt number'),
                'correct' => new external_value(PARAM_BOOL, 'only fetch correct attempts', VALUE_DEFAULT, false),
                'pageid' => new external_value(PARAM_INT, 'only fetch attempts at the given page', VALUE_DEFAULT, null),
                'userid' => new external_value(PARAM_INT, 'only fetch attempts of the given user', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the list of page question attempts in a given opendsa_activity.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $attempt the opendsa_activity attempt number
     * @param bool $correct only fetch correct attempts
     * @param int $pageid only fetch attempts at the given page
     * @param int $userid only fetch attempts of the given user
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_questions_attempts($opendsa_activity_id, $attempt, $correct = false, $pageid = null, $userid = null) {
        global $DB, $USER;

        $params = array(
            'opendsa_activity_id' => $opendsa_activity_id,
            'attempt' => $attempt,
            'correct' => $correct,
            'pageid' => $pageid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_questions_attempts_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $result = array();
        $result['attempts'] = $opendsa_activity->get_attempts($params['attempt'], $params['correct'], $params['pageid'], $params['userid']);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_questions_attempts return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_questions_attempts_returns() {
        return new external_single_structure(
            array(
                'attempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The attempt id'),
                            'opendsa_activity_id' => new external_value(PARAM_INT, 'The attempt opendsa_activity_id'),
                            'pageid' => new external_value(PARAM_INT, 'The attempt pageid'),
                            'userid' => new external_value(PARAM_INT, 'The user who did the attempt'),
                            'answerid' => new external_value(PARAM_INT, 'The attempt answerid'),
                            'retry' => new external_value(PARAM_INT, 'The opendsa_activity attempt number'),
                            'correct' => new external_value(PARAM_INT, 'If it was the correct answer'),
                            'useranswer' => new external_value(PARAM_RAW, 'The complete user answer'),
                            'timeseen' => new external_value(PARAM_INT, 'The time the question was seen'),
                        ),
                        'The question page attempts'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_grade.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_user_grade_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'userid' => new external_value(PARAM_INT, 'the user id (empty for current user)', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the final grade in the opendsa_activity for the given user.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $userid only fetch grades of this user
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_user_grade($opendsa_activity_id, $userid = null) {
        global $CFG, $USER;
        require_once($CFG->libdir . '/gradelib.php');

        $params = array(
            'opendsa_activity_id' => $opendsa_activity_id,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_user_grade_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $grade = null;
        $formattedgrade = null;
        $grades = opendsa_activity_get_user_grades($opendsa_activity, $params['userid']);
        if (!empty($grades)) {
            $grade = $grades[$params['userid']]->rawgrade;
            $params = array(
                'itemtype' => 'mod',
                'itemmodule' => 'opendsa_activity',
                'iteminstance' => $opendsa_activity->id,
                'courseid' => $course->id,
                'itemnumber' => 0
            );
            $gradeitem = grade_item::fetch($params);
            $formattedgrade = grade_format_gradevalue($grade, $gradeitem);
        }

        $result = array();
        $result['grade'] = $grade;
        $result['formattedgrade'] = $formattedgrade;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_user_grade return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_user_grade_returns() {
        return new external_single_structure(
            array(
                'grade' => new external_value(PARAM_FLOAT, 'The opendsa_activity final raw grade'),
                'formattedgrade' => new external_value(PARAM_RAW, 'The opendsa_activity final grade formatted'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes an attempt grade structure.
     *
     * @param  int $required if the structure is required or optional
     * @return external_single_structure the structure
     * @since  Moodle 3.3
     */
    protected static function get_user_attempt_grade_structure($required = VALUE_REQUIRED) {
        $data = array(
            'nquestions' => new external_value(PARAM_INT, 'Number of questions answered'),
            'attempts' => new external_value(PARAM_INT, 'Number of question attempts'),
            'total' => new external_value(PARAM_FLOAT, 'Max points possible'),
            'earned' => new external_value(PARAM_FLOAT, 'Points earned by student'),
            'grade' => new external_value(PARAM_FLOAT, 'Calculated percentage grade'),
            'nmanual' => new external_value(PARAM_INT, 'Number of manually graded questions'),
            'manualpoints' => new external_value(PARAM_FLOAT, 'Point value for manually graded questions'),
        );
        return new external_single_structure(
            $data, 'Attempt grade', $required
        );
    }

    /**
     * Describes the parameters for get_user_attempt_grade.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_user_attempt_grade_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'opendsa_activityattempt' => new external_value(PARAM_INT, 'opendsa_activity attempt number'),
                'userid' => new external_value(PARAM_INT, 'the user id (empty for current user)', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return grade information in the attempt for a given user.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $opendsa_activityattempt opendsa_activity attempt number
     * @param int $userid only fetch attempts of the given user
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_user_attempt_grade($opendsa_activity_id, $opendsa_activityattempt, $userid = null) {
        global $CFG, $USER;
        require_once($CFG->libdir . '/gradelib.php');

        $params = array(
            'opendsa_activity_id' => $opendsa_activity_id,
            'opendsa_activityattempt' => $opendsa_activityattempt,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_user_attempt_grade_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $result = array();
        $result['grade'] = (array) opendsa_activity_grade($opendsa_activity, $params['opendsa_activityattempt'], $params['userid']);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_user_attempt_grade return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_user_attempt_grade_returns() {
        return new external_single_structure(
            array(
                'grade' => self::get_user_attempt_grade_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_content_pages_viewed.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_content_pages_viewed_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'opendsa_activityattempt' => new external_value(PARAM_INT, 'opendsa_activity attempt number'),
                'userid' => new external_value(PARAM_INT, 'the user id (empty for current user)', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the list of content pages viewed by a user during a opendsa_activity attempt.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $opendsa_activityattempt opendsa_activity attempt number
     * @param int $userid only fetch attempts of the given user
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_content_pages_viewed($opendsa_activity_id, $opendsa_activityattempt, $userid = null) {
        global $USER;

        $params = array(
            'opendsa_activity_id' => $opendsa_activity_id,
            'opendsa_activityattempt' => $opendsa_activityattempt,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_content_pages_viewed_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $pages = $opendsa_activity->get_content_pages_viewed($params['opendsa_activityattempt'], $params['userid']);

        $result = array();
        $result['pages'] = $pages;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_content_pages_viewed return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_content_pages_viewed_returns() {
        return new external_single_structure(
            array(
                'pages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The attempt id.'),
                            'opendsa_activity_id' => new external_value(PARAM_INT, 'The opendsa_activity id.'),
                            'pageid' => new external_value(PARAM_INT, 'The page id.'),
                            'userid' => new external_value(PARAM_INT, 'The user who viewed the page.'),
                            'retry' => new external_value(PARAM_INT, 'The opendsa_activity attempt number.'),
                            'flag' => new external_value(PARAM_INT, '1 if the next page was calculated randomly.'),
                            'timeseen' => new external_value(PARAM_INT, 'The time the page was seen.'),
                            'nextpageid' => new external_value(PARAM_INT, 'The next page chosen id.'),
                        ),
                        'The content pages viewed.'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_timers.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_user_timers_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'userid' => new external_value(PARAM_INT, 'the user id (empty for current user)', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the timers in the current opendsa_activity for the given user.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $userid only fetch timers of the given user
     * @return array of warnings and timers
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_user_timers($opendsa_activity_id, $userid = null) {
        global $USER;

        $params = array(
            'opendsa_activity_id' => $opendsa_activity_id,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_user_timers_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $timers = $opendsa_activity->get_user_timers($params['userid']);

        $result = array();
        $result['timers'] = $timers;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_user_timers return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_user_timers_returns() {
        return new external_single_structure(
            array(
                'timers' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The attempt id'),
                            'opendsa_activity_id' => new external_value(PARAM_INT, 'The opendsa_activity id'),
                            'userid' => new external_value(PARAM_INT, 'The user id'),
                            'starttime' => new external_value(PARAM_INT, 'First access time for a new timer session'),
                            'opendsa_activitytime' => new external_value(PARAM_INT, 'Last access time to the opendsa_activity during the timer session'),
                            'completed' => new external_value(PARAM_INT, 'If the opendsa_activity for this timer was completed'),
                            'timemodifiedoffline' => new external_value(PARAM_INT, 'Last modified time via webservices.'),
                        ),
                        'The timers'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the external structure for a opendsa_activity page.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    protected static function get_page_structure($required = VALUE_REQUIRED) {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'The id of this opendsa_activity page'),
                'opendsa_activity_id' => new external_value(PARAM_INT, 'The id of the opendsa_activity this page belongs to'),
                'prevpageid' => new external_value(PARAM_INT, 'The id of the page before this one'),
                'nextpageid' => new external_value(PARAM_INT, 'The id of the next page in the page sequence'),
                'qtype' => new external_value(PARAM_INT, 'Identifies the page type of this page'),
                'qoption' => new external_value(PARAM_INT, 'Used to record page type specific options'),
                'layout' => new external_value(PARAM_INT, 'Used to record page specific layout selections'),
                'display' => new external_value(PARAM_INT, 'Used to record page specific display selections'),
                'timecreated' => new external_value(PARAM_INT, 'Timestamp for when the page was created'),
                'timemodified' => new external_value(PARAM_INT, 'Timestamp for when the page was last modified'),
                'title' => new external_value(PARAM_RAW, 'The title of this page', VALUE_OPTIONAL),
                'contents' => new external_value(PARAM_RAW, 'The contents of this page', VALUE_OPTIONAL),
                'contentsformat' => new external_format_value('contents', VALUE_OPTIONAL),
                'displayinmenublock' => new external_value(PARAM_BOOL, 'Toggles display in the left menu block'),
                'type' => new external_value(PARAM_INT, 'The type of the page [question | structure]'),
                'typeid' => new external_value(PARAM_INT, 'The unique identifier for the page type'),
                'typestring' => new external_value(PARAM_RAW, 'The string that describes this page type'),
            ),
            'Page fields', $required
        );
    }

    /**
     * Returns the fields of a page object
     * @param opendsa_activity_page $page the opendsa_activity page
     * @param bool $returncontents whether to return the page title and contents
     * @return stdClass          the fields matching the external page structure
     * @since Moodle 3.3
     */
    protected static function get_page_fields(opendsa_activity_page $page, $returncontents = false) {
        $opendsa_activity = $page->opendsa_activity;
        $context = $opendsa_activity->context;

        $pagedata = new stdClass; // Contains the data that will be returned by the WS.

        // Return the visible data.
        $visibleproperties = array('id', 'opendsa_activity_id', 'prevpageid', 'nextpageid', 'qtype', 'qoption', 'layout', 'display',
                                    'displayinmenublock', 'type', 'typeid', 'typestring', 'timecreated', 'timemodified');
        foreach ($visibleproperties as $prop) {
            $pagedata->{$prop} = $page->{$prop};
        }

        // Check if we can see title (contents required custom rendering, we won't returning it here @see get_page_data).
        $canmanage = $opendsa_activity->can_manage();
        // If we are managers or the menu block is enabled and is a content page visible always return contents.
        if ($returncontents || $canmanage || (opendsa_activity_displayleftif($opendsa_activity) && $page->displayinmenublock && $page->display)) {
            $pagedata->title = external_format_string($page->title, $context->id);

            $options = array('noclean' => true);
            list($pagedata->contents, $pagedata->contentsformat) =
                external_format_text($page->contents, $page->contentsformat, $context->id, 'mod_opendsa_activity', 'page_contents', $page->id,
                    $options);

        }
        return $pagedata;
    }

    /**
     * Describes the parameters for get_pages.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_pages_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'password' => new external_value(PARAM_RAW, 'optional password (the opendsa_activity may be protected)', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Return the list of pages in a opendsa_activity (based on the user permissions).
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param string $password optional password (the opendsa_activity may be protected)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_pages($opendsa_activity_id, $password = '') {

        $params = array('opendsa_activity_id' => $opendsa_activity_id, 'password' => $password);
        $params = self::validate_parameters(self::get_pages_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);
        self::validate_attempt($opendsa_activity, $params);

        $opendsa_activitypages = $opendsa_activity->load_all_pages();
        $pages = array();

        foreach ($opendsa_activitypages as $page) {
            $pagedata = new stdClass();

            // Get the page object fields.
            $pagedata->page = self::get_page_fields($page);

            // Now, calculate the file area files (maybe we need to download a opendsa_activity for offline usage).
            $pagedata->filescount = 0;
            $pagedata->filessizetotal = 0;
            $files = $page->get_files(false);   // Get files excluding directories.
            foreach ($files as $file) {
                $pagedata->filescount++;
                $pagedata->filessizetotal += $file->get_filesize();
            }

            // Now the possible answers and page jumps ids.
            $pagedata->answerids = array();
            $pagedata->jumps = array();
            $answers = $page->get_answers();
            foreach ($answers as $answer) {
                $pagedata->answerids[] = $answer->id;
                $pagedata->jumps[] = $answer->jumpto;
                $files = $answer->get_files(false);   // Get files excluding directories.
                foreach ($files as $file) {
                    $pagedata->filescount++;
                    $pagedata->filessizetotal += $file->get_filesize();
                }
            }
            $pages[] = $pagedata;
        }

        $result = array();
        $result['pages'] = $pages;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_pages return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_pages_returns() {
        return new external_single_structure(
            array(
                'pages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'page' => self::get_page_structure(),
                            'answerids' => new external_multiple_structure(
                                new external_value(PARAM_INT, 'Answer id'), 'List of answers ids (empty for content pages in  Moodle 1.9)'
                            ),
                            'jumps' => new external_multiple_structure(
                                new external_value(PARAM_INT, 'Page to jump id'), 'List of possible page jumps'
                            ),
                            'filescount' => new external_value(PARAM_INT, 'The total number of files attached to the page'),
                            'filessizetotal' => new external_value(PARAM_INT, 'The total size of the files'),
                        ),
                        'The opendsa_activity pages'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for launch_attempt.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function launch_attempt_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'password' => new external_value(PARAM_RAW, 'optional password (the opendsa_activity may be protected)', VALUE_DEFAULT, ''),
                'pageid' => new external_value(PARAM_INT, 'page id to continue from (only when continuing an attempt)', VALUE_DEFAULT, 0),
                'review' => new external_value(PARAM_BOOL, 'if we want to review just after finishing', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Return opendsa_activity messages formatted according the external_messages structure
     *
     * @param  opendsa_activity $opendsa_activity opendsa_activity instance
     * @return array          messages formatted
     * @since Moodle 3.3
     */
    protected static function format_opendsa_activity_messages($opendsa_activity) {
        $messages = array();
        foreach ($opendsa_activity->messages as $message) {
            $messages[] = array(
                'message' => $message[0],
                'type' => $message[1],
            );
        }
        return $messages;
    }

    /**
     * Return a external structure representing messages.
     *
     * @return external_multiple_structure messages structure
     * @since Moodle 3.3
     */
    protected static function external_messages() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'message' => new external_value(PARAM_RAW, 'Message.'),
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Message type: usually a CSS identifier like:
                                success, info, warning, error, notifyproblem, notifyerror, notifytiny, notifysuccess')
                ), 'The opendsa_activity generated messages'
            )
        );
    }

    /**
     * Starts a new attempt or continues an existing one.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param string $password optional password (the opendsa_activity may be protected)
     * @param int $pageid page id to continue from (only when continuing an attempt)
     * @param bool $review if we want to review just after finishing
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function launch_attempt($opendsa_activity_id, $password = '', $pageid = 0, $review = false) {
        global $CFG, $USER;

        $params = array('opendsa_activity_id' => $opendsa_activity_id, 'password' => $password, 'pageid' => $pageid, 'review' => $review);
        $params = self::validate_parameters(self::launch_attempt_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);
        self::validate_attempt($opendsa_activity, $params);

        $newpageid = 0;
        // Starting a new opendsa_activity attempt.
        if (empty($params['pageid'])) {
            // Check if there is a recent timer created during the active session.
            $alreadystarted = false;
            if ($timers = $opendsa_activity->get_user_timers($USER->id, 'starttime DESC', '*', 0, 1)) {
                $timer = array_shift($timers);
                $endtime = $opendsa_activity->timelimit > 0 ? min($CFG->sessiontimeout, $opendsa_activity->timelimit) : $CFG->sessiontimeout;
                if (!$timer->completed && $timer->starttime > time() - $endtime) {
                    $alreadystarted = true;
                }
            }
            if (!$alreadystarted && !$opendsa_activity->can_manage()) {
                $opendsa_activity->start_timer();
            }
        } else {
            if ($params['pageid'] == OPENDSA_ACTIVITY_EOL) {
                throw new moodle_exception('endofopendsa_activity', 'opendsa_activity');
            }
            $timer = $opendsa_activity->update_timer(true, true);
            if (!$opendsa_activity->check_time($timer)) {
                throw new moodle_exception('eolstudentoutoftime', 'opendsa_activity');
            }
        }
        $messages = self::format_opendsa_activity_messages($opendsa_activity);

        $result = array(
            'status' => true,
            'messages' => $messages,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the launch_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function launch_attempt_returns() {
        return new external_single_structure(
            array(
                'messages' => self::external_messages(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_page_data.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_page_data_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'pageid' => new external_value(PARAM_INT, 'the page id'),
                'password' => new external_value(PARAM_RAW, 'optional password (the opendsa_activity may be protected)', VALUE_DEFAULT, ''),
                'review' => new external_value(PARAM_BOOL, 'if we want to review just after finishing (1 hour margin)',
                    VALUE_DEFAULT, false),
                'returncontents' => new external_value(PARAM_BOOL, 'if we must return the complete page contents once rendered',
                    VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Return information of a given page, including its contents.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $pageid page id
     * @param string $password optional password (the opendsa_activity may be protected)
     * @param bool $review if we want to review just after finishing (1 hour margin)
     * @param bool $returncontents if we must return the complete page contents once rendered
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_page_data($opendsa_activity_id, $pageid,  $password = '', $review = false, $returncontents = false) {
        global $PAGE, $USER;

        $params = array('opendsa_activity_id' => $opendsa_activity_id, 'password' => $password, 'pageid' => $pageid, 'review' => $review,
            'returncontents' => $returncontents);
        $params = self::validate_parameters(self::get_page_data_parameters(), $params);

        $warnings = $contentfiles = $answerfiles = $responsefiles = $answers = array();
        $pagecontent = $ongoingscore = '';
        $progress = $pagedata = null;

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);
        self::validate_attempt($opendsa_activity, $params);

        $pageid = $params['pageid'];

        // This is called if a student leaves during a opendsa_activity.
        if ($pageid == OPENDSA_ACTIVITY_UNSEENBRANCHPAGE) {
            $pageid = opendsa_activity_unseen_question_jump($opendsa_activity, $USER->id, $pageid);
        }

        if ($pageid != OPENDSA_ACTIVITY_EOL) {
            $reviewmode = $opendsa_activity->is_in_review_mode();
            $opendsa_activityoutput = $PAGE->get_renderer('mod_opendsa_activity');
            // Prepare page contents avoiding redirections.
            list($pageid, $page, $pagecontent) = $opendsa_activity->prepare_page_and_contents($pageid, $opendsa_activityoutput, $reviewmode, false);

            if ($pageid > 0) {

                $pagedata = self::get_page_fields($page, true);

                // Files.
                $contentfiles = external_util::get_area_files($context->id, 'mod_opendsa_activity', 'page_contents', $page->id);

                // Answers.
                $answers = array();
                $pageanswers = $page->get_answers();
                foreach ($pageanswers as $a) {
                    $answer = array(
                        'id' => $a->id,
                        'answerfiles' => external_util::get_area_files($context->id, 'mod_opendsa_activity', 'page_answers', $a->id),
                        'responsefiles' => external_util::get_area_files($context->id, 'mod_opendsa_activity', 'page_responses', $a->id),
                    );
                    // For managers, return all the information (including correct answers, jumps).
                    // If the teacher enabled offline attempts, this information will be downloaded too.
                    if ($opendsa_activity->can_manage() || $opendsa_activity->allowofflineattempts) {
                        $extraproperties = array('jumpto', 'grade', 'score', 'flags', 'timecreated', 'timemodified');
                        foreach ($extraproperties as $prop) {
                            $answer[$prop] = $a->{$prop};
                        }

                        $options = array('noclean' => true);
                        list($answer['answer'], $answer['answerformat']) =
                            external_format_text($a->answer, $a->answerformat, $context->id, 'mod_opendsa_activity', 'page_answers', $a->id,
                                $options);
                        list($answer['response'], $answer['responseformat']) =
                            external_format_text($a->response, $a->responseformat, $context->id, 'mod_opendsa_activity', 'page_responses',
                                $a->id, $options);
                    }
                    $answers[] = $answer;
                }

                // Additional opendsa_activity information.
                if (!$opendsa_activity->can_manage()) {
                    if ($opendsa_activity->ongoing && !$reviewmode) {
                        $ongoingscore = $opendsa_activity->get_ongoing_score_message();
                    }
                    if ($opendsa_activity->progressbar) {
                        $progress = $opendsa_activity->calculate_progress();
                    }
                }
            }
        }

        $messages = self::format_opendsa_activity_messages($opendsa_activity);

        $result = array(
            'newpageid' => $pageid,
            'ongoingscore' => $ongoingscore,
            'progress' => $progress,
            'contentfiles' => $contentfiles,
            'answers' => $answers,
            'messages' => $messages,
            'warnings' => $warnings,
            'displaymenu' => !empty(opendsa_activity_displayleftif($opendsa_activity)),
        );

        if (!empty($pagedata)) {
            $result['page'] = $pagedata;
        }
        if ($params['returncontents']) {
            $result['pagecontent'] = $pagecontent;  // Return the complete page contents rendered.
        }

        return $result;
    }

    /**
     * Describes the get_page_data return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_page_data_returns() {
        return new external_single_structure(
            array(
                'page' => self::get_page_structure(VALUE_OPTIONAL),
                'newpageid' => new external_value(PARAM_INT, 'New page id (if a jump was made)'),
                'pagecontent' => new external_value(PARAM_RAW, 'Page html content', VALUE_OPTIONAL),
                'ongoingscore' => new external_value(PARAM_TEXT, 'The ongoing score message'),
                'progress' => new external_value(PARAM_INT, 'Progress percentage in the opendsa_activity'),
                'contentfiles' => new external_files(),
                'answers' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The ID of this answer in the database'),
                            'answerfiles' => new external_files(),
                            'responsefiles' => new external_files(),
                            'jumpto' => new external_value(PARAM_INT, 'Identifies where the user goes upon completing a page with this answer',
                                                            VALUE_OPTIONAL),
                            'grade' => new external_value(PARAM_INT, 'The grade this answer is worth', VALUE_OPTIONAL),
                            'score' => new external_value(PARAM_INT, 'The score this answer will give', VALUE_OPTIONAL),
                            'flags' => new external_value(PARAM_INT, 'Used to store options for the answer', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'A timestamp of when the answer was created', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'A timestamp of when the answer was modified', VALUE_OPTIONAL),
                            'answer' => new external_value(PARAM_RAW, 'Possible answer text', VALUE_OPTIONAL),
                            'answerformat' => new external_format_value('answer', VALUE_OPTIONAL),
                            'response' => new external_value(PARAM_RAW, 'Response text for the answer', VALUE_OPTIONAL),
                            'responseformat' => new external_format_value('response', VALUE_OPTIONAL),
                        ), 'The page answers'

                    )
                ),
                'messages' => self::external_messages(),
                'displaymenu' => new external_value(PARAM_BOOL, 'Whether we should display the menu or not in this page.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for process_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function process_page_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'pageid' => new external_value(PARAM_INT, 'the page id'),
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'the data to be saved'
                ),
                'password' => new external_value(PARAM_RAW, 'optional password (the opendsa_activity may be protected)', VALUE_DEFAULT, ''),
                'review' => new external_value(PARAM_BOOL, 'if we want to review just after finishing (1 hour margin)',
                    VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Processes page responses
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $pageid page id
     * @param array $data the data to be saved
     * @param string $password optional password (the opendsa_activity may be protected)
     * @param bool $review if we want to review just after finishing (1 hour margin)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function process_page($opendsa_activity_id, $pageid,  $data, $password = '', $review = false) {
        global $USER;

        $params = array('opendsa_activity_id' => $opendsa_activity_id, 'pageid' => $pageid, 'data' => $data, 'password' => $password,
            'review' => $review);
        $params = self::validate_parameters(self::process_page_parameters(), $params);

        $warnings = array();
        $pagecontent = $ongoingscore = '';
        $progress = null;

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Update timer so the validation can check the time restrictions.
        $timer = $opendsa_activity->update_timer();
        self::validate_attempt($opendsa_activity, $params);

        // Create the $_POST object required by the opendsa_activity question engine.
        $_POST = array();
        foreach ($data as $element) {
            // First check if we are handling editor fields like answer[text].
            if (preg_match('/(.+)\[(.+)\]$/', $element['name'], $matches)) {
                $_POST[$matches[1]][$matches[2]] = $element['value'];
            } else {
                $_POST[$element['name']] = $element['value'];
            }
        }

        // Ignore sesskey (deep in some APIs), the request is already validated.
        $USER->ignoresesskey = true;

        // Process page.
        $page = $opendsa_activity->load_page($params['pageid']);
        $result = $opendsa_activity->process_page_responses($page);

        // Prepare messages.
        $reviewmode = $opendsa_activity->is_in_review_mode();
        $opendsa_activity->add_messages_on_page_process($page, $result, $reviewmode);

        // Additional opendsa_activity information.
        if (!$opendsa_activity->can_manage()) {
            if ($opendsa_activity->ongoing && !$reviewmode) {
                $ongoingscore = $opendsa_activity->get_ongoing_score_message();
            }
            if ($opendsa_activity->progressbar) {
                $progress = $opendsa_activity->calculate_progress();
            }
        }

        // Check conditionally everything coming from result (except newpageid because is always set).
        $result = array(
            'newpageid'         => (int) $result->newpageid,
            'inmediatejump'     => $result->inmediatejump,
            'nodefaultresponse' => !empty($result->nodefaultresponse),
            'feedback'          => (isset($result->feedback)) ? $result->feedback : '',
            'attemptsremaining' => (isset($result->attemptsremaining)) ? $result->attemptsremaining : null,
            'correctanswer'     => !empty($result->correctanswer),
            'noanswer'          => !empty($result->noanswer),
            'isessayquestion'   => !empty($result->isessayquestion),
            'maxattemptsreached' => !empty($result->maxattemptsreached),
            'response'          => (isset($result->response)) ? $result->response : '',
            'studentanswer'     => (isset($result->studentanswer)) ? $result->studentanswer : '',
            'userresponse'      => (isset($result->userresponse)) ? $result->userresponse : '',
            'reviewmode'        => $reviewmode,
            'ongoingscore'      => $ongoingscore,
            'progress'          => $progress,
            'displaymenu'       => !empty(opendsa_activity_displayleftif($opendsa_activity)),
            'messages'          => self::format_opendsa_activity_messages($opendsa_activity),
            'warnings'          => $warnings,
        );
        return $result;
    }

    /**
     * Describes the process_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function process_page_returns() {
        return new external_single_structure(
            array(
                'newpageid' => new external_value(PARAM_INT, 'New page id (if a jump was made).'),
                'inmediatejump' => new external_value(PARAM_BOOL, 'Whether the page processing redirect directly to anoter page.'),
                'nodefaultresponse' => new external_value(PARAM_BOOL, 'Whether there is not a default response.'),
                'feedback' => new external_value(PARAM_RAW, 'The response feedback.'),
                'attemptsremaining' => new external_value(PARAM_INT, 'Number of attempts remaining.'),
                'correctanswer' => new external_value(PARAM_BOOL, 'Whether the answer is correct.'),
                'noanswer' => new external_value(PARAM_BOOL, 'Whether there aren\'t answers.'),
                'isessayquestion' => new external_value(PARAM_BOOL, 'Whether is a essay question.'),
                'maxattemptsreached' => new external_value(PARAM_BOOL, 'Whether we reachered the max number of attempts.'),
                'response' => new external_value(PARAM_RAW, 'The response.'),
                'studentanswer' => new external_value(PARAM_RAW, 'The student answer.'),
                'userresponse' => new external_value(PARAM_RAW, 'The user response.'),
                'reviewmode' => new external_value(PARAM_BOOL, 'Whether the user is reviewing.'),
                'ongoingscore' => new external_value(PARAM_TEXT, 'The ongoing message.'),
                'progress' => new external_value(PARAM_INT, 'Progress percentage in the opendsa_activity.'),
                'displaymenu' => new external_value(PARAM_BOOL, 'Whether we should display the menu or not in this page.'),
                'messages' => self::external_messages(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for finish_attempt.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function finish_attempt_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'Lesson instance id.'),
                'password' => new external_value(PARAM_RAW, 'Optional password (the opendsa_activity may be protected).', VALUE_DEFAULT, ''),
                'outoftime' => new external_value(PARAM_BOOL, 'If the user run out of time.', VALUE_DEFAULT, false),
                'review' => new external_value(PARAM_BOOL, 'If we want to review just after finishing (1 hour margin).',
                    VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Finishes the current attempt.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param string $password optional password (the opendsa_activity may be protected)
     * @param bool $outoftime optional if the user run out of time
     * @param bool $review if we want to review just after finishing (1 hour margin)
     * @return array of warnings and information about the finished attempt
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function finish_attempt($opendsa_activity_id, $password = '', $outoftime = false, $review = false) {

        $params = array('opendsa_activity_id' => $opendsa_activity_id, 'password' => $password, 'outoftime' => $outoftime, 'review' => $review);
        $params = self::validate_parameters(self::finish_attempt_parameters(), $params);

        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Update timer so the validation can check the time restrictions.
        $timer = $opendsa_activity->update_timer();

        // Return the validation to avoid exceptions in case the user is out of time.
        $params['pageid'] = OPENDSA_ACTIVITY_EOL;
        $validation = self::validate_attempt($opendsa_activity, $params, true);

        if (array_key_exists('eolstudentoutoftime', $validation)) {
            // Maybe we run out of time just now.
            $params['outoftime'] = true;
            unset($validation['eolstudentoutoftime']);
        }
        // Check if there are more errors.
        if (!empty($validation)) {
            reset($validation);
            throw new moodle_exception(key($validation), 'opendsa_activity', '', current($validation));   // Throw first error.
        }

        // Set out of time to normal (it is the only existing mode).
        $outoftimemode = $params['outoftime'] ? 'normal' : '';
        $result = $opendsa_activity->process_eol_page($outoftimemode);

        // Return the data.
         $validmessages = array(
            'notenoughtimespent', 'numberofpagesviewed', 'youshouldview', 'numberofcorrectanswers',
            'displayscorewithessays', 'displayscorewithoutessays', 'yourcurrentgradeisoutof', 'eolstudentoutoftimenoanswers',
            'welldone', 'displayofgrade', 'modattemptsnoteacher', 'progresscompleted');

        $data = array();
        foreach ($result as $el => $value) {
            if ($value !== false) {
                $message = '';
                if (in_array($el, $validmessages)) { // Check if the data comes with an informative message.
                    $a = (is_bool($value)) ? null : $value;
                    $message = get_string($el, 'opendsa_activity', $a);
                }
                // Return the data.
                $data[] = array(
                    'name' => $el,
                    'value' => (is_bool($value)) ? 1 : json_encode($value), // The data can be a php object.
                    'message' => $message
                );
            }
        }

        $result = array(
            'data'     => $data,
            'messages' => self::format_opendsa_activity_messages($opendsa_activity),
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the finish_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function finish_attempt_returns() {
        return new external_single_structure(
            array(
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'Data name.'),
                            'value' => new external_value(PARAM_RAW, 'Data value.'),
                            'message' => new external_value(PARAM_RAW, 'Data message (translated string).'),
                        )
                    ), 'The EOL page information data.'
                ),
                'messages' => self::external_messages(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_attempts_overview.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_attempts_overview_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'groupid' => new external_value(PARAM_INT, 'group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Get a list of all the attempts made by users in a opendsa_activity.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $groupid group id, 0 means that the function will determine the user group
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_attempts_overview($opendsa_activity_id, $groupid = 0) {

        $params = array('opendsa_activity_id' => $opendsa_activity_id, 'groupid' => $groupid);
        $params = self::validate_parameters(self::get_attempts_overview_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);
        require_capability('mod/opendsa_activity:viewreports', $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0 -> all groups).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        $result = array(
            'warnings' => $warnings
        );

        list($table, $data) = opendsa_activity_get_overview_report_table_and_data($opendsa_activity, $groupid);
        if ($data !== false) {
            $result['data'] = $data;
        }

        return $result;
    }

    /**
     * Describes the get_attempts_overview return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_attempts_overview_returns() {
        return new external_single_structure(
            array(
                'data' => new external_single_structure(
                    array(
                        'opendsa_activityscored' => new external_value(PARAM_BOOL, 'True if the opendsa_activity was scored.'),
                        'numofattempts' => new external_value(PARAM_INT, 'Number of attempts.'),
                        'avescore' => new external_value(PARAM_FLOAT, 'Average score.'),
                        'highscore' => new external_value(PARAM_FLOAT, 'High score.'),
                        'lowscore' => new external_value(PARAM_FLOAT, 'Low score.'),
                        'avetime' => new external_value(PARAM_INT, 'Average time (spent in taking the opendsa_activity).'),
                        'hightime' => new external_value(PARAM_INT, 'High time.'),
                        'lowtime' => new external_value(PARAM_INT, 'Low time.'),
                        'students' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'User id.'),
                                    'fullname' => new external_value(PARAM_TEXT, 'User full name.'),
                                    'bestgrade' => new external_value(PARAM_FLOAT, 'Best grade.'),
                                    'attempts' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'try' => new external_value(PARAM_INT, 'Attempt number.'),
                                                'grade' => new external_value(PARAM_FLOAT, 'Attempt grade.'),
                                                'timestart' => new external_value(PARAM_INT, 'Attempt time started.'),
                                                'timeend' => new external_value(PARAM_INT, 'Attempt last time continued.'),
                                                'end' => new external_value(PARAM_INT, 'Attempt time ended.'),
                                            )
                                        )
                                    )
                                )
                            ), 'Students data, including attempts.', VALUE_OPTIONAL
                        ),
                    ),
                    'Attempts overview data (empty for no attemps).', VALUE_OPTIONAL
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_attempt.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_user_attempt_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'Lesson instance id.'),
                'userid' => new external_value(PARAM_INT, 'The user id. 0 for current user.'),
                'opendsa_activityattempt' => new external_value(PARAM_INT, 'The attempt number.'),
            )
        );
    }

    /**
     * Return information about the given user attempt (including answers).
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param int $userid the user id
     * @param int $opendsa_activityattempt the attempt number
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_user_attempt($opendsa_activity_id, $userid, $opendsa_activityattempt) {
        global $USER;

        $params = array(
            'opendsa_activity_id' => $opendsa_activity_id,
            'userid' => $userid,
            'opendsa_activityattempt' => $opendsa_activityattempt,
        );
        $params = self::validate_parameters(self::get_user_attempt_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        list($answerpages, $userstats) = opendsa_activity_get_user_detailed_report_data($opendsa_activity, $userid, $params['opendsa_activityattempt']);
        // Convert page object to page record.
        foreach ($answerpages as $answerp) {
            $answerp->page = self::get_page_fields($answerp->page);
        }

        $result = array(
            'answerpages' => $answerpages,
            'userstats' => $userstats,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the get_user_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_user_attempt_returns() {
        return new external_single_structure(
            array(
                'answerpages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'page' => self::get_page_structure(VALUE_OPTIONAL),
                            'title' => new external_value(PARAM_RAW, 'Page title.'),
                            'contents' => new external_value(PARAM_RAW, 'Page contents.'),
                            'qtype' => new external_value(PARAM_TEXT, 'Identifies the page type of this page.'),
                            'grayout' => new external_value(PARAM_INT, 'If is required to apply a grayout.'),
                            'answerdata' => new external_single_structure(
                                array(
                                    'score' => new external_value(PARAM_TEXT, 'The score (text version).'),
                                    'response' => new external_value(PARAM_RAW, 'The response text.'),
                                    'responseformat' => new external_format_value('response.'),
                                    'answers' => new external_multiple_structure(
                                        new external_multiple_structure(new external_value(PARAM_RAW, 'Possible answers and info.')),
                                        'User answers',
                                        VALUE_OPTIONAL
                                    ),
                                ), 'Answer data (empty in content pages created in Moodle 1.x).', VALUE_OPTIONAL
                            )
                        )
                    )
                ),
                'userstats' => new external_single_structure(
                    array(
                        'grade' => new external_value(PARAM_FLOAT, 'Attempt final grade.'),
                        'completed' => new external_value(PARAM_INT, 'Time completed.'),
                        'timetotake' => new external_value(PARAM_INT, 'Time taken.'),
                        'gradeinfo' => self::get_user_attempt_grade_structure(VALUE_OPTIONAL)
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_pages_possible_jumps.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_pages_possible_jumps_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
            )
        );
    }

    /**
     * Return all the possible jumps for the pages in a given opendsa_activity.
     *
     * You may expect different results on consecutive executions due to the random nature of the opendsa_activity module.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @return array of warnings and possible jumps
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_pages_possible_jumps($opendsa_activity_id) {
        global $USER;

        $params = array('opendsa_activity_id' => $opendsa_activity_id);
        $params = self::validate_parameters(self::get_pages_possible_jumps_parameters(), $params);

        $warnings = $jumps = array();

        list($opendsa_activity, $course, $cm, $context) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        // Only return for managers or if offline attempts are enabled.
        if ($opendsa_activity->can_manage() || $opendsa_activity->allowofflineattempts) {

            $opendsa_activitypages = $opendsa_activity->load_all_pages();
            foreach ($opendsa_activitypages as $page) {
                $jump = array();
                $jump['pageid'] = $page->id;

                $answers = $page->get_answers();
                if (count($answers) > 0) {
                    foreach ($answers as $answer) {
                        $jump['answerid'] = $answer->id;
                        $jump['jumpto'] = $answer->jumpto;
                        $jump['calculatedjump'] = $opendsa_activity->calculate_new_page_on_jump($page, $answer->jumpto);
                        // Special case, only applies to branch/end of branch.
                        if ($jump['calculatedjump'] == OPENDSA_ACTIVITY_RANDOMBRANCH) {
                            $jump['calculatedjump'] = opendsa_activity_unseen_branch_jump($opendsa_activity, $USER->id);
                        }
                        $jumps[] = $jump;
                    }
                } else {
                    // Imported opendsa_activitys from 1.x.
                    $jump['answerid'] = 0;
                    $jump['jumpto'] = $page->nextpageid;
                    $jump['calculatedjump'] = $opendsa_activity->calculate_new_page_on_jump($page, $page->nextpageid);
                    $jumps[] = $jump;
                }
            }
        }

        $result = array(
            'jumps' => $jumps,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the get_pages_possible_jumps return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_pages_possible_jumps_returns() {
        return new external_single_structure(
            array(
                'jumps' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'pageid' => new external_value(PARAM_INT, 'The page id'),
                            'answerid' => new external_value(PARAM_INT, 'The answer id'),
                            'jumpto' => new external_value(PARAM_INT, 'The jump (page id or type of jump)'),
                            'calculatedjump' => new external_value(PARAM_INT, 'The real page id (or EOL) to jump'),
                        ), 'Jump for a page answer'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_opendsa_activity.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_opendsa_activity_parameters() {
        return new external_function_parameters (
            array(
                'opendsa_activity_id' => new external_value(PARAM_INT, 'opendsa_activity instance id'),
                'password' => new external_value(PARAM_RAW, 'opendsa_activity password', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Return information of a given opendsa_activity.
     *
     * @param int $opendsa_activity_id opendsa_activity instance id
     * @param string $password optional password (the opendsa_activity may be protected)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_opendsa_activity($opendsa_activity_id, $password = '') {
        global $PAGE;

        $params = array('opendsa_activity_id' => $opendsa_activity_id, 'password' => $password);
        $params = self::validate_parameters(self::get_opendsa_activity_parameters(), $params);
        $warnings = array();

        list($opendsa_activity, $course, $cm, $context, $opendsa_activityrecord) = self::validate_opendsa_activity($params['opendsa_activity_id']);

        $opendsa_activityrecord = self::get_opendsa_activity_summary_for_exporter($opendsa_activityrecord, $params['password']);
        $exporter = new opendsa_activity_summary_exporter($opendsa_activityrecord, array('context' => $context));

        $result = array();
        $result['opendsa_activity'] = $exporter->export($PAGE->get_renderer('core'));
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_opendsa_activity return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_opendsa_activity_returns() {
        return new external_single_structure(
            array(
                'opendsa_activity' => opendsa_activity_summary_exporter::get_read_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }
}
