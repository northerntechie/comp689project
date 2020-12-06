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
 * @package mod_opendsa_activity
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/opendsa_activity/backup/moodle2/restore_opendsa_activity_stepslib.php'); // Because it exists (must)

/**
 * opendsa_activity restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_opendsa_activity_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // opendsa_activity only has one structure step
        $this->add_step(new restore_opendsa_activity_activity_structure_step('opendsa_activity_structure', 'opendsa_activity.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('opendsa_activity', array('intro'), 'opendsa_activity');
        $contents[] = new restore_decode_content('opendsa_activity_pages', array('contents'), 'opendsa_activity_page');
        $contents[] = new restore_decode_content('opendsa_activity_answers', array('answer', 'response'), 'opendsa_activity_answer');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('OPENDSA_ACTIVITYEDIT', '/mod/opendsa_activity/edit.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('OPENDSA_ACTIVITYESAY', '/mod/opendsa_activity/essay.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('OPENDSA_ACTIVITYREPORT', '/mod/opendsa_activity/report.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('OPENDSA_ACTIVITYMEDIAFILE', '/mod/opendsa_activity/mediafile.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('OPENDSA_ACTIVITYVIEWBYID', '/mod/opendsa_activity/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('OPENDSA_ACTIVITYINDEX', '/mod/opendsa_activity/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('OPENDSA_ACTIVITYVIEWPAGE', '/mod/opendsa_activity/view.php?id=$1&pageid=$2', array('course_module', 'opendsa_activity_page'));
        $rules[] = new restore_decode_rule('OPENDSA_ACTIVITYEDITPAGE', '/mod/opendsa_activity/edit.php?id=$1&pageid=$2', array('course_module', 'opendsa_activity_page'));

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * opendsa_activity logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('opendsa_activity', 'add', 'view.php?id={course_module}', '{opendsa_activity}');
        $rules[] = new restore_log_rule('opendsa_activity', 'update', 'view.php?id={course_module}', '{opendsa_activity}');
        $rules[] = new restore_log_rule('opendsa_activity', 'view', 'view.php?id={course_module}', '{opendsa_activity}');
        $rules[] = new restore_log_rule('opendsa_activity', 'start', 'view.php?id={course_module}', '{opendsa_activity}');
        $rules[] = new restore_log_rule('opendsa_activity', 'end', 'view.php?id={course_module}', '{opendsa_activity}');
        $rules[] = new restore_log_rule('opendsa_activity', 'view grade', 'essay.php?id={course_module}', '[name]');
        $rules[] = new restore_log_rule('opendsa_activity', 'update grade', 'essay.php?id={course_module}', '[name]');
        $rules[] = new restore_log_rule('opendsa_activity', 'update email essay grade', 'essay.php?id={course_module}', '[name]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('opendsa_activity', 'view all', 'index.php?id={course}', null);

        return $rules;
    }


    /**
     * Re-map the dependency and activitylink information
     * If a depency or activitylink has no mapping in the backup data then it could either be a duplication of a
     * opendsa_activity, or a backup/restore of a single opendsa_activity. We have no way to determine which and whether this is the
     * same site and/or course. Therefore we try and retrieve a mapping, but fallback to the original value if one
     * was not found. We then test to see whether the value found is valid for the course being restored into.
     */
    public function after_restore() {
        global $DB;

        $opendsa_activity = $DB->get_record('opendsa_activity', array('id' => $this->get_activityid()), 'id, course, dependency, activitylink');
        $updaterequired = false;

        if (!empty($opendsa_activity->dependency)) {
            $updaterequired = true;
            if ($newitem = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'opendsa_activity', $opendsa_activity->dependency)) {
                $opendsa_activity->dependency = $newitem->newitemid;
            }
            if (!$DB->record_exists('opendsa_activity', array('id' => $opendsa_activity->dependency, 'course' => $opendsa_activity->course))) {
                $opendsa_activity->dependency = 0;
            }
        }

        if (!empty($opendsa_activity->activitylink)) {
            $updaterequired = true;
            if ($newitem = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $opendsa_activity->activitylink)) {
                $opendsa_activity->activitylink = $newitem->newitemid;
            }
            if (!$DB->record_exists('course_modules', array('id' => $opendsa_activity->activitylink, 'course' => $opendsa_activity->course))) {
                $opendsa_activity->activitylink = 0;
            }
        }

        if ($updaterequired) {
            $DB->update_record('opendsa_activity', $opendsa_activity);
        }
    }
}
