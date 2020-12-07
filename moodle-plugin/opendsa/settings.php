<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('opendsa_method_heading', 'OpenDSA Settings',
                       'Setup of OpenDSA REST server'));

    $options = array();
    $options['ajax']      = get_string('methodajax', 'opendsa');
    $options['header_js'] = get_string('methodnormal', 'opendsa');
    $options['sockets']   = get_string('methoddaemon', 'opendsa');
    $settings->add(new admin_setting_configselect('opendsa_method', get_string('method', 'opendsa'),
                       get_string('configmethod', 'opendsa'), 'ajax', $options));

    $settings->add(new admin_setting_configtext('opendsa_refresh_userlist', get_string('refreshuserlist', 'opendsa'),
                       get_string('configrefreshuserlist', 'opendsa'), 10, PARAM_INT));

}