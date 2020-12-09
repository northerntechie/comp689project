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
 * OpenDSA module external API
 *
 * @package    mod_opendsa
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/opendsa/lib.php');

/**
 * OpenDSA module external functions
 *
 * @package    mod_opendsa
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_opendsa_external extends external_api {

    /**
     * Describes the parameters for get_opendsas_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_opendsa_results_parameters() {
        return new external_function_parameters (array('opendsaid' => new external_value(PARAM_INT, 'opendsa instance id')));
    }
    /**
     * Returns user's results for a specific opendsa
     * and a list of those users that did not answered yet.
     *
     * @param int $opendsaid the opendsa instance id
     * @return array of responses details
     * @since Moodle 3.0
     */
    public static function get_opendsa_results($opendsaid) {
        global $USER, $PAGE;

        $params = self::validate_parameters(self::get_opendsa_results_parameters(), array('opendsaid' => $opendsaid));

        if (!$opendsa = opendsa_get_opendsa($params['opendsaid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($opendsa, 'opendsa');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $groupmode = groups_get_activity_groupmode($cm);
        // Check if we have to include responses from inactive users.
        $onlyactive = $opendsa->includeinactive ? false : true;
        $users = opendsa_get_response_data($opendsa, $cm, $groupmode, $onlyactive);
        // Show those who haven't answered the question.
        if (!empty($opendsa->showunanswered)) {
            $opendsa->option[0] = get_string('notanswered', 'opendsa');
            $opendsa->maxanswers[0] = 0;
        }
        $results = prepare_opendsa_show_results($opendsa, $course, $cm, $users);

        $options = array();
        $fullnamecap = has_capability('moodle/site:viewfullnames', $context);
        foreach ($results->options as $optionid => $option) {

            $userresponses = array();
            $numberofuser = 0;
            $percentageamount = 0;
            if (property_exists($option, 'user') and
                (has_capability('mod/opendsa:readresponses', $context) or opendsa_can_view_results($opendsa))) {
                $numberofuser = count($option->user);
                $percentageamount = ((float)$numberofuser / (float)$results->numberofuser) * 100.0;
                if ($opendsa->publish) {
                    foreach ($option->user as $userresponse) {
                        $response = array();
                        $response['userid'] = $userresponse->id;
                        $response['fullname'] = fullname($userresponse, $fullnamecap);

                        $userpicture = new user_picture($userresponse);
                        $userpicture->size = 1; // Size f1.
                        $response['profileimageurl'] = $userpicture->get_url($PAGE)->out(false);

                        // Add optional properties.
                        foreach (array('answerid', 'timemodified') as $field) {
                            if (property_exists($userresponse, 'answerid')) {
                                $response[$field] = $userresponse->$field;
                            }
                        }
                        $userresponses[] = $response;
                    }
                }
            }

            $options[] = array('id'               => $optionid,
                               'text'             => external_format_string($option->text, $context->id),
                               'maxanswer'        => $option->maxanswer,
                               'userresponses'    => $userresponses,
                               'numberofuser'     => $numberofuser,
                               'percentageamount' => $percentageamount
                              );
        }

        $warnings = array();
        return array(
            'options' => $options,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the get_opendsa_results return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_opendsa_results_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'opendsa instance id'),
                            'text' => new external_value(PARAM_RAW, 'text of the opendsa'),
                            'maxanswer' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'userresponses' => new external_multiple_structure(
                                 new external_single_structure(
                                     array(
                                        'userid' => new external_value(PARAM_INT, 'user id'),
                                        'fullname' => new external_value(PARAM_NOTAGS, 'user full name'),
                                        'profileimageurl' => new external_value(PARAM_URL, 'profile user image url'),
                                        'answerid' => new external_value(PARAM_INT, 'answer id', VALUE_OPTIONAL),
                                        'timemodified' => new external_value(PARAM_INT, 'time of modification', VALUE_OPTIONAL),
                                     ), 'User responses'
                                 )
                            ),
                            'numberofuser' => new external_value(PARAM_INT, 'number of users answers'),
                            'percentageamount' => new external_value(PARAM_FLOAT, 'percentage of users answers')
                        ), 'Options'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for mod_opendsa_get_opendsa_options.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_opendsa_options_parameters() {
        return new external_function_parameters (array('opendsaid' => new external_value(PARAM_INT, 'opendsa instance id')));
    }

    /**
     * Returns options for a specific opendsa
     *
     * @param int $opendsaid the opendsa instance id
     * @return array of options details
     * @since Moodle 3.0
     */
    public static function get_opendsa_options($opendsaid) {
        global $USER;
        $warnings = array();
        $params = self::validate_parameters(self::get_opendsa_options_parameters(), array('opendsaid' => $opendsaid));

        if (!$opendsa = opendsa_get_opendsa($params['opendsaid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($opendsa, 'opendsa');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/opendsa:choose', $context);

        $groupmode = groups_get_activity_groupmode($cm);
        $onlyactive = $opendsa->includeinactive ? false : true;
        $allresponses = opendsa_get_response_data($opendsa, $cm, $groupmode, $onlyactive);

        $timenow = time();
        $opendsaopen = true;
        $showpreview = false;

        if (!empty($opendsa->timeopen) && ($opendsa->timeopen > $timenow)) {
            $opendsaopen = false;
            $warnings[1] = get_string("notopenyet", "opendsa", userdate($opendsa->timeopen));
            if ($opendsa->showpreview) {
                $warnings[2] = get_string('previewonly', 'opendsa', userdate($opendsa->timeopen));
                $showpreview = true;
            }
        }
        if (!empty($opendsa->timeclose) && ($timenow > $opendsa->timeclose)) {
            $opendsaopen = false;
            $warnings[3] = get_string("expired", "opendsa", userdate($opendsa->timeclose));
        }

        $optionsarray = array();

        if ($opendsaopen or $showpreview) {

            $options = opendsa_prepare_options($opendsa, $USER, $cm, $allresponses);

            foreach ($options['options'] as $option) {
                $optionarr = array();
                $optionarr['id']            = $option->attributes->value;
                $optionarr['text']          = external_format_string($option->text, $context->id);
                $optionarr['maxanswers']    = $option->maxanswers;
                $optionarr['displaylayout'] = $option->displaylayout;
                $optionarr['countanswers']  = $option->countanswers;
                foreach (array('checked', 'disabled') as $field) {
                    if (property_exists($option->attributes, $field) and $option->attributes->$field == 1) {
                        $optionarr[$field] = 1;
                    } else {
                        $optionarr[$field] = 0;
                    }
                }
                // When showpreview is active, we show options as disabled.
                if ($showpreview or ($optionarr['checked'] == 1 and !$opendsa->allowupdate)) {
                    $optionarr['disabled'] = 1;
                }
                $optionsarray[] = $optionarr;
            }
        }
        foreach ($warnings as $key => $message) {
            $warnings[$key] = array(
                'item' => 'opendsa',
                'itemid' => $cm->id,
                'warningcode' => $key,
                'message' => $message
            );
        }
        return array(
            'options' => $optionsarray,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the get_opendsa_results return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function get_opendsa_options_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'option id'),
                            'text' => new external_value(PARAM_RAW, 'text of the opendsa'),
                            'maxanswers' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'displaylayout' => new external_value(PARAM_BOOL, 'true for orizontal, otherwise vertical'),
                            'countanswers' => new external_value(PARAM_INT, 'number of answers'),
                            'checked' => new external_value(PARAM_BOOL, 'we already answered'),
                            'disabled' => new external_value(PARAM_BOOL, 'option disabled'),
                            )
                    ), 'Options'
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for submit_opendsa_response.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function submit_opendsa_response_parameters() {
        return new external_function_parameters (
            array(
                'opendsaid' => new external_value(PARAM_INT, 'opendsa instance id'),
                'responses' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'answer id'),
                    'Array of response ids'
                ),
            )
        );
    }

    /**
     * Submit opendsa responses
     *
     * @param int $opendsaid the opendsa instance id
     * @param array $responses the response ids
     * @return array answers information and warnings
     * @since Moodle 3.0
     */
    public static function submit_opendsa_response($opendsaid, $responses) {
        global $USER;

        $warnings = array();
        $params = self::validate_parameters(self::submit_opendsa_response_parameters(),
                                            array(
                                                'opendsaid' => $opendsaid,
                                                'responses' => $responses
                                            ));

        if (!$opendsa = opendsa_get_opendsa($params['opendsaid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($opendsa, 'opendsa');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/opendsa:choose', $context);

        $timenow = time();
        if (!empty($opendsa->timeopen) && ($opendsa->timeopen > $timenow)) {
            throw new moodle_exception("notopenyet", "opendsa", '', userdate($opendsa->timeopen));
        } else if (!empty($opendsa->timeclose) && ($timenow > $opendsa->timeclose)) {
            throw new moodle_exception("expired", "opendsa", '', userdate($opendsa->timeclose));
        }

        if (!opendsa_get_my_response($opendsa) or $opendsa->allowupdate) {
            // When a single response is given, we convert the array to a simple variable
            // in order to avoid opendsa_user_submit_response to check with allowmultiple even
            // for a single response.
            if (count($params['responses']) == 1) {
                $params['responses'] = reset($params['responses']);
            }
            opendsa_user_submit_response($params['responses'], $opendsa, $USER->id, $course, $cm);
        } else {
            throw new moodle_exception('missingrequiredcapability', 'webservice', '', 'allowupdate');
        }
        $answers = opendsa_get_my_response($opendsa);

        return array(
            'answers' => $answers,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the submit_opendsa_response return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function submit_opendsa_response_returns() {
        return new external_single_structure(
            array(
                'answers' => new external_multiple_structure(
                     new external_single_structure(
                         array(
                             'id'           => new external_value(PARAM_INT, 'answer id'),
                             'opendsaid'     => new external_value(PARAM_INT, 'opendsaid'),
                             'userid'       => new external_value(PARAM_INT, 'user id'),
                             'optionid'     => new external_value(PARAM_INT, 'optionid'),
                             'timemodified' => new external_value(PARAM_INT, 'time of last modification')
                         ), 'Answers'
                     )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_opendsa_parameters() {
        return new external_function_parameters(
            array(
                'opendsaid' => new external_value(PARAM_INT, 'opendsa instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $opendsaid the opendsa instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_opendsa($opendsaid) {
        global $CFG;

        $params = self::validate_parameters(self::view_opendsa_parameters(),
                                            array(
                                                'opendsaid' => $opendsaid
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$opendsa = opendsa_get_opendsa($params['opendsaid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($opendsa, 'opendsa');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Trigger course_module_viewed event and completion.
        opendsa_view($opendsa, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_opendsa_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_opendsas_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_opendsas_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of opendsas in a provided list of courses,
     * if no list is provided all opendsas that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of opendsas details
     * @since Moodle 3.0
     */
    public static function get_opendsas_by_courses($courseids = array()) {
        global $CFG;

        $returnedopendsas = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_opendsas_by_courses_parameters(), array('courseids' => $courseids));

        $courses = array();
        if (empty($params['courseids'])) {
            $courses = enrol_get_my_courses();
            $params['courseids'] = array_keys($courses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $courses);

            // Get the opendsas in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $opendsas = get_all_instances_in_courses("opendsa", $courses);
            foreach ($opendsas as $opendsa) {
                $context = context_module::instance($opendsa->coursemodule);
                // Entry to return.
                $opendsadetails = array();
                // First, we return information that any user can see in the web interface.
                $opendsadetails['id'] = $opendsa->id;
                $opendsadetails['coursemodule'] = $opendsa->coursemodule;
                $opendsadetails['course'] = $opendsa->course;
                $opendsadetails['name']  = external_format_string($opendsa->name, $context->id);
                // Format intro.
                $options = array('noclean' => true);
                list($opendsadetails['intro'], $opendsadetails['introformat']) =
                    external_format_text($opendsa->intro, $opendsa->introformat, $context->id, 'mod_opendsa', 'intro', null, $options);
                $opendsadetails['introfiles'] = external_util::get_area_files($context->id, 'mod_opendsa', 'intro', false, false);

                if (has_capability('mod/opendsa:choose', $context)) {
                    $opendsadetails['publish']  = $opendsa->publish;
                    $opendsadetails['showresults']  = $opendsa->showresults;
                    $opendsadetails['showpreview']  = $opendsa->showpreview;
                    $opendsadetails['timeopen']  = $opendsa->timeopen;
                    $opendsadetails['timeclose']  = $opendsa->timeclose;
                    $opendsadetails['display']  = $opendsa->display;
                    $opendsadetails['allowupdate']  = $opendsa->allowupdate;
                    $opendsadetails['allowmultiple']  = $opendsa->allowmultiple;
                    $opendsadetails['limitanswers']  = $opendsa->limitanswers;
                    $opendsadetails['showunanswered']  = $opendsa->showunanswered;
                    $opendsadetails['includeinactive']  = $opendsa->includeinactive;
                }

                if (has_capability('moodle/course:manageactivities', $context)) {
                    $opendsadetails['timemodified']  = $opendsa->timemodified;
                    $opendsadetails['completionsubmit']  = $opendsa->completionsubmit;
                    $opendsadetails['section']  = $opendsa->section;
                    $opendsadetails['visible']  = $opendsa->visible;
                    $opendsadetails['groupmode']  = $opendsa->groupmode;
                    $opendsadetails['groupingid']  = $opendsa->groupingid;
                }
                $returnedopendsas[] = $opendsadetails;
            }
        }
        $result = array();
        $result['opendsas'] = $returnedopendsas;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_opendsa_get_opendsas_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_opendsas_by_courses_returns() {
        return new external_single_structure(
            array(
                'opendsas' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'OpenDSA instance id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_INT, 'Course id'),
                            'name' => new external_value(PARAM_RAW, 'OpenDSA name'),
                            'intro' => new external_value(PARAM_RAW, 'The opendsa intro'),
                            'introformat' => new external_format_value('intro'),
                            'introfiles' => new external_files('Files in the introduction text', VALUE_OPTIONAL),
                            'publish' => new external_value(PARAM_BOOL, 'If opendsa is published', VALUE_OPTIONAL),
                            'showresults' => new external_value(PARAM_INT, '0 never, 1 after answer, 2 after close, 3 always',
                                                                VALUE_OPTIONAL),
                            'display' => new external_value(PARAM_INT, 'Display mode (vertical, horizontal)', VALUE_OPTIONAL),
                            'allowupdate' => new external_value(PARAM_BOOL, 'Allow update', VALUE_OPTIONAL),
                            'allowmultiple' => new external_value(PARAM_BOOL, 'Allow multiple opendsas', VALUE_OPTIONAL),
                            'showunanswered' => new external_value(PARAM_BOOL, 'Show users who not answered yet', VALUE_OPTIONAL),
                            'includeinactive' => new external_value(PARAM_BOOL, 'Include inactive users', VALUE_OPTIONAL),
                            'limitanswers' => new external_value(PARAM_BOOL, 'Limit unswers', VALUE_OPTIONAL),
                            'timeopen' => new external_value(PARAM_INT, 'Date of opening validity', VALUE_OPTIONAL),
                            'timeclose' => new external_value(PARAM_INT, 'Date of closing validity', VALUE_OPTIONAL),
                            'showpreview' => new external_value(PARAM_BOOL, 'Show preview before timeopen', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification', VALUE_OPTIONAL),
                            'completionsubmit' => new external_value(PARAM_BOOL, 'Completion on user submission', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'Course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'Visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'Group id', VALUE_OPTIONAL),
                        ), 'OpenDSAs'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for delete_opendsa_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function delete_opendsa_responses_parameters() {
        return new external_function_parameters (
            array(
                'opendsaid' => new external_value(PARAM_INT, 'opendsa instance id'),
                'responses' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'response id'),
                    'Array of response ids, empty for deleting all the current user responses.',
                    VALUE_DEFAULT,
                    array()
                ),
            )
        );
    }

    /**
     * Delete the given submitted responses in a opendsa
     *
     * @param int $opendsaid the opendsa instance id
     * @param array $responses the response ids,  empty for deleting all the current user responses
     * @return array status information and warnings
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function delete_opendsa_responses($opendsaid, $responses = array()) {

        $status = false;
        $warnings = array();
        $params = self::validate_parameters(self::delete_opendsa_responses_parameters(),
                                            array(
                                                'opendsaid' => $opendsaid,
                                                'responses' => $responses
                                            ));

        if (!$opendsa = opendsa_get_opendsa($params['opendsaid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($opendsa, 'opendsa');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/opendsa:choose', $context);

        $candeleteall = has_capability('mod/opendsa:deleteresponses', $context);
        if ($candeleteall || $opendsa->allowupdate) {

            // Check if we can delete our own responses.
            if (!$candeleteall) {
                $timenow = time();
                if (!empty($opendsa->timeclose) && ($timenow > $opendsa->timeclose)) {
                    throw new moodle_exception("expired", "opendsa", '', userdate($opendsa->timeclose));
                }
            }

            if (empty($params['responses'])) {
                // No responses indicated so delete only my responses.
                $todelete = array_keys(opendsa_get_my_response($opendsa));
            } else {
                // Fill an array with the responses that can be deleted for this opendsa.
                if ($candeleteall) {
                    // Teacher/managers can delete any.
                    $allowedresponses = array_keys(opendsa_get_all_responses($opendsa));
                } else {
                    // Students can delete only their own responses.
                    $allowedresponses = array_keys(opendsa_get_my_response($opendsa));
                }

                $todelete = array();
                foreach ($params['responses'] as $response) {
                    if (!in_array($response, $allowedresponses)) {
                        $warnings[] = array(
                            'item' => 'response',
                            'itemid' => $response,
                            'warningcode' => 'nopermissions',
                            'message' => 'Invalid response id, the response does not exist or you are not allowed to delete it.'
                        );
                    } else {
                        $todelete[] = $response;
                    }
                }
            }

            $status = opendsa_delete_responses($todelete, $opendsa, $cm, $course);
        } else {
            // The user requires the capability to delete responses.
            throw new required_capability_exception($context, 'mod/opendsa:deleteresponses', 'nopermissions', '');
        }

        return array(
            'status' => $status,
            'warnings' => $warnings
        );
    }

    /**
     * Describes the delete_opendsa_responses return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function delete_opendsa_responses_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status, true if everything went right'),
                'warnings' => new external_warnings(),
            )
        );
    }

}
