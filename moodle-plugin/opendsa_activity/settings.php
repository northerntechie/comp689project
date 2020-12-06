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
 * Settings used by the opendsa_activity module, were moved from mod_edit
 *
 * @package mod_opendsa_activity
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/opendsa_activity/locallib.php');
    $yesno = array(0 => get_string('no'), 1 => get_string('yes'));

    // Introductory explanation that all the settings are defaults for the add opendsa_activity form.
    $settings->add(new admin_setting_heading('mod_opendsa_activity/opendsa_activityintro', '', get_string('configintro', 'opendsa_activity')));

    // Appearance settings.
    $settings->add(new admin_setting_heading('mod_opendsa_activity/appearance', get_string('appearance'), ''));

    // Media file popup settings.
    $setting = new admin_setting_configempty('mod_opendsa_activity/mediafile', get_string('mediafile', 'opendsa_activity'),
            get_string('mediafile_help', 'opendsa_activity'));

    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $settings->add($setting);

    $settings->add(new admin_setting_configtext('mod_opendsa_activity/mediawidth', get_string('mediawidth', 'opendsa_activity'),
            get_string('configmediawidth', 'opendsa_activity'), 640, PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_opendsa_activity/mediaheight', get_string('mediaheight', 'opendsa_activity'),
            get_string('configmediaheight', 'opendsa_activity'), 480, PARAM_INT));

    $settings->add(new admin_setting_configcheckbox('mod_opendsa_activity/mediaclose', get_string('mediaclose', 'opendsa_activity'),
            get_string('configmediaclose', 'opendsa_activity'), false, PARAM_TEXT));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/progressbar',
        get_string('progressbar', 'opendsa_activity'), get_string('progressbar_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => false), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/ongoing',
        get_string('ongoing', 'opendsa_activity'), get_string('ongoing_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => true), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/displayleftmenu',
        get_string('displayleftmenu', 'opendsa_activity'), get_string('displayleftmenu_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => false), $yesno));

    $percentage = array();
    for ($i = 100; $i >= 0; $i--) {
        $percentage[$i] = $i.'%';
    }
    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/displayleftif',
        get_string('displayleftif', 'opendsa_activity'), get_string('displayleftif_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => true), $percentage));

    // Slideshow settings.
    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/slideshow',
        get_string('slideshow', 'opendsa_activity'), get_string('slideshow_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => true), $yesno));

    $settings->add(new admin_setting_configtext('mod_opendsa_activity/slideshowwidth', get_string('slideshowwidth', 'opendsa_activity'),
            get_string('configslideshowwidth', 'opendsa_activity'), 640, PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_opendsa_activity/slideshowheight', get_string('slideshowheight', 'opendsa_activity'),
            get_string('configslideshowheight', 'opendsa_activity'), 480, PARAM_INT));

    $settings->add(new admin_setting_configtext('mod_opendsa_activity/slideshowbgcolor', get_string('slideshowbgcolor', 'opendsa_activity'),
            get_string('configslideshowbgcolor', 'opendsa_activity'), '#FFFFFF', PARAM_TEXT));

    $numbers = array();
    for ($i = 20; $i > 1; $i--) {
        $numbers[$i] = $i;
    }

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/maxanswers',
        get_string('maximumnumberofanswersbranches', 'opendsa_activity'), get_string('maximumnumberofanswersbranches_help', 'opendsa_activity'),
        array('value' => '5', 'adv' => true), $numbers));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/defaultfeedback',
        get_string('displaydefaultfeedback', 'opendsa_activity'), get_string('displaydefaultfeedback_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => true), $yesno));

    $setting = new admin_setting_configempty('mod_opendsa_activity/activitylink', get_string('activitylink', 'opendsa_activity'),
        '');

    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, true);
    $settings->add($setting);

    // Availability settings.
    $settings->add(new admin_setting_heading('mod_opendsa_activity/availibility', get_string('availability'), ''));

    $settings->add(new admin_setting_configduration_with_advanced('mod_opendsa_activity/timelimit',
        get_string('timelimit', 'opendsa_activity'), get_string('configtimelimit_desc', 'opendsa_activity'),
            array('value' => '0', 'adv' => false), 60));

    $settings->add(new admin_setting_configcheckbox_with_advanced('mod_opendsa_activity/password',
        get_string('password', 'opendsa_activity'), get_string('configpassword_desc', 'opendsa_activity'),
        array('value' => 0, 'adv' => true)));

    // Flow Control.
    $settings->add(new admin_setting_heading('opendsa_activity/flowcontrol', get_string('flowcontrol', 'opendsa_activity'), ''));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/modattempts',
        get_string('modattempts', 'opendsa_activity'), get_string('modattempts_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => false), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/displayreview',
        get_string('displayreview', 'opendsa_activity'), get_string('displayreview_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => false), $yesno));

    $attempts = array();
    for ($i = 10; $i > 0; $i--) {
        $attempts[$i] = $i;
    }

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/maximumnumberofattempts',
        get_string('maximumnumberofattempts', 'opendsa_activity'), get_string('maximumnumberofattempts_help', 'opendsa_activity'),
        array('value' => '1', 'adv' => false), $attempts));

    $defaultnextpages = array();
    $defaultnextpages[0] = get_string("normal", "opendsa_activity");
    $defaultnextpages[OPENDSA_ACTIVITY_UNSEENPAGE] = get_string("showanunseenpage", "opendsa_activity");
    $defaultnextpages[OPENDSA_ACTIVITY_UNANSWEREDPAGE] = get_string("showanunansweredpage", "opendsa_activity");

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/defaultnextpage',
            get_string('actionaftercorrectanswer', 'opendsa_activity'), '',
            array('value' => 0, 'adv' => true), $defaultnextpages));

    $pages = array();
    for ($i = 100; $i >= 0; $i--) {
        $pages[$i] = $i;
    }
    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/numberofpagestoshow',
        get_string('numberofpagestoshow', 'opendsa_activity'), get_string('numberofpagestoshow_help', 'opendsa_activity'),
        array('value' => '1', 'adv' => true), $pages));

    // Grade.
    $settings->add(new admin_setting_heading('opendsa_activity/grade', get_string('grade'), ''));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/practice',
        get_string('practice', 'opendsa_activity'), get_string('practice_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => false), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/customscoring',
        get_string('customscoring', 'opendsa_activity'), get_string('customscoring_help', 'opendsa_activity'),
        array('value' => 1, 'adv' => true), $yesno));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/retakesallowed',
        get_string('retakesallowed', 'opendsa_activity'), get_string('retakesallowed_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => false), $yesno));

    $options = array();
    $options[0] = get_string('usemean', 'opendsa_activity');
    $options[1] = get_string('usemaximum', 'opendsa_activity');

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/handlingofretakes',
        get_string('handlingofretakes', 'opendsa_activity'), get_string('handlingofretakes_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => true), $options));

    $settings->add(new admin_setting_configselect_with_advanced('mod_opendsa_activity/minimumnumberofquestions',
        get_string('minimumnumberofquestions', 'opendsa_activity'), get_string('minimumnumberofquestions_help', 'opendsa_activity'),
        array('value' => 0, 'adv' => true), $pages));

}
