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
 * OpenDSA external functions and service definitions.
 *
 * @package    mod_opendsa
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(

    'mod_opendsa_get_opendsa_results' => array(
        'classname'     => 'mod_opendsa_external',
        'methodname'    => 'get_opendsa_results',
        'description'   => 'Retrieve users results for a given opendsa.',
        'type'          => 'read',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_opendsa_get_opendsa_options' => array(
        'classname'     => 'mod_opendsa_external',
        'methodname'    => 'get_opendsa_options',
        'description'   => 'Retrieve options for a specific opendsa.',
        'type'          => 'read',
        'capabilities'  => 'mod/opendsa:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_opendsa_submit_opendsa_response' => array(
        'classname'     => 'mod_opendsa_external',
        'methodname'    => 'submit_opendsa_response',
        'description'   => 'Submit responses to a specific opendsa item.',
        'type'          => 'write',
        'capabilities'  => 'mod/opendsa:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_opendsa_view_opendsa' => array(
        'classname'     => 'mod_opendsa_external',
        'methodname'    => 'view_opendsa',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_opendsa_get_opendsas_by_courses' => array(
        'classname'     => 'mod_opendsa_external',
        'methodname'    => 'get_opendsas_by_courses',
        'description'   => 'Returns a list of opendsa instances in a provided set of courses,
                            if no courses are provided then all the opendsa instances the user has access to will be returned.',
        'type'          => 'read',
        'capabilities'  => '',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_opendsa_delete_opendsa_responses' => array(
        'classname'     => 'mod_opendsa_external',
        'methodname'    => 'delete_opendsa_responses',
        'description'   => 'Delete the given submitted responses in a opendsa',
        'type'          => 'write',
        'capabilities'  => 'mod/opendsa:choose',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
