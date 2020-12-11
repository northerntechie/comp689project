<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require ($CFG->dirroot.'/mod/opendsa/locallib.php');

class mod_opendsa_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $OPENDSA_SHOWRESULTS, $OPENDSA_PUBLISH,
        $OPENDSA_DISPLAY, $DB;

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('opendsaname', 'opendsa'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('description', 'opendsa'));

        $mform->addElement('select', 'display', get_string("displaymode","opendsa"), $OPENDSA_DISPLAY);

        //-------------------------------------------------------------------------------
        //$mform->addElement('header', 'optionhdr', get_string('options', 'opendsa'));
        $mform->addElement('header', 'optionhdr', get_string('restserverurlheading', 'opendsa'));

        $mform->addElement('text', 'resturl', get_string('resturlfield', 'opendsa'));
        $mform->addElement('text', 'restport', get_string('restportfield', 'opendsa'));
        
        // DEVELOPMENT ONLY.  Needs persistent data for host and port.
        $GLOBALS['opendsa_catalog'] = opendsa_get_catalog('localhost','8080');
        $GLOBALS['opendsa_list'] = generate_catalog_options($GLOBALS['opendsa_catalog']);

        $mform->addElement('select','exerciseid', get_string('selectexercise','opendsa'), $GLOBALS['opendsa_list']);

//-------------------------------------------------------------------------------
//-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values){
        global $DB;
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
        // Check if exercise is selected
        if (!empty($data->exerciseid)) {
            if($GLOBALS['opendsa_list']) {
                $data->exercisename = $GLOBALS['opendsa_list'][$data->exerciseid];
                $obj = get_exercise($GLOBALS['opendsa_catalog'], $data->exercisename);
                $data->exercisepath = $obj->path;
            }
        }
    }

    /**
     * Enforce validation rules here
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array
     **/
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }

    function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'opendsa'));
        // Enable this completion rule by default.
        $mform->setDefault('completionsubmit', 1);
        return array('completionsubmit');
    }

    function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }
}

