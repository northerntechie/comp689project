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
 * The mod_opendsa_activity highscore added event.
 *
 * @package    mod_opendsa_activity
 * @deprecated since Moodle 3.0
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace mod_opendsa_activity\event;

defined('MOODLE_INTERNAL') || die();

debugging('mod_opendsa_activity\event\highscore_added has been deprecated. Since the functionality no longer resides in the opendsa_activity module.',
        DEBUG_DEVELOPER);
/**
 * The mod_opendsa_activity highscore added event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int opendsa_activity_id: the id of the opendsa_activity in the opendsa_activity table.
 *      - string nickname: the user's nickname.
 * }
 *
 * @package    mod_opendsa_activity
 * @since      Moodle 2.7
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

class highscore_added extends \core\event\base {

    /**
     * Set basic properties for the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'opendsa_activity_high_scores';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventhighscoreadded', 'mod_opendsa_activity');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/opendsa_activity/highscores.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' added a new highscore to the opendsa_activity activity with course module " .
            "id '$this->contextinstanceid'.";
    }

    /**
     * Replace add_to_log() statement.
     *
     * @return array of parameters to be passed to legacy add_to_log() function.
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'opendsa_activity', 'update highscores', 'highscores.php?id=' . $this->contextinstanceid,
            $this->other['nickname'], $this->contextinstanceid);
    }

    /**
     * Custom validations.
     *
     * @throws \coding_exception when validation fails.
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['opendsa_activity_id'])) {
            throw new \coding_exception('The \'opendsa_activity_id\' value must be set in other.');
        }

        if (!isset($this->other['nickname'])) {
            throw new \coding_exception('The \'nickname\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        // The 'highscore' functionality was removed from core.
        return false;
    }

    public static function get_other_mapping() {
        // The 'highscore' functionality was removed from core.
        return false;
    }
}
