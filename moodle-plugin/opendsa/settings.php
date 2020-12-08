<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('opendsa_method_heading', 'OpenDSA Settings',
                       'Setup of OpenDSA REST server'));

    $settings->add(new admin_setting_configtext('opendsa/settings',//get_string('opendsaconfigurl','opendsa'),
        get_string('opendsaconfigurlstring', 'opendsa'),
        get_string('opendsaconfigurldesc', 'opendsa'), 
        get_string('opendsaconfigurldefault', 'opendsa'),
        PARAM_RAW, 256));
}