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

/**
 * Define all the restore steps that will be used by the restore_opendsa_activity_activity_task
 */

/**
 * Structure step to restore one opendsa_activity activity
 */
class restore_opendsa_activity_activity_structure_step extends restore_activity_structure_step {
    // Store the answers as they're received but only process them at the
    // end of the opendsa_activity
    protected $answers = array();

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('opendsa_activity', '/activity/opendsa_activity');
        $paths[] = new restore_path_element('opendsa_activity_page', '/activity/opendsa_activity/pages/page');
        $paths[] = new restore_path_element('opendsa_activity_answer', '/activity/opendsa_activity/pages/page/answers/answer');
        $paths[] = new restore_path_element('opendsa_activity_override', '/activity/opendsa_activity/overrides/override');
        if ($userinfo) {
            $paths[] = new restore_path_element('opendsa_activity_attempt', '/activity/opendsa_activity/pages/page/answers/answer/attempts/attempt');
            $paths[] = new restore_path_element('opendsa_activity_grade', '/activity/opendsa_activity/grades/grade');
            $paths[] = new restore_path_element('opendsa_activity_branch', '/activity/opendsa_activity/pages/page/branches/branch');
            $paths[] = new restore_path_element('opendsa_activity_highscore', '/activity/opendsa_activity/highscores/highscore');
            $paths[] = new restore_path_element('opendsa_activity_timer', '/activity/opendsa_activity/timers/timer');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_opendsa_activity($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $data->available = $this->apply_date_offset($data->available);
        $data->deadline = $this->apply_date_offset($data->deadline);

        // The opendsa_activity->highscore code was removed in MDL-49581.
        // Remove it if found in the backup file.
        if (isset($data->showhighscores)) {
            unset($data->showhighscores);
        }
        if (isset($data->highscores)) {
            unset($data->highscores);
        }

        // Supply items that maybe missing from previous versions.
        if (!isset($data->completionendreached)) {
            $data->completionendreached = 0;
        }
        if (!isset($data->completiontimespent)) {
            $data->completiontimespent = 0;
        }

        if (!isset($data->intro)) {
            $data->intro = '';
            $data->introformat = FORMAT_HTML;
        }

        // Compatibility with old backups with maxtime and timed fields.
        if (!isset($data->timelimit)) {
            if (isset($data->timed) && isset($data->maxtime) && $data->timed) {
                $data->timelimit = 60 * $data->maxtime;
            } else {
                $data->timelimit = 0;
            }
        }
        // insert the opendsa_activity record
        $newitemid = $DB->insert_record('opendsa_activity', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_opendsa_activity_page($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->opendsa_activity_id = $this->get_new_parentid('opendsa_activity');

        // We'll remap all the prevpageid and nextpageid at the end, once all pages have been created

        $newitemid = $DB->insert_record('opendsa_activity_pages', $data);
        $this->set_mapping('opendsa_activity_page', $oldid, $newitemid, true); // Has related fileareas
    }

    protected function process_opendsa_activity_answer($data) {
        global $DB;

        $data = (object)$data;
        $data->opendsa_activity_id = $this->get_new_parentid('opendsa_activity');
        $data->pageid = $this->get_new_parentid('opendsa_activity_page');
        $data->answer = $data->answer_text;

        // Set a dummy mapping to get the old ID so that it can be used by get_old_parentid when
        // processing attempts. It will be corrected in after_execute
        $this->set_mapping('opendsa_activity_answer', $data->id, 0, true); // Has related fileareas.

        // Answers need to be processed in order, so we store them in an
        // instance variable and insert them in the after_execute stage
        $this->answers[$data->id] = $data;
    }

    protected function process_opendsa_activity_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->opendsa_activity_id = $this->get_new_parentid('opendsa_activity');
        $data->pageid = $this->get_new_parentid('opendsa_activity_page');

        // We use the old answerid here as the answer isn't created until after_execute
        $data->answerid = $this->get_old_parentid('opendsa_activity_answer');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('opendsa_activity_attempts', $data);
        $this->set_mapping('opendsa_activity_attempt', $oldid, $newitemid, true); // Has related fileareas.
    }

    protected function process_opendsa_activity_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->opendsa_activity_id = $this->get_new_parentid('opendsa_activity');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('opendsa_activity_grades', $data);
        $this->set_mapping('opendsa_activity_grade', $oldid, $newitemid);
    }

    protected function process_opendsa_activity_branch($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->opendsa_activity_id = $this->get_new_parentid('opendsa_activity');
        $data->pageid = $this->get_new_parentid('opendsa_activity_page');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('opendsa_activity_branch', $data);
    }

    protected function process_opendsa_activity_highscore($data) {
        // Do not process any high score data.
        // high scores were removed in Moodle 3.0 See MDL-49581.
    }

    protected function process_opendsa_activity_timer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->opendsa_activity_id = $this->get_new_parentid('opendsa_activity');
        $data->userid = $this->get_mappingid('user', $data->userid);
        // Supply item that maybe missing from previous versions.
        if (!isset($data->completed)) {
            $data->completed = 0;
        }
        $newitemid = $DB->insert_record('opendsa_activity_timer', $data);
    }

    /**
     * Process a opendsa_activity override restore
     * @param object $data The data in object form
     * @return void
     */
    protected function process_opendsa_activity_override($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Based on userinfo, we'll restore user overides or no.
        $userinfo = $this->get_setting_value('userinfo');

        // Skip user overrides if we are not restoring userinfo.
        if (!$userinfo && !is_null($data->userid)) {
            return;
        }

        $data->opendsa_activity_id = $this->get_new_parentid('opendsa_activity');

        if (!is_null($data->userid)) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }
        if (!is_null($data->groupid)) {
            $data->groupid = $this->get_mappingid('group', $data->groupid);
        }

        // Skip if there is no user and no group data.
        if (empty($data->userid) && empty($data->groupid)) {
            return;
        }

        $data->available = $this->apply_date_offset($data->available);
        $data->deadline = $this->apply_date_offset($data->deadline);

        $newitemid = $DB->insert_record('opendsa_activity_overrides', $data);

        // Add mapping, restore of logs needs it.
        $this->set_mapping('opendsa_activity_override', $oldid, $newitemid);
    }

    protected function after_execute() {
        global $DB;

        // Answers must be sorted by id to ensure that they're shown correctly
        ksort($this->answers);
        foreach ($this->answers as $answer) {
            $newitemid = $DB->insert_record('opendsa_activity_answers', $answer);
            $this->set_mapping('opendsa_activity_answer', $answer->id, $newitemid, true);

            // Update the opendsa_activity attempts to use the newly created answerid
            $DB->set_field('opendsa_activity_attempts', 'answerid', $newitemid, array(
                    'opendsa_activity_id' => $answer->opendsa_activity_id,
                    'pageid' => $answer->pageid,
                    'answerid' => $answer->id));
        }

        // Add opendsa_activity files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_opendsa_activity', 'intro', null);
        $this->add_related_files('mod_opendsa_activity', 'mediafile', null);
        // Add opendsa_activity page files, by opendsa_activity_page itemname
        $this->add_related_files('mod_opendsa_activity', 'page_contents', 'opendsa_activity_page');
        $this->add_related_files('mod_opendsa_activity', 'page_answers', 'opendsa_activity_answer');
        $this->add_related_files('mod_opendsa_activity', 'page_responses', 'opendsa_activity_answer');
        $this->add_related_files('mod_opendsa_activity', 'essay_responses', 'opendsa_activity_attempt');
        $this->add_related_files('mod_opendsa_activity', 'essay_answers', 'opendsa_activity_attempt');

        // Remap all the restored prevpageid and nextpageid now that we have all the pages and their mappings
        $rs = $DB->get_recordset('opendsa_activity_pages', array('opendsa_activity_id' => $this->task->get_activityid()),
                                 '', 'id, prevpageid, nextpageid');
        foreach ($rs as $page) {
            $page->prevpageid = (empty($page->prevpageid)) ? 0 : $this->get_mappingid('opendsa_activity_page', $page->prevpageid);
            $page->nextpageid = (empty($page->nextpageid)) ? 0 : $this->get_mappingid('opendsa_activity_page', $page->nextpageid);
            $DB->update_record('opendsa_activity_pages', $page);
        }
        $rs->close();

        // Remap all the restored 'jumpto' fields now that we have all the pages and their mappings
        $rs = $DB->get_recordset('opendsa_activity_answers', array('opendsa_activity_id' => $this->task->get_activityid()),
                                 '', 'id, jumpto');
        foreach ($rs as $answer) {
            if ($answer->jumpto > 0) {
                $answer->jumpto = $this->get_mappingid('opendsa_activity_page', $answer->jumpto);
                $DB->update_record('opendsa_activity_answers', $answer);
            }
        }
        $rs->close();

        // Remap all the restored 'nextpageid' fields now that we have all the pages and their mappings.
        $rs = $DB->get_recordset('opendsa_activity_branch', array('opendsa_activity_id' => $this->task->get_activityid()),
                                 '', 'id, nextpageid');
        foreach ($rs as $answer) {
            if ($answer->nextpageid > 0) {
                $answer->nextpageid = $this->get_mappingid('opendsa_activity_page', $answer->nextpageid);
                $DB->update_record('opendsa_activity_branch', $answer);
            }
        }
        $rs->close();

        // Replay the upgrade step 2015030301
        // to clean opendsa_activity answers that should be plain text.
        // 1 = OPENDSA_ACTIVITY_PAGE_SHORTANSWER, 8 = OPENDSA_ACTIVITY_PAGE_NUMERICAL, 20 = OPENDSA_ACTIVITY_PAGE_BRANCHTABLE.

        $sql = 'SELECT a.*
                  FROM {opendsa_activity_answers} a
                  JOIN {opendsa_activity_pages} p ON p.id = a.pageid
                 WHERE a.answerformat <> :format
                   AND a.opendsa_activity_id = :opendsa_activity_id
                   AND p.qtype IN (1, 8, 20)';
        $badanswers = $DB->get_recordset_sql($sql, array('opendsa_activity_id' => $this->task->get_activityid(), 'format' => FORMAT_MOODLE));

        foreach ($badanswers as $badanswer) {
            // Strip tags from answer text and convert back the format to FORMAT_MOODLE.
            $badanswer->answer = strip_tags($badanswer->answer);
            $badanswer->answerformat = FORMAT_MOODLE;
            $DB->update_record('opendsa_activity_answers', $badanswer);
        }
        $badanswers->close();

        // Replay the upgrade step 2015032700.
        // Delete any orphaned opendsa_activity_branch record.
        if ($DB->get_dbfamily() === 'mysql') {
            $sql = "DELETE {opendsa_activity_branch}
                      FROM {opendsa_activity_branch}
                 LEFT JOIN {opendsa_activity_pages}
                        ON {opendsa_activity_branch}.pageid = {opendsa_activity_pages}.id
                     WHERE {opendsa_activity_pages}.id IS NULL";
        } else {
            $sql = "DELETE FROM {opendsa_activity_branch}
               WHERE NOT EXISTS (
                         SELECT 'x' FROM {opendsa_activity_pages}
                          WHERE {opendsa_activity_branch}.pageid = {opendsa_activity_pages}.id)";
        }

        $DB->execute($sql);
    }
}
