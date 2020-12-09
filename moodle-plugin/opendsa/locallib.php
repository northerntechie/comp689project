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
 * Internal library of functions for opendsa module.
 *
 * All the opendsa specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_opendsa
 * @copyright 2016 Stephen Bourget
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * This creates new calendar events given as timeopen and timeclose by $opendsa.
 *
 * @param stdClass $opendsa
 * @return void
 */
function opendsa_set_events($opendsa) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/calendar/lib.php');

    // Get CMID if not sent as part of $opendsa.
    if (!isset($opendsa->coursemodule)) {
        $cm = get_coursemodule_from_instance('opendsa', $opendsa->id, $opendsa->course);
        $opendsa->coursemodule = $cm->id;
    }

    // OpenDSA start calendar events.
    $event = new stdClass();
    $event->eventtype = OPENDSA_EVENT_TYPE_OPEN;
    // The OPENDSA_EVENT_TYPE_OPEN event should only be an action event if no close time is specified.
    $event->type = empty($opendsa->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
    if ($event->id = $DB->get_field('event', 'id',
            array('modulename' => 'opendsa', 'instance' => $opendsa->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($opendsa->timeopen)) && ($opendsa->timeopen > 0)) {
            // Calendar event exists so update it.
            $event->name         = get_string('calendarstart', 'opendsa', $opendsa->name);
            $event->description  = format_module_intro('opendsa', $opendsa, $opendsa->coursemodule, false);
            $event->format       = FORMAT_HTML;
            $event->timestart    = $opendsa->timeopen;
            $event->timesort     = $opendsa->timeopen;
            $event->visible      = instance_is_visible('opendsa', $opendsa);
            $event->timeduration = 0;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($opendsa->timeopen)) && ($opendsa->timeopen > 0)) {
            $event->name         = get_string('calendarstart', 'opendsa', $opendsa->name);
            $event->description  = format_module_intro('opendsa', $opendsa, $opendsa->coursemodule, false);
            $event->format       = FORMAT_HTML;
            $event->courseid     = $opendsa->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'opendsa';
            $event->instance     = $opendsa->id;
            $event->timestart    = $opendsa->timeopen;
            $event->timesort     = $opendsa->timeopen;
            $event->visible      = instance_is_visible('opendsa', $opendsa);
            $event->timeduration = 0;
            calendar_event::create($event, false);
        }
    }

    // OpenDSA end calendar events.
    $event = new stdClass();
    $event->type = CALENDAR_EVENT_TYPE_ACTION;
    $event->eventtype = OPENDSA_EVENT_TYPE_CLOSE;
    if ($event->id = $DB->get_field('event', 'id',
            array('modulename' => 'opendsa', 'instance' => $opendsa->id, 'eventtype' => $event->eventtype))) {
        if ((!empty($opendsa->timeclose)) && ($opendsa->timeclose > 0)) {
            // Calendar event exists so update it.
            $event->name         = get_string('calendarend', 'opendsa', $opendsa->name);
            $event->description  = format_module_intro('opendsa', $opendsa, $opendsa->coursemodule, false);
            $event->format       = FORMAT_HTML;
            $event->timestart    = $opendsa->timeclose;
            $event->timesort     = $opendsa->timeclose;
            $event->visible      = instance_is_visible('opendsa', $opendsa);
            $event->timeduration = 0;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Calendar event is on longer needed.
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->delete();
        }
    } else {
        // Event doesn't exist so create one.
        if ((!empty($opendsa->timeclose)) && ($opendsa->timeclose > 0)) {
            $event->name         = get_string('calendarend', 'opendsa', $opendsa->name);
            $event->description  = format_module_intro('opendsa', $opendsa, $opendsa->coursemodule, false);
            $event->format       = FORMAT_HTML;
            $event->courseid     = $opendsa->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'opendsa';
            $event->instance     = $opendsa->id;
            $event->timestart    = $opendsa->timeclose;
            $event->timesort     = $opendsa->timeclose;
            $event->visible      = instance_is_visible('opendsa', $opendsa);
            $event->timeduration = 0;
            calendar_event::create($event, false);
        }
    }
}

/**
 * Retrieve a catalog listing object using the OpenDSA REST interface
 *
 * @param string $host
 * @param string $port
 * @return object $responseSplit
 */
function opendsa_get_catalog($host, $port) {
    $fp = @fsockopen ( $host, $port, $errno, $errnstr);

    if (! $fp) {
        throw new Exception ( "Could not create socket: '" . $errnstr . "' (" . $errno . ")." );
    }

    $request = "GET /catalog HTTP/1.1\r\n";
    $request .= "Host: " . $host . "\r\n";
    $request .= "Connection: Close\r\n\r\n";

    fwrite ( $fp, $request );
    $response = "";
    while ( ! feof ( $fp ) ) {
        $response .= fgets ( $fp, 128 );
    }
    fclose ( $fp );

    // split headers from data
    $responseSplit = explode ( "\r\n\r\n", $response, 2 );
    $_json_data = json_decode($responseSplit[1]);
    
    return $_json_data;
}
