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
 * The mod_opendsa_activity essay assessed event.
 *
 * @package    mod_opendsa_activity
 * @copyright  2014 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_opendsa_activity\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_opendsa_activity essay assessed event class.
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - int opendsa_activity_id: The ID of the opendsa_activity.
 *     - int attemptid: The ID for the attempt.
 * }
 *
 * @package    mod_opendsa_activity
 * @since      Moodle 2.7
 * @copyright  2014 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class essay_assessed extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'opendsa_activity_grades';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has marked the essay with id '{$this->other['attemptid']}' and " .
            "recorded a mark '$this->objectid' in the opendsa_activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $opendsa_activity = $this->get_record_snapshot('opendsa_activity', $this->other['opendsa_activity_id']);
        return array($this->courseid, 'opendsa_activity', 'update grade', 'essay.php?id=' .
                $this->contextinstanceid, $opendsa_activity->name, $this->contextinstanceid);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventessayassessed', 'mod_opendsa_activity');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/opendsa_activity/essay.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['opendsa_activity_id'])) {
            throw new \coding_exception('The \'opendsa_activity_id\' value must be set in other.');
        }
        if (!isset($this->other['attemptid'])) {
            throw new \coding_exception('The \'attemptid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'opendsa_activity_grades', 'restore' => 'opendsa_activity_grade');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['opendsa_activity_id'] = array('db' => 'opendsa_activity', 'restore' => 'opendsa_activity');
        $othermapped['attemptid'] = array('db' => 'opendsa_activity_attempts', 'restore' => 'opendsa_activity_attept');

        return $othermapped;
    }
}
