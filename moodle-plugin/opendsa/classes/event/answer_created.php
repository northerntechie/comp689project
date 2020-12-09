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
 * The mod_opendsa answer created event.
 *
 * @package    mod_opendsa
 * @copyright  2016 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_opendsa\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_opendsa answer created event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int opendsaid: id of opendsa.
 *      - int optionid: id of the option.
 * }
 *
 * @package    mod_opendsa
 * @since      Moodle 3.2
 * @copyright  2016 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_created extends \core\event\base {

    /**
     * Creates an instance of the event from the records
     *
     * @param stdClass $opendsaanswer record from 'opendsa_answers' table
     * @param stdClass $opendsa record from 'opendsa' table
     * @param stdClass $cm record from 'course_modules' table
     * @param stdClass $course
     * @return self
     */
    public static function create_from_object($opendsaanswer, $opendsa, $cm, $course) {
        global $USER;
        $eventdata = array();
        $eventdata['objectid'] = $opendsaanswer->id;
        $eventdata['context'] = \context_module::instance($cm->id);
        $eventdata['userid'] = $USER->id;
        $eventdata['courseid'] = $course->id;
        $eventdata['relateduserid'] = $opendsaanswer->userid;
        $eventdata['other'] = array();
        $eventdata['other']['opendsaid'] = $opendsa->id;
        $eventdata['other']['optionid'] = $opendsaanswer->optionid;
        $event = self::create($eventdata);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('opendsa', $opendsa);
        $event->add_record_snapshot('opendsa_answers', $opendsaanswer);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has added the option with id '" . $this->other['optionid'] . "' for the
            user with id '$this->relateduserid' from the opendsa activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventanswercreated', 'mod_opendsa');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/opendsa/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'opendsa_answers';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['opendsaid'])) {
            throw new \coding_exception('The \'opendsaid\' value must be set in other.');
        }

        if (!isset($this->other['optionid'])) {
            throw new \coding_exception('The \'optionid\' value must be set in other.');
        }
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the objectid to it's new value in the new course.
     *
     * @return string the name of the restore mapping the objectid links to
     */
    public static function get_objectid_mapping() {
        return array('db' => 'opendsa_answers', 'restore' => 'answer');
    }

    /**
     * This is used when restoring course logs where it is required that we
     * map the information in 'other' to it's new value in the new course.
     *
     * @return array an array of other values and their corresponding mapping
     */
    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['opendsaid'] = array('db' => 'opendsa', 'restore' => 'opendsa');
        $othermapped['optionid'] = array('db' => 'opendsa_options', 'restore' => 'opendsa_option');

        return $othermapped;
    }
}
