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
 * Form to define a new instance of opendsa_activity or edit an instance.
 * It is used from /course/modedit.php.
 *
 * @package mod_opendsa_activity
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');

class mod_opendsa_activity_mod_form extends moodleform_mod {

    protected $course = null;

    public function __construct($current, $section, $cm, $course) {
        $this->course = $course;
        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function mod_opendsa_activity_mod_form($current, $section, $cm, $course) {
        debugging('Use of class name as constructor is deprecated', DEBUG_DEVELOPER);
        self::__construct($current, $section, $cm, $course);
    }

    function definition() {
        global $CFG, $COURSE, $DB, $OUTPUT;

        $mform    = $this->_form;

        $opendsa_activityconfig = get_config('mod_opendsa_activity');

        $mform->addElement('header', 'general', get_string('general', 'form'));

        /** Legacy slideshow width element to maintain backwards compatibility */
        $mform->addElement('hidden', 'width');
        $mform->setType('width', PARAM_INT);
        $mform->setDefault('width', $opendsa_activityconfig->slideshowwidth);

        /** Legacy slideshow height element to maintain backwards compatibility */
        $mform->addElement('hidden', 'height');
        $mform->setType('height', PARAM_INT);
        $mform->setDefault('height', $opendsa_activityconfig->slideshowheight);

        /** Legacy slideshow background color element to maintain backwards compatibility */
        $mform->addElement('hidden', 'bgcolor');
        $mform->setType('bgcolor', PARAM_TEXT);
        $mform->setDefault('bgcolor', $opendsa_activityconfig->slideshowbgcolor);

        /** Legacy media popup width element to maintain backwards compatibility */
        $mform->addElement('hidden', 'mediawidth');
        $mform->setType('mediawidth', PARAM_INT);
        $mform->setDefault('mediawidth', $opendsa_activityconfig->mediawidth);

        /** Legacy media popup height element to maintain backwards compatibility */
        $mform->addElement('hidden', 'mediaheight');
        $mform->setType('mediaheight', PARAM_INT);
        $mform->setDefault('mediaheight', $opendsa_activityconfig->mediaheight);

        /** Legacy media popup close button element to maintain backwards compatibility */
        $mform->addElement('hidden', 'mediaclose');
        $mform->setType('mediaclose', PARAM_BOOL);
        $mform->setDefault('mediaclose', $opendsa_activityconfig->mediaclose);

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        // Appearance.
        $mform->addElement('header', 'appearancehdr', get_string('appearance'));

        $filemanageroptions = array();
        $filemanageroptions['filetypes'] = '*';
        $filemanageroptions['maxbytes'] = $this->course->maxbytes;
        $filemanageroptions['subdirs'] = 0;
        $filemanageroptions['maxfiles'] = 1;

        $mform->addElement('filemanager', 'mediafile', get_string('mediafile', 'opendsa_activity'), null, $filemanageroptions);
        $mform->addHelpButton('mediafile', 'mediafile', 'opendsa_activity');
        $mform->setAdvanced('mediafile', $opendsa_activityconfig->mediafile_adv);

        $mform->addElement('selectyesno', 'progressbar', get_string('progressbar', 'opendsa_activity'));
        $mform->addHelpButton('progressbar', 'progressbar', 'opendsa_activity');
        $mform->setDefault('progressbar', $opendsa_activityconfig->progressbar);
        $mform->setAdvanced('progressbar', $opendsa_activityconfig->progressbar_adv);

        $mform->addElement('selectyesno', 'ongoing', get_string('ongoing', 'opendsa_activity'));
        $mform->addHelpButton('ongoing', 'ongoing', 'opendsa_activity');
        $mform->setDefault('ongoing', $opendsa_activityconfig->ongoing);
        $mform->setAdvanced('ongoing', $opendsa_activityconfig->ongoing_adv);

        $mform->addElement('selectyesno', 'displayleft', get_string('displayleftmenu', 'opendsa_activity'));
        $mform->addHelpButton('displayleft', 'displayleftmenu', 'opendsa_activity');
        $mform->setDefault('displayleft', $opendsa_activityconfig->displayleftmenu);
        $mform->setAdvanced('displayleft', $opendsa_activityconfig->displayleftmenu_adv);

        $options = array();
        for($i = 100; $i >= 0; $i--) {
            $options[$i] = $i.'%';
        }
        $mform->addElement('select', 'displayleftif', get_string('displayleftif', 'opendsa_activity'), $options);
        $mform->addHelpButton('displayleftif', 'displayleftif', 'opendsa_activity');
        $mform->setDefault('displayleftif', $opendsa_activityconfig->displayleftif);
        $mform->setAdvanced('displayleftif', $opendsa_activityconfig->displayleftif_adv);

        $mform->addElement('selectyesno', 'slideshow', get_string('slideshow', 'opendsa_activity'));
        $mform->addHelpButton('slideshow', 'slideshow', 'opendsa_activity');
        $mform->setDefault('slideshow', $opendsa_activityconfig->slideshow);
        $mform->setAdvanced('slideshow', $opendsa_activityconfig->slideshow_adv);

        $numbers = array();
        for ($i = 20; $i > 1; $i--) {
            $numbers[$i] = $i;
        }

        $mform->addElement('select', 'maxanswers', get_string('maximumnumberofanswersbranches', 'opendsa_activity'), $numbers);
        $mform->setDefault('maxanswers', $opendsa_activityconfig->maxanswers);
        $mform->setAdvanced('maxanswers', $opendsa_activityconfig->maxanswers_adv);
        $mform->setType('maxanswers', PARAM_INT);
        $mform->addHelpButton('maxanswers', 'maximumnumberofanswersbranches', 'opendsa_activity');

        $mform->addElement('selectyesno', 'feedback', get_string('displaydefaultfeedback', 'opendsa_activity'));
        $mform->addHelpButton('feedback', 'displaydefaultfeedback', 'opendsa_activity');
        $mform->setDefault('feedback', $opendsa_activityconfig->defaultfeedback);
        $mform->setAdvanced('feedback', $opendsa_activityconfig->defaultfeedback_adv);

        // Get the modules.
        if ($mods = get_course_mods($COURSE->id)) {
            $modinstances = array();
            foreach ($mods as $mod) {
                // Get the module name and then store it in a new array.
                if ($module = get_coursemodule_from_instance($mod->modname, $mod->instance, $COURSE->id)) {
                    // Exclude this opendsa_activity, if it's already been saved.
                    if (!isset($this->_cm->id) || $this->_cm->id != $mod->id) {
                        $modinstances[$mod->id] = $mod->modname.' - '.$module->name;
                    }
                }
            }
            asort($modinstances); // Sort by module name.
            $modinstances=array(0=>get_string('none'))+$modinstances;

            $mform->addElement('select', 'activitylink', get_string('activitylink', 'opendsa_activity'), $modinstances);
            $mform->addHelpButton('activitylink', 'activitylink', 'opendsa_activity');
            $mform->setDefault('activitylink', 0);
            $mform->setAdvanced('activitylink', $opendsa_activityconfig->activitylink_adv);
        }

        // Availability.
        $mform->addElement('header', 'availabilityhdr', get_string('availability'));

        $mform->addElement('date_time_selector', 'available', get_string('available', 'opendsa_activity'), array('optional'=>true));
        $mform->setDefault('available', 0);

        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'opendsa_activity'), array('optional'=>true));
        $mform->setDefault('deadline', 0);

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'opendsa_activity'),
                array('optional' => true));
        $mform->addHelpButton('timelimit', 'timelimit', 'opendsa_activity');
        $mform->setAdvanced('timelimit', $opendsa_activityconfig->timelimit_adv);
        $mform->setDefault('timelimit', $opendsa_activityconfig->timelimit);

        $mform->addElement('selectyesno', 'usepassword', get_string('usepassword', 'opendsa_activity'));
        $mform->addHelpButton('usepassword', 'usepassword', 'opendsa_activity');
        $mform->setDefault('usepassword', $opendsa_activityconfig->password);
        $mform->setAdvanced('usepassword', $opendsa_activityconfig->password_adv);

        $mform->addElement('passwordunmask', 'password', get_string('password', 'opendsa_activity'));
        $mform->setDefault('password', '');
        $mform->setAdvanced('password', $opendsa_activityconfig->password_adv);
        $mform->setType('password', PARAM_RAW);
        $mform->hideIf('password', 'usepassword', 'eq', 0);
        $mform->hideIf('passwordunmask', 'usepassword', 'eq', 0);

        // Dependent on.
        if ($this->current && isset($this->current->dependency) && $this->current->dependency) {
            $mform->addElement('header', 'dependencyon', get_string('prerequisiteopendsa_activity', 'opendsa_activity'));
            $mform->addElement('static', 'warningobsolete',
                get_string('warning', 'opendsa_activity'),
                get_string('prerequisiteisobsolete', 'opendsa_activity'));
            $options = array(0 => get_string('none'));
            if ($opendsa_activitys = get_all_instances_in_course('opendsa_activity', $COURSE)) {
                foreach ($opendsa_activitys as $opendsa_activity) {
                    if ($opendsa_activity->id != $this->_instance) {
                        $options[$opendsa_activity->id] = format_string($opendsa_activity->name, true);
                    }

                }
            }
            $mform->addElement('select', 'dependency', get_string('dependencyon', 'opendsa_activity'), $options);
            $mform->addHelpButton('dependency', 'dependencyon', 'opendsa_activity');
            $mform->setDefault('dependency', 0);

            $mform->addElement('text', 'timespent', get_string('timespentminutes', 'opendsa_activity'));
            $mform->setDefault('timespent', 0);
            $mform->setType('timespent', PARAM_INT);
            $mform->disabledIf('timespent', 'dependency', 'eq', 0);

            $mform->addElement('checkbox', 'completed', get_string('completed', 'opendsa_activity'));
            $mform->setDefault('completed', 0);
            $mform->disabledIf('completed', 'dependency', 'eq', 0);

            $mform->addElement('text', 'gradebetterthan', get_string('gradebetterthan', 'opendsa_activity'));
            $mform->setDefault('gradebetterthan', 0);
            $mform->setType('gradebetterthan', PARAM_INT);
            $mform->disabledIf('gradebetterthan', 'dependency', 'eq', 0);
        } else {
            $mform->addElement('hidden', 'dependency', 0);
            $mform->setType('dependency', PARAM_INT);
            $mform->addElement('hidden', 'timespent', 0);
            $mform->setType('timespent', PARAM_INT);
            $mform->addElement('hidden', 'completed', 0);
            $mform->setType('completed', PARAM_INT);
            $mform->addElement('hidden', 'gradebetterthan', 0);
            $mform->setType('gradebetterthan', PARAM_INT);
            $mform->setConstants(array('dependency' => 0, 'timespent' => 0,
                    'completed' => 0, 'gradebetterthan' => 0));
        }

        // Allow to enable offline opendsa_activitys only if the Mobile services are enabled.
        if ($CFG->enablemobilewebservice) {
            $mform->addElement('selectyesno', 'allowofflineattempts', get_string('allowofflineattempts', 'opendsa_activity'));
            $mform->addHelpButton('allowofflineattempts', 'allowofflineattempts', 'opendsa_activity');
            $mform->setDefault('allowofflineattempts', 0);
            $mform->setAdvanced('allowofflineattempts');
            $mform->disabledIf('allowofflineattempts', 'timelimit[number]', 'neq', 0);

            $mform->addElement('static', 'allowofflineattemptswarning', '',
                    $OUTPUT->notification(get_string('allowofflineattempts_help', 'opendsa_activity'), 'warning'));
            $mform->setAdvanced('allowofflineattemptswarning');
        } else {
            $mform->addElement('hidden', 'allowofflineattempts', 0);
            $mform->setType('allowofflineattempts', PARAM_INT);
        }

        // Flow control.
        $mform->addElement('header', 'flowcontrol', get_string('flowcontrol', 'opendsa_activity'));

        $mform->addElement('selectyesno', 'modattempts', get_string('modattempts', 'opendsa_activity'));
        $mform->addHelpButton('modattempts', 'modattempts', 'opendsa_activity');
        $mform->setDefault('modattempts', $opendsa_activityconfig->modattempts);
        $mform->setAdvanced('modattempts', $opendsa_activityconfig->modattempts_adv);

        $mform->addElement('selectyesno', 'review', get_string('displayreview', 'opendsa_activity'));
        $mform->addHelpButton('review', 'displayreview', 'opendsa_activity');
        $mform->setDefault('review', $opendsa_activityconfig->displayreview);
        $mform->setAdvanced('review', $opendsa_activityconfig->displayreview_adv);

        $numbers = array();
        for ($i = 10; $i > 0; $i--) {
            $numbers[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maximumnumberofattempts', 'opendsa_activity'), $numbers);
        $mform->addHelpButton('maxattempts', 'maximumnumberofattempts', 'opendsa_activity');
        $mform->setDefault('maxattempts', $opendsa_activityconfig->maximumnumberofattempts);
        $mform->setAdvanced('maxattempts', $opendsa_activityconfig->maximumnumberofattempts_adv);

        $defaultnextpages = array();
        $defaultnextpages[0] = get_string('normal', 'opendsa_activity');
        $defaultnextpages[OPENDSA_ACTIVITY_UNSEENPAGE] = get_string('showanunseenpage', 'opendsa_activity');
        $defaultnextpages[OPENDSA_ACTIVITY_UNANSWEREDPAGE] = get_string('showanunansweredpage', 'opendsa_activity');
        $mform->addElement('select', 'nextpagedefault', get_string('actionaftercorrectanswer', 'opendsa_activity'), $defaultnextpages);
        $mform->addHelpButton('nextpagedefault', 'actionaftercorrectanswer', 'opendsa_activity');
        $mform->setDefault('nextpagedefault', $opendsa_activityconfig->defaultnextpage);
        $mform->setAdvanced('nextpagedefault', $opendsa_activityconfig->defaultnextpage_adv);

        $numbers = array();
        for ($i = 100; $i >= 0; $i--) {
            $numbers[$i] = $i;
        }
        $mform->addElement('select', 'maxpages', get_string('numberofpagestoshow', 'opendsa_activity'), $numbers);
        $mform->addHelpButton('maxpages', 'numberofpagestoshow', 'opendsa_activity');
        $mform->setDefault('maxpages', $opendsa_activityconfig->numberofpagestoshow);
        $mform->setAdvanced('maxpages', $opendsa_activityconfig->numberofpagestoshow_adv);

        // Grade.
        $this->standard_grading_coursemodule_elements();

        // No header here, so that the following settings are displayed in the grade section.

        $mform->addElement('selectyesno', 'practice', get_string('practice', 'opendsa_activity'));
        $mform->addHelpButton('practice', 'practice', 'opendsa_activity');
        $mform->setDefault('practice', $opendsa_activityconfig->practice);
        $mform->setAdvanced('practice', $opendsa_activityconfig->practice_adv);

        $mform->addElement('selectyesno', 'custom', get_string('customscoring', 'opendsa_activity'));
        $mform->addHelpButton('custom', 'customscoring', 'opendsa_activity');
        $mform->setDefault('custom', $opendsa_activityconfig->customscoring);
        $mform->setAdvanced('custom', $opendsa_activityconfig->customscoring_adv);

        $mform->addElement('selectyesno', 'retake', get_string('retakesallowed', 'opendsa_activity'));
        $mform->addHelpButton('retake', 'retakesallowed', 'opendsa_activity');
        $mform->setDefault('retake', $opendsa_activityconfig->retakesallowed);
        $mform->setAdvanced('retake', $opendsa_activityconfig->retakesallowed_adv);

        $options = array();
        $options[0] = get_string('usemean', 'opendsa_activity');
        $options[1] = get_string('usemaximum', 'opendsa_activity');
        $mform->addElement('select', 'usemaxgrade', get_string('handlingofretakes', 'opendsa_activity'), $options);
        $mform->addHelpButton('usemaxgrade', 'handlingofretakes', 'opendsa_activity');
        $mform->setDefault('usemaxgrade', $opendsa_activityconfig->handlingofretakes);
        $mform->setAdvanced('usemaxgrade', $opendsa_activityconfig->handlingofretakes_adv);
        $mform->hideIf('usemaxgrade', 'retake', 'eq', '0');

        $numbers = array();
        for ($i = 100; $i >= 0; $i--) {
            $numbers[$i] = $i;
        }
        $mform->addElement('select', 'minquestions', get_string('minimumnumberofquestions', 'opendsa_activity'), $numbers);
        $mform->addHelpButton('minquestions', 'minimumnumberofquestions', 'opendsa_activity');
        $mform->setDefault('minquestions', $opendsa_activityconfig->minimumnumberofquestions);
        $mform->setAdvanced('minquestions', $opendsa_activityconfig->minimumnumberofquestions_adv);

//-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();
    }

    /**
     * Enforce defaults here
     *
     * @param array $defaultvalues Form defaults
     * @return void
     **/
    public function data_preprocessing(&$defaultvalues) {
        if (isset($defaultvalues['conditions'])) {
            $conditions = unserialize($defaultvalues['conditions']);
            $defaultvalues['timespent'] = $conditions->timespent;
            $defaultvalues['completed'] = $conditions->completed;
            $defaultvalues['gradebetterthan'] = $conditions->gradebetterthan;
        }

        // Set up the completion checkbox which is not part of standard data.
        $defaultvalues['completiontimespentenabled'] =
            !empty($defaultvalues['completiontimespent']) ? 1 : 0;

        if ($this->current->instance) {
            // Editing existing instance - copy existing files into draft area.
            $draftitemid = file_get_submitted_draft_itemid('mediafile');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_opendsa_activity', 'mediafile', 0, array('subdirs'=>0, 'maxbytes' => $this->course->maxbytes, 'maxfiles' => 1));
            $defaultvalues['mediafile'] = $draftitemid;
        }
    }

    /**
     * Enforce validation rules here
     *
     * @param object $data Post data to validate
     * @return array
     **/
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['available'] != 0 && $data['deadline'] != 0 &&
                $data['deadline'] < $data['available']) {
            $errors['deadline'] = get_string('closebeforeopen', 'opendsa_activity');
        }

        if (!empty($data['usepassword']) && empty($data['password'])) {
            $errors['password'] = get_string('emptypassword', 'opendsa_activity');
        }

        return $errors;
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement('checkbox', 'completionendreached', get_string('completionendreached', 'opendsa_activity'),
                get_string('completionendreached_desc', 'opendsa_activity'));
        // Enable this completion rule by default.
        $mform->setDefault('completionendreached', 1);

        $group = array();
        $group[] =& $mform->createElement('checkbox', 'completiontimespentenabled', '',
                get_string('completiontimespent', 'opendsa_activity'));
        $group[] =& $mform->createElement('duration', 'completiontimespent', '', array('optional' => false));
        $mform->addGroup($group, 'completiontimespentgroup', get_string('completiontimespentgroup', 'opendsa_activity'), array(' '), false);
        $mform->disabledIf('completiontimespent[number]', 'completiontimespentenabled', 'notchecked');
        $mform->disabledIf('completiontimespent[timeunit]', 'completiontimespentenabled', 'notchecked');

        return array('completionendreached', 'completiontimespentgroup');
    }

    /**
     * Called during validation. Indicates whether a module-specific completion rule is selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionendreached']) || $data['completiontimespent'] > 0;
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // Turn off completion setting if the checkbox is not ticked.
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completiontimespentenabled) || !$autocompletion) {
                $data->completiontimespent = 0;
            }
            if (empty($data->completionendreached) || !$autocompletion) {
                $data->completionendreached = 0;
            }
        }
    }
}

