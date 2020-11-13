<?php
/**
 * @package   activity_opendsa
 * @copyright 2020, Todd Saharchuk <tsaharchuk1@athabasca.edu>
 * @license   MIT
 */ 
defined('MOODLE_INTERNAL') || die();
 
$plugin->version = 2020111100;
$plugin->requires = 2020061500; // Moodle 3.9.0
//$plugin->supported = TODO;   // Available as of Moodle 3.9.0 or later.
//$plugin->incompatible = TODO;   // Available as of Moodle 3.9.0 or later.
$plugin->component = 'OpenDSA';
$plugin->maturity = MATURITY_STABLE;
//$plugin->release = 'TODO';
 
$plugin->dependencies = [
    'mod_forum' => ANY_VERSION,
    'mod_data' => TODO
];