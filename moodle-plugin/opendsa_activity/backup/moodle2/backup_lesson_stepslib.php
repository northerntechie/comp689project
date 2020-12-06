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
 * This file contains the backup structure for the opendsa_activity module
 *
 * This is the "graphical" structure of the opendsa_activity module:
 *
 *         opendsa_activity ---------->-------------|------------>---------|----------->----------|
 *      (CL,pk->id)                       |                      |                      |
 *            |                           |                      |                      |
 *            |                     opendsa_activity_grades           opendsa_activity_timer           opendsa_activity_overrides
 *            |            (UL, pk->id,fk->opendsa_activity_id)  (UL, pk->id,fk->opendsa_activity_id) (UL, pk->id,fk->opendsa_activity_id)
 *            |                           |
 *            |                           |
 *            |                           |
 *            |                           |
 *      opendsa_activity_pages-------->-------opendsa_activity_branch
 *   (CL,pk->id,fk->opendsa_activity_id)     (UL, pk->id,fk->pageid)
 *            |
 *            |
 *            |
 *      opendsa_activity_answers
 *   (CL,pk->id,fk->pageid)
 *            |
 *            |
 *            |
 *      opendsa_activity_attempts
 *  (UL,pk->id,fk->answerid)
 *
 * Meaning: pk->primary key field of the table
 *          fk->foreign key to link with parent
 *          nt->nested field (recursive data)
 *          CL->course level info
 *          UL->user level info
 *          files->table may have files)
 *
 * @package mod_opendsa_activity
 * @copyright  2010 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step class that informs a backup task how to backup the opendsa_activity module.
 *
 * @copyright  2010 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_opendsa_activity_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // The opendsa_activity table
        // This table contains all of the goodness for the opendsa_activity module, quite
        // alot goes into it but nothing relational other than course when will
        // need to be corrected upon restore.
        $opendsa_activity = new backup_nested_element('opendsa_activity', array('id'), array(
            'course', 'name', 'intro', 'introformat', 'practice', 'modattempts',
            'usepassword', 'password',
            'dependency', 'conditions', 'grade', 'custom', 'ongoing', 'usemaxgrade',
            'maxanswers', 'maxattempts', 'review', 'nextpagedefault', 'feedback',
            'minquestions', 'maxpages', 'timelimit', 'retake', 'activitylink',
            'mediafile', 'mediaheight', 'mediawidth', 'mediaclose', 'slideshow',
            'width', 'height', 'bgcolor', 'displayleft', 'displayleftif', 'progressbar',
            'available', 'deadline', 'timemodified',
            'completionendreached', 'completiontimespent', 'allowofflineattempts'
        ));

        // The opendsa_activity_pages table
        // Grouped within a `pages` element, important to note that page is relational
        // to the opendsa_activity, and also to the previous/next page in the series.
        // Upon restore prevpageid and nextpageid will need to be corrected.
        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', array('id'), array(
            'prevpageid','nextpageid','qtype','qoption','layout',
            'display','timecreated','timemodified','title','contents',
            'contentsformat'
        ));

        // The opendsa_activity_answers table
        // Grouped within an answers `element`, the opendsa_activity_answers table relates
        // to the page and opendsa_activity with `pageid` and `opendsa_activity_id` that will both need
        // to be corrected during restore.
        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', array('id'), array(
            'jumpto','grade','score','flags','timecreated','timemodified','answer_text',
            'response', 'answerformat', 'responseformat'
        ));
        // Tell the answer element about the answer_text elements mapping to the answer
        // database field.
        $answer->set_source_alias('answer', 'answer_text');

        // The opendsa_activity_attempts table
        // Grouped by an `attempts` element this is relational to the page, opendsa_activity,
        // and user.
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', array('id'), array(
            'userid','retry','correct','useranswer','timeseen'
        ));

        // The opendsa_activity_branch table
        // Grouped by a `branch` element this is relational to the page, opendsa_activity,
        // and user.
        $branches = new backup_nested_element('branches');
        $branch = new backup_nested_element('branch', array('id'), array(
             'userid', 'retry', 'flag', 'timeseen', 'nextpageid'
        ));

        // The opendsa_activity_grades table
        // Grouped by a grades element this is relational to the opendsa_activity and user.
        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', array('id'), array(
            'userid','grade','late','completed'
        ));

        // The opendsa_activity_timer table
        // Grouped by a `timers` element this is relational to the opendsa_activity and user.
        $timers = new backup_nested_element('timers');
        $timer = new backup_nested_element('timer', array('id'), array(
            'userid', 'starttime', 'opendsa_activitytime', 'completed', 'timemodifiedoffline'
        ));

        $overrides = new backup_nested_element('overrides');
        $override = new backup_nested_element('override', array('id'), array(
            'groupid', 'userid', 'available', 'deadline', 'timelimit',
            'review', 'maxattempts', 'retake', 'password'));

        // Now that we have all of the elements created we've got to put them
        // together correctly.
        $opendsa_activity->add_child($pages);
        $pages->add_child($page);
        $page->add_child($answers);
        $answers->add_child($answer);
        $answer->add_child($attempts);
        $attempts->add_child($attempt);
        $page->add_child($branches);
        $branches->add_child($branch);
        $opendsa_activity->add_child($grades);
        $grades->add_child($grade);
        $opendsa_activity->add_child($timers);
        $timers->add_child($timer);
        $opendsa_activity->add_child($overrides);
        $overrides->add_child($override);

        // Set the source table for the elements that aren't reliant on the user
        // at this point (opendsa_activity, opendsa_activity_pages, opendsa_activity_answers)
        $opendsa_activity->set_source_table('opendsa_activity', array('id' => backup::VAR_ACTIVITYID));
        //we use SQL here as it must be ordered by prevpageid so that restore gets the pages in the right order.
        $page->set_source_table('opendsa_activity_pages', array('opendsa_activity_id' => backup::VAR_PARENTID), 'prevpageid ASC');

        // We use SQL here as answers must be ordered by id so that the restore gets them in the right order
        $answer->set_source_table('opendsa_activity_answers', array('pageid' => backup::VAR_PARENTID), 'id ASC');

        // Lesson overrides to backup are different depending of user info.
        $overrideparams = array('opendsa_activity_id' => backup::VAR_PARENTID);

        // Check if we are also backing up user information
        if ($this->get_setting_value('userinfo')) {
            // Set the source table for elements that are reliant on the user
            // opendsa_activity_attempts, opendsa_activity_branch, opendsa_activity_grades, opendsa_activity_timer.
            $attempt->set_source_table('opendsa_activity_attempts', array('answerid' => backup::VAR_PARENTID));
            $branch->set_source_table('opendsa_activity_branch', array('pageid' => backup::VAR_PARENTID));
            $grade->set_source_table('opendsa_activity_grades', array('opendsa_activity_id'=>backup::VAR_PARENTID));
            $timer->set_source_table('opendsa_activity_timer', array('opendsa_activity_id' => backup::VAR_PARENTID));
        } else {
            $overrideparams['userid'] = backup_helper::is_sqlparam(null); //  Without userinfo, skip user overrides.
        }

        // Skip group overrides if not including groups.
        $groupinfo = $this->get_setting_value('groups');
        if (!$groupinfo) {
            $overrideparams['groupid'] = backup_helper::is_sqlparam(null);
        }

        $override->set_source_table('opendsa_activity_overrides', $overrideparams);

        // Annotate the user id's where required.
        $attempt->annotate_ids('user', 'userid');
        $branch->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'userid');
        $timer->annotate_ids('user', 'userid');
        $override->annotate_ids('user', 'userid');
        $override->annotate_ids('group', 'groupid');

        // Annotate the file areas in user by the opendsa_activity module.
        $opendsa_activity->annotate_files('mod_opendsa_activity', 'intro', null);
        $opendsa_activity->annotate_files('mod_opendsa_activity', 'mediafile', null);
        $page->annotate_files('mod_opendsa_activity', 'page_contents', 'id');
        $answer->annotate_files('mod_opendsa_activity', 'page_answers', 'id');
        $answer->annotate_files('mod_opendsa_activity', 'page_responses', 'id');
        $attempt->annotate_files('mod_opendsa_activity', 'essay_responses', 'id');
        $attempt->annotate_files('mod_opendsa_activity', 'essay_answers', 'id');

        // Prepare and return the structure we have just created for the opendsa_activity module.
        return $this->prepare_activity_structure($opendsa_activity);
    }
}
