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
 * The mod_opendsa_activity user override updated event.
 *
 * @package    mod_opendsa_activity
 * @copyright  2015 Jean-Michel vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_opendsa_activity\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_opendsa_activity user override updated event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int opendsa_activity_id: the id of the opendsa_activity.
 * }
 *
 * @package    mod_opendsa_activity
 * @since      Moodle 2.9
 * @copyright  2015 Jean-Michel Vedrine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_override_updated extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'opendsa_activity_overrides';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventoverrideupdated', 'mod_opendsa_activity');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' updated the override with id '$this->objectid' for the opendsa_activity with " .
            "course module id '$this->contextinstanceid' for the user with id '{$this->relateduserid}'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/opendsa_activity/overrideedit.php', array('id' => $this->objectid));
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
    }

    public static function get_objectid_mapping() {
        return array('db' => 'opendsa_activity_overrides', 'restore' => 'opendsa_activity_override');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['opendsa_activity_id'] = array('db' => 'opendsa_activity', 'restore' => 'opendsa_activity');

        return $othermapped;
    }
}
