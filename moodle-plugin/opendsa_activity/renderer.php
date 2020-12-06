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
 * Moodle renderer used to display special elements of the opendsa_activity module
 *
 * @package mod_opendsa_activity
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

class mod_opendsa_activity_renderer extends plugin_renderer_base {
    /**
     * Returns the header for the opendsa_activity module
     *
     * @param opendsa_activity $opendsa_activity a opendsa_activity object.
     * @param string $currenttab current tab that is shown.
     * @param bool   $extraeditbuttons if extra edit buttons should be displayed.
     * @param int    $opendsa_activitypageid id of the opendsa_activity page that needs to be displayed.
     * @param string $extrapagetitle String to appent to the page title.
     * @return string
     */
    public function header($opendsa_activity, $cm, $currenttab = '', $extraeditbuttons = false, $opendsa_activitypageid = null, $extrapagetitle = null) {
        global $CFG;

        $activityname = format_string($opendsa_activity->name, true, $opendsa_activity->course);
        if (empty($extrapagetitle)) {
            $title = $this->page->course->shortname.": ".$activityname;
        } else {
            $title = $this->page->course->shortname.": ".$activityname.": ".$extrapagetitle;
        }

        // Build the buttons
        $context = context_module::instance($cm->id);

        // Header setup.
        $this->page->set_title($title);
        $this->page->set_heading($this->page->course->fullname);
        opendsa_activity_add_header_buttons($cm, $context, $extraeditbuttons, $opendsa_activitypageid);
        $output = $this->output->header();

        if (has_capability('mod/opendsa_activity:manage', $context)) {
            $output .= $this->output->heading_with_help($activityname, 'overview', 'opendsa_activity');
            // Info box.
            if ($opendsa_activity->intro) {
                $output .= $this->output->box(format_module_intro('opendsa_activity', $opendsa_activity, $cm->id), 'generalbox', 'intro');
            }
            if (!empty($currenttab)) {
                ob_start();
                include($CFG->dirroot.'/mod/opendsa_activity/tabs.php');
                $output .= ob_get_contents();
                ob_end_clean();
            }
        } else {
            $output .= $this->output->heading($activityname);
            // Info box.
            if ($opendsa_activity->intro) {
                $output .= $this->output->box(format_module_intro('opendsa_activity', $opendsa_activity, $cm->id), 'generalbox', 'intro');
            }
        }

        foreach ($opendsa_activity->messages as $message) {
            $output .= $this->output->notification($message[0], $message[1], $message[2]);
        }

        return $output;
    }

    /**
     * Returns the footer
     * @return string
     */
    public function footer() {
        return $this->output->footer();
    }

    /**
     * Returns HTML for a opendsa_activity inaccessible message
     *
     * @param string $message
     * @return <type>
     */
    public function opendsa_activity_inaccessible($message) {
        global $CFG;
        $output  =  $this->output->box_start('generalbox boxaligncenter');
        $output .=  $this->output->box_start('center');
        $output .=  $message;
        $output .=  $this->output->box('<a href="'.$CFG->wwwroot.'/course/view.php?id='. $this->page->course->id .'">'. get_string('returnto', 'opendsa_activity', format_string($this->page->course->fullname, true)) .'</a>', 'opendsa_activitybutton standardbutton');
        $output .=  $this->output->box_end();
        $output .=  $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to prompt the user to log in
     * @param opendsa_activity $opendsa_activity
     * @param bool $failedattempt
     * @return string
     */
    public function login_prompt(opendsa_activity $opendsa_activity, $failedattempt = false) {
        global $CFG;
        $output  = $this->output->box_start('password-form');
        $output .= $this->output->box_start('generalbox boxaligncenter');
        $output .=  '<form id="password" method="post" action="'.$CFG->wwwroot.'/mod/opendsa_activity/view.php" autocomplete="off">';
        $output .=  '<fieldset class="invisiblefieldset center">';
        $output .=  '<input type="hidden" name="id" value="'. $this->page->cm->id .'" />';
        $output .=  '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        if ($failedattempt) {
            $output .=  $this->output->notification(get_string('loginfail', 'opendsa_activity'));
        }
        $output .= get_string('passwordprotectedopendsa_activity', 'opendsa_activity', format_string($opendsa_activity->name)).'<br /><br />';
        $output .= get_string('enterpassword', 'opendsa_activity')." <input type=\"password\" name=\"userpassword\" /><br /><br />";
        $output .= "<div class='opendsa_activitybutton standardbutton submitbutton'><input type='submit' value='".get_string('continue', 'opendsa_activity')."' /></div>";
        $output .= " <div class='opendsa_activitybutton standardbutton submitbutton'><input type='submit' name='backtocourse' value='".get_string('cancel', 'opendsa_activity')."' /></div>";
        $output .=  '</fieldset></form>';
        $output .=  $this->output->box_end();
        $output .=  $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display dependancy errors
     *
     * @param object $dependentopendsa_activity
     * @param array $errors
     * @return string
     */
    public function dependancy_errors($dependentopendsa_activity, $errors) {
        $output  = $this->output->box_start('generalbox boxaligncenter');
        $output .= get_string('completethefollowingconditions', 'opendsa_activity', $dependentopendsa_activity->name);
        $output .= $this->output->box(implode('<br />'.get_string('and', 'opendsa_activity').'<br />', $errors),'center');
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display a message
     * @param string $message
     * @param single_button $button
     * @return string
     */
    public function message($message, single_button $button = null) {
        $output  = $this->output->box_start('generalbox boxaligncenter');
        $output .= $message;
        if ($button !== null) {
            $output .= $this->output->box($this->output->render($button), 'opendsa_activitybutton standardbutton');
        }
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display a continue button
     * @param opendsa_activity $opendsa_activity
     * @param int $lastpageseen
     * @return string
     */
    public function continue_links(opendsa_activity $opendsa_activity, $lastpageseenid) {
        global $CFG;
        $output = $this->output->box(get_string('youhaveseen','opendsa_activity'), 'generalbox boxaligncenter');
        $output .= $this->output->box_start('center');

        $yeslink = html_writer::link(new moodle_url('/mod/opendsa_activity/view.php', array('id' => $this->page->cm->id,
            'pageid' => $lastpageseenid, 'startlastseen' => 'yes')), get_string('yes'), array('class' => 'btn btn-primary'));
        $output .= html_writer::tag('span', $yeslink, array('class'=>'opendsa_activitybutton standardbutton'));
        $output .= '&nbsp;';

        $nolink = html_writer::link(new moodle_url('/mod/opendsa_activity/view.php', array('id' => $this->page->cm->id,
            'pageid' => $opendsa_activity->firstpageid, 'startlastseen' => 'no')), get_string('no'), array('class' => 'btn btn-secondary'));
        $output .= html_writer::tag('span', $nolink, array('class'=>'opendsa_activitybutton standardbutton'));

        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Returns HTML to display a page to the user
     * @param opendsa_activity $opendsa_activity
     * @param opendsa_activity_page $page
     * @param object $attempt
     * @return string
     */
    public function display_page(opendsa_activity $opendsa_activity, opendsa_activity_page $page, $attempt) {
        // We need to buffer here as there is an mforms display call
        ob_start();
        echo $page->display($this, $attempt);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Returns HTML to display a collapsed edit form
     *
     * @param opendsa_activity $opendsa_activity
     * @param int $pageid
     * @return string
     */
    public function display_edit_collapsed(opendsa_activity $opendsa_activity, $pageid) {
        global $DB, $CFG;

        $manager = opendsa_activity_page_type_manager::get($opendsa_activity);
        $qtypes = $manager->get_page_type_strings();
        $npages = count($opendsa_activity->load_all_pages());

        $table = new html_table();
        $table->head = array(get_string('pagetitle', 'opendsa_activity'), get_string('qtype', 'opendsa_activity'), get_string('jumps', 'opendsa_activity'), get_string('actions', 'opendsa_activity'));
        $table->align = array('left', 'left', 'left', 'center');
        $table->wrap = array('', 'nowrap', '', 'nowrap');
        $table->tablealign = 'center';
        $table->cellspacing = 0;
        $table->cellpadding = '2px';
        $table->width = '80%';
        $table->data = array();

        $canedit = has_capability('mod/opendsa_activity:edit', context_module::instance($this->page->cm->id));

        while ($pageid != 0) {
            $page = $opendsa_activity->load_page($pageid);
            $data = array();
            $url = new moodle_url('/mod/opendsa_activity/edit.php', array(
                'id'     => $this->page->cm->id,
                'mode'   => 'single',
                'pageid' => $page->id
            ));
            $data[] = html_writer::link($url, format_string($page->title, true), array('id' => 'opendsa_activity-' . $page->id));
            $data[] = $qtypes[$page->qtype];
            $data[] = implode("<br />\n", $page->jumps);
            if ($canedit) {
                $data[] = $this->page_action_links($page, $npages, true);
            } else {
                $data[] = '';
            }
            $table->data[] = $data;
            $pageid = $page->nextpageid;
        }

        return html_writer::table($table);
    }

    /**
     * Returns HTML to display the full edit page
     *
     * @param opendsa_activity $opendsa_activity
     * @param int $pageid
     * @param int $prevpageid
     * @param bool $single
     * @return string
     */
    public function display_edit_full(opendsa_activity $opendsa_activity, $pageid, $prevpageid, $single=false) {
        global $DB, $CFG;

        $manager = opendsa_activity_page_type_manager::get($opendsa_activity);
        $qtypes = $manager->get_page_type_strings();
        $npages = count($opendsa_activity->load_all_pages());
        $canedit = has_capability('mod/opendsa_activity:edit', context_module::instance($this->page->cm->id));

        $content = '';
        if ($canedit) {
            $content = $this->add_page_links($opendsa_activity, $prevpageid);
        }

        $options = new stdClass;
        $options->noclean = true;

        while ($pageid != 0 && $single!=='stop') {
            $page = $opendsa_activity->load_page($pageid);

            $pagetable = new html_table();
            $pagetable->align = array('right','left');
            $pagetable->width = '100%';
            $pagetable->tablealign = 'center';
            $pagetable->cellspacing = 0;
            $pagetable->cellpadding = '5px';
            $pagetable->data = array();

            $pageheading = new html_table_cell();

            $pageheading->text = html_writer::tag('a', '', array('id' => 'opendsa_activity-' . $pageid)) . format_string($page->title);
            if ($canedit) {
                $pageheading->text .= ' '.$this->page_action_links($page, $npages);
            }
            $pageheading->style = 'text-align:center';
            $pageheading->colspan = 2;
            $pageheading->scope = 'col';
            $pagetable->head = array($pageheading);

            $cell = new html_table_cell();
            $cell->colspan = 2;
            $cell->style = 'text-align:left';
            $cell->text = $page->contents;
            $pagetable->data[] = new html_table_row(array($cell));

            $cell = new html_table_cell();
            $cell->colspan = 2;
            $cell->style = 'text-align:center';
            $cell->text = '<strong>'.$qtypes[$page->qtype] . $page->option_description_string().'</strong>';
            $pagetable->data[] = new html_table_row(array($cell));

            $pagetable = $page->display_answers($pagetable);

            $content .= html_writer::start_tag('div');
            $content .= html_writer::table($pagetable);
            $content .= html_writer::end_tag('div');

            if ($canedit) {
                $content .= $this->add_page_links($opendsa_activity, $pageid);
            }

            // check the prev links - fix (silently) if necessary - there was a bug in
            // versions 1 and 2 when add new pages. Not serious then as the backwards
            // links were not used in those versions
            if ($page->prevpageid != $prevpageid) {
                // fix it
                $DB->set_field("opendsa_activity_pages", "prevpageid", $prevpageid, array("id" => $page->id));
                debugging("<p>***prevpageid of page $page->id set to $prevpageid***");
            }

            $prevpageid = $page->id;
            $pageid = $page->nextpageid;

            if ($single === true) {
                $single = 'stop';
            }

        }

        return $this->output->box($content, 'edit_pages_box');
    }

    /**
     * Returns HTML to display the add page links
     *
     * @param opendsa_activity $opendsa_activity
     * @param int $prevpageid
     * @return string
     */
    public function add_page_links(opendsa_activity $opendsa_activity, $prevpageid=false) {
        global $CFG;

        $links = array();

        $importquestionsurl = new moodle_url('/mod/opendsa_activity/import.php',array('id'=>$this->page->cm->id, 'pageid'=>$prevpageid));
        $links[] = html_writer::link($importquestionsurl, get_string('importquestions', 'opendsa_activity'));

        $manager = opendsa_activity_page_type_manager::get($opendsa_activity);
        foreach($manager->get_add_page_type_links($prevpageid) as $link) {
            $links[] = html_writer::link($link['addurl'], $link['name']);
        }

        $addquestionurl = new moodle_url('/mod/opendsa_activity/editpage.php', array('id'=>$this->page->cm->id, 'pageid'=>$prevpageid));
        $links[] = html_writer::link($addquestionurl, get_string('addaquestionpagehere', 'opendsa_activity'));

        return $this->output->box(implode(" | \n", $links), 'addlinks');
    }

    /**
     * Return HTML to display add first page links
     * @param opendsa_activity $opendsa_activity
     * @return string
     */
    public function add_first_page_links(opendsa_activity $opendsa_activity) {
        global $CFG;
        $prevpageid = 0;

        $output = $this->output->heading(get_string("whatdofirst", "opendsa_activity"), 3);
        $links = array();

        $importquestionsurl = new moodle_url('/mod/opendsa_activity/import.php',array('id'=>$this->page->cm->id, 'pageid'=>$prevpageid));
        $links[] = html_writer::link($importquestionsurl, get_string('importquestions', 'opendsa_activity'));

        $manager = opendsa_activity_page_type_manager::get($opendsa_activity);
        foreach ($manager->get_add_page_type_links($prevpageid) as $link) {
            $link['addurl']->param('firstpage', 1);
            $links[] = html_writer::link($link['addurl'], $link['name']);
        }

        $addquestionurl = new moodle_url('/mod/opendsa_activity/editpage.php', array('id'=>$this->page->cm->id, 'pageid'=>$prevpageid, 'firstpage'=>1));
        $links[] = html_writer::link($addquestionurl, get_string('addaquestionpage', 'opendsa_activity'));

        return $this->output->box($output.'<p>'.implode('</p><p>', $links).'</p>', 'generalbox firstpageoptions');
    }

    /**
     * Returns HTML to display action links for a page
     *
     * @param opendsa_activity_page $page
     * @param bool $printmove
     * @param bool $printaddpage
     * @return string
     */
    public function page_action_links(opendsa_activity_page $page, $printmove, $printaddpage=false) {
        global $CFG;

        $actions = array();

        if ($printmove) {
            $url = new moodle_url('/mod/opendsa_activity/opendsa_activity.php',
                    array('id' => $this->page->cm->id, 'action' => 'move', 'pageid' => $page->id, 'sesskey' => sesskey()));
            $label = get_string('movepagenamed', 'opendsa_activity', format_string($page->title));
            $img = $this->output->pix_icon('t/move', $label);
            $actions[] = html_writer::link($url, $img, array('title' => $label));
        }
        $url = new moodle_url('/mod/opendsa_activity/editpage.php', array('id' => $this->page->cm->id, 'pageid' => $page->id, 'edit' => 1));
        $label = get_string('updatepagenamed', 'opendsa_activity', format_string($page->title));
        $img = $this->output->pix_icon('t/edit', $label);
        $actions[] = html_writer::link($url, $img, array('title' => $label));

        // Duplicate action.
        $url = new moodle_url('/mod/opendsa_activity/opendsa_activity.php', array('id' => $this->page->cm->id, 'pageid' => $page->id,
                'action' => 'duplicate', 'sesskey' => sesskey()));
        $label = get_string('duplicatepagenamed', 'opendsa_activity', format_string($page->title));
        $img = $this->output->pix_icon('e/copy', $label, 'mod_opendsa_activity');
        $actions[] = html_writer::link($url, $img, array('title' => $label));

        $url = new moodle_url('/mod/opendsa_activity/view.php', array('id' => $this->page->cm->id, 'pageid' => $page->id));
        $label = get_string('previewpagenamed', 'opendsa_activity', format_string($page->title));
        $img = $this->output->pix_icon('t/preview', $label);
        $actions[] = html_writer::link($url, $img, array('title' => $label));

        $url = new moodle_url('/mod/opendsa_activity/opendsa_activity.php',
                array('id' => $this->page->cm->id, 'action' => 'confirmdelete', 'pageid' => $page->id, 'sesskey' => sesskey()));
        $label = get_string('deletepagenamed', 'opendsa_activity', format_string($page->title));
        $img = $this->output->pix_icon('t/delete', $label);
        $actions[] = html_writer::link($url, $img, array('title' => $label));

        if ($printaddpage) {
            $options = array();
            $manager = opendsa_activity_page_type_manager::get($page->opendsa_activity);
            $links = $manager->get_add_page_type_links($page->id);
            foreach ($links as $link) {
                $options[$link['type']] = $link['name'];
            }
            $options[0] = get_string('addaquestionpage', 'opendsa_activity');

            $addpageurl = new moodle_url('/mod/opendsa_activity/editpage.php', array('id'=>$this->page->cm->id, 'pageid'=>$page->id, 'sesskey'=>sesskey()));
            $addpageselect = new single_select($addpageurl, 'qtype', $options, null, array(''=>get_string('addanewpage', 'opendsa_activity').'...'), 'addpageafter'.$page->id);
            $addpageselector = $this->output->render($addpageselect);
        }

        if (isset($addpageselector)) {
            $actions[] = $addpageselector;
        }

        return implode(' ', $actions);
    }

    /**
     * Prints the on going message to the user.
     *
     * With custom grading On, displays points
     * earned out of total points possible thus far.
     * With custom grading Off, displays number of correct
     * answers out of total attempted.
     *
     * @param object $opendsa_activity The opendsa_activity that the user is taking.
     * @return void
     **/

     /**
      * Prints the on going message to the user.
      *
      * With custom grading On, displays points
      * earned out of total points possible thus far.
      * With custom grading Off, displays number of correct
      * answers out of total attempted.
      *
      * @param opendsa_activity $opendsa_activity
      * @return string
      */
    public function ongoing_score(opendsa_activity $opendsa_activity) {
        return $this->output->box($opendsa_activity->get_ongoing_score_message(), "ongoing center");
    }

    /**
     * Returns HTML to display a progress bar of progression through a opendsa_activity
     *
     * @param opendsa_activity $opendsa_activity
     * @param int $progress optional, if empty it will be calculated
     * @return string
     */
    public function progress_bar(opendsa_activity $opendsa_activity, $progress = null) {
        $context = context_module::instance($this->page->cm->id);

        // opendsa_activity setting to turn progress bar on or off
        if (!$opendsa_activity->progressbar) {
            return '';
        }

        // catch teachers
        if (has_capability('mod/opendsa_activity:manage', $context)) {
            return $this->output->notification(get_string('progressbarteacherwarning2', 'opendsa_activity'));
        }

        if ($progress === null) {
            $progress = $opendsa_activity->calculate_progress();
        }

        $content = html_writer::start_tag('div');
        $content .= html_writer::start_tag('div', array('class' => 'progress'));
        $content .= html_writer::start_tag('div', array('class' => 'progress-bar bar', 'role' => 'progressbar',
            'style' => 'width: ' . $progress .'%', 'aria-valuenow' => $progress, 'aria-valuemin' => 0, 'aria-valuemax' => 100));
        $content .= $progress . "%";
        $content .= html_writer::end_tag('div');
        $content .= html_writer::end_tag('div');
        $printprogress = html_writer::tag('div', get_string('progresscompleted', 'opendsa_activity', $progress) . $content);
        return $this->output->box($printprogress, 'progress_bar');
    }

    /**
     * Returns HTML to show the start of a slideshow
     * @param opendsa_activity $opendsa_activity
     */
    public function slideshow_start(opendsa_activity $opendsa_activity) {
        $attributes = array();
        $attributes['class'] = 'slideshow';
        $attributes['style'] = 'background-color:'.$opendsa_activity->properties()->bgcolor.';height:'.
                $opendsa_activity->properties()->height.'px;width:'.$opendsa_activity->properties()->width.'px;';
        $output = html_writer::start_tag('div', $attributes);
        return $output;
    }
    /**
     * Returns HTML to show the end of a slideshow
     */
    public function slideshow_end() {
        $output = html_writer::end_tag('div');
        return $output;
    }
    /**
     * Returns a P tag containing contents
     * @param string $contents
     * @param string $class
     */
    public function paragraph($contents, $class='') {
        $attributes = array();
        if ($class !== '') {
            $attributes['class'] = $class;
        }
        $output = html_writer::tag('p', $contents, $attributes);
        return $output;
    }

    /**
     * Returns the HTML for displaying the end of opendsa_activity page.
     *
     * @param  opendsa_activity $opendsa_activity opendsa_activity instance
     * @param  stdclass $data opendsa_activity data to be rendered
     * @return string         HTML contents
     */
    public function display_eol_page(opendsa_activity $opendsa_activity, $data) {

        $output = '';
        $canmanage = $opendsa_activity->can_manage();
        $course = $opendsa_activity->courserecord;

        if ($opendsa_activity->custom && !$canmanage && (($data->gradeinfo->nquestions < $opendsa_activity->minquestions))) {
            $output .= $this->box_start('generalbox boxaligncenter');
        }

        if ($data->gradeopendsa_activity) {
            // We are using level 3 header because the page title is a sub-heading of opendsa_activity title (MDL-30911).
            $output .= $this->heading(get_string("congratulations", "opendsa_activity"), 3);
            $output .= $this->box_start('generalbox boxaligncenter');
        }

        if ($data->notenoughtimespent !== false) {
            $output .= $this->paragraph(get_string("notenoughtimespent", "opendsa_activity", $data->notenoughtimespent), 'center');
        }

        if ($data->numberofpagesviewed !== false) {
            $output .= $this->paragraph(get_string("numberofpagesviewed", "opendsa_activity", $data->numberofpagesviewed), 'center');
        }
        if ($data->youshouldview !== false) {
            $output .= $this->paragraph(get_string("youshouldview", "opendsa_activity", $data->youshouldview), 'center');
        }
        if ($data->numberofcorrectanswers !== false) {
            $output .= $this->paragraph(get_string("numberofcorrectanswers", "opendsa_activity", $data->numberofcorrectanswers), 'center');
        }

        if ($data->displayscorewithessays !== false) {
            $output .= $this->box(get_string("displayscorewithessays", "opendsa_activity", $data->displayscorewithessays), 'center');
        } else if ($data->displayscorewithoutessays !== false) {
            $output .= $this->box(get_string("displayscorewithoutessays", "opendsa_activity", $data->displayscorewithoutessays), 'center');
        }

        if ($data->yourcurrentgradeisoutof !== false) {
            $output .= $this->paragraph(get_string("yourcurrentgradeisoutof", "opendsa_activity", $data->yourcurrentgradeisoutof), 'center');
        }
        if ($data->eolstudentoutoftimenoanswers !== false) {
            $output .= $this->paragraph(get_string("eolstudentoutoftimenoanswers", "opendsa_activity"));
        }
        if ($data->welldone !== false) {
            $output .= $this->paragraph(get_string("welldone", "opendsa_activity"));
        }

        if ($data->progresscompleted !== false) {
            $output .= $this->progress_bar($opendsa_activity, $data->progresscompleted);
        }

        if ($data->displayofgrade !== false) {
            $output .= $this->paragraph(get_string("displayofgrade", "opendsa_activity"), 'center');
        }

        $output .= $this->box_end(); // End of Lesson button to Continue.

        if ($data->reviewopendsa_activity !== false) {
            $output .= html_writer::link($data->reviewopendsa_activity, get_string('reviewopendsa_activity', 'opendsa_activity'), array('class' => 'centerpadded opendsa_activitybutton standardbutton p-r-1'));
        }
        if ($data->modattemptsnoteacher !== false) {
            $output .= $this->paragraph(get_string("modattemptsnoteacher", "opendsa_activity"), 'centerpadded');
        }

        if ($data->activitylink !== false) {
            $output .= $data->activitylink;
        }

        $url = new moodle_url('/course/view.php', array('id' => $course->id));
        $output .= html_writer::link($url, get_string('returnto', 'opendsa_activity', format_string($course->fullname, true)),
                array('class' => 'centerpadded opendsa_activitybutton standardbutton p-r-1'));

        if (has_capability('gradereport/user:view', context_course::instance($course->id))
                && $course->showgrades && $opendsa_activity->grade != 0 && !$opendsa_activity->practice) {
            $url = new moodle_url('/grade/index.php', array('id' => $course->id));
            $output .= html_writer::link($url, get_string('viewgrades', 'opendsa_activity'),
                array('class' => 'centerpadded opendsa_activitybutton standardbutton p-r-1'));
        }
        return $output;
    }
}
