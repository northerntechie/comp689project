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
 * Numerical
 *
 * @package mod_opendsa_activity
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

/** Numerical question type */
define("OPENDSA_ACTIVITY_PAGE_NUMERICAL",     "8");

use mod_opendsa_activity\local\numeric\helper;

class opendsa_activity_page_type_numerical extends opendsa_activity_page {

    protected $type = opendsa_activity_page::TYPE_QUESTION;
    protected $typeidstring = 'numerical';
    protected $typeid = OPENDSA_ACTIVITY_PAGE_NUMERICAL;
    protected $string = null;

    public function get_typeid() {
        return $this->typeid;
    }
    public function get_typestring() {
        if ($this->string===null) {
            $this->string = get_string($this->typeidstring, 'opendsa_activity');
        }
        return $this->string;
    }
    public function get_idstring() {
        return $this->typeidstring;
    }
    public function display($renderer, $attempt) {
        global $USER, $PAGE;
        $mform = new opendsa_activity_display_answer_form_numerical(new moodle_url('/mod/opendsa_activity/continue.php'),
            array('contents' => $this->get_contents(), 'opendsa_activity_id' => $this->opendsa_activity->id));
        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
        if (isset($USER->modattempts[$this->opendsa_activity->id])) {
            $data->answer = s($attempt->useranswer);
        }
        $mform->set_data($data);

        // Trigger an event question viewed.
        $eventparams = array(
            'context' => context_module::instance($PAGE->cm->id),
            'objectid' => $this->properties->id,
            'other' => array(
                    'pagetype' => $this->get_typestring()
                )
            );

        $event = \mod_opendsa_activity\event\question_viewed::create($eventparams);
        $event->trigger();
        return $mform->display();
    }

    /**
     * Creates answers for this page type.
     *
     * @param  object $properties The answer properties.
     */
    public function create_answers($properties) {
        if (isset($properties->enableotheranswers) && $properties->enableotheranswers) {
            $properties->response_editor = array_values($properties->response_editor);
            $properties->jumpto = array_values($properties->jumpto);
            $properties->score = array_values($properties->score);
            $wrongresponse = end($properties->response_editor);
            $wrongkey = key($properties->response_editor);
            $properties->answer_editor[$wrongkey] = OPENDSA_ACTIVITY_OTHER_ANSWERS;
        }
        parent::create_answers($properties);
    }

    /**
     * Update the answers for this page type.
     *
     * @param  object $properties The answer properties.
     * @param  context $context The context for this module.
     * @param  int $maxbytes The maximum bytes for any uploades.
     */
    public function update($properties, $context = null, $maxbytes = null) {
        if ($properties->enableotheranswers) {
            $properties->response_editor = array_values($properties->response_editor);
            $properties->jumpto = array_values($properties->jumpto);
            $properties->score = array_values($properties->score);
            $wrongresponse = end($properties->response_editor);
            $wrongkey = key($properties->response_editor);
            $properties->answer_editor[$wrongkey] = OPENDSA_ACTIVITY_OTHER_ANSWERS;
        }
        parent::update($properties, $context, $maxbytes);
    }

    public function check_answer() {
        $result = parent::check_answer();

        $mform = new opendsa_activity_display_answer_form_numerical(new moodle_url('/mod/opendsa_activity/continue.php'),
            array('contents' => $this->get_contents()));
        $data = $mform->get_data();
        require_sesskey();

        $formattextdefoptions = new stdClass();
        $formattextdefoptions->noclean = true;
        $formattextdefoptions->para = false;

        // set defaults
        $result->response = '';
        $result->newpageid = 0;

        if (!isset($data->answer)) {
            $result->noanswer = true;
            return $result;
        } else {
            $result->useranswer = $data->answer;
        }
        $result->studentanswer = $result->userresponse = $result->useranswer;
        $answers = $this->get_answers();
        foreach ($answers as $answer) {
            $answer = parent::rewrite_answers_urls($answer);
            if (strpos($answer->answer, ':')) {
                // there's a pairs of values
                list($min, $max) = explode(':', $answer->answer);
                $minimum = (float) $min;
                $maximum = (float) $max;
            } else {
                // there's only one value
                $minimum = (float) $answer->answer;
                $maximum = $minimum;
            }
            if (($result->useranswer >= $minimum) && ($result->useranswer <= $maximum)) {
                $result->newpageid = $answer->jumpto;
                $result->response = format_text($answer->response, $answer->responseformat, $formattextdefoptions);
                if ($this->opendsa_activity->jumpto_is_correct($this->properties->id, $result->newpageid)) {
                    $result->correctanswer = true;
                }
                if ($this->opendsa_activity->custom) {
                    if ($answer->score > 0) {
                        $result->correctanswer = true;
                    } else {
                        $result->correctanswer = false;
                    }
                }
                $result->answerid = $answer->id;
                return $result;
            }
        }
        // We could check here to see if we have a wrong answer jump to use.
        if ($result->answerid == 0) {
            // Use the all other answers jump details if it is set up.
            $lastanswer = end($answers);
            // Double check that this is the @#wronganswer#@ answer.
            if (strpos($lastanswer->answer, OPENDSA_ACTIVITY_OTHER_ANSWERS) !== false) {
                $otheranswers = end($answers);
                $result->newpageid = $otheranswers->jumpto;
                $result->response = format_text($otheranswers->response, $otheranswers->responseformat, $formattextdefoptions);
                // Does this also need to do the jumpto_is_correct?
                if ($this->opendsa_activity->custom) {
                    $result->correctanswer = ($otheranswers->score > 0);
                }
                $result->answerid = $otheranswers->id;
            }
        }
        return $result;
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass;
        $options->noclean = true;
        $options->para = false;
        $i = 1;
        foreach ($answers as $answer) {
            $answer = parent::rewrite_answers_urls($answer, false);
            $cells = array();
            if ($this->opendsa_activity->custom && $answer->score > 0) {
                // if the score is > 0, then it is correct
                $cells[] = '<label class="correct">' . get_string('answer', 'opendsa_activity') . ' ' . $i . '</label>:';
            } else if ($this->opendsa_activity->custom) {
                $cells[] = '<label>' . get_string('answer', 'opendsa_activity') . ' ' . $i . '</label>:';
            } else if ($this->opendsa_activity->jumpto_is_correct($this->properties->id, $answer->jumpto)) {
                // underline correct answers
                $cells[] = '<span class="correct">' . get_string('answer', 'opendsa_activity') . ' ' . $i . '</span>:' . "\n";
            } else {
                $cells[] = '<label class="correct">' . get_string('answer', 'opendsa_activity') . ' ' . $i . '</label>:';
            }
            $formattedanswer = helper::opendsa_activity_format_numeric_value($answer->answer);
            $cells[] = format_text($formattedanswer, $answer->answerformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = '<label>' . get_string('response', 'opendsa_activity') . ' ' . $i . '</label>:';
            $cells[] = format_text($answer->response, $answer->responseformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = '<label>' . get_string('score', 'opendsa_activity') . '</label>:';
            $cells[] = $answer->score;
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = '<label>' . get_string('jump', 'opendsa_activity') . '</label>:';
            $cells[] = $this->get_jump_name($answer->jumpto);
            $table->data[] = new html_table_row($cells);
            if ($i === 1){
                $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
            }
            $i++;
        }
        return $table;
    }
    public function stats(array &$pagestats, $tries) {
        if(count($tries) > $this->opendsa_activity->maxattempts) { // if there are more tries than the max that is allowed, grab the last "legal" attempt
            $temp = $tries[$this->opendsa_activity->maxattempts - 1];
        } else {
            // else, user attempted the question less than the max, so grab the last one
            $temp = end($tries);
        }
        if (isset($pagestats[$temp->pageid][$temp->useranswer])) {
            $pagestats[$temp->pageid][$temp->useranswer]++;
        } else {
            $pagestats[$temp->pageid][$temp->useranswer] = 1;
        }
        if (isset($pagestats[$temp->pageid]["total"])) {
            $pagestats[$temp->pageid]["total"]++;
        } else {
            $pagestats[$temp->pageid]["total"] = 1;
        }
        return true;
    }

    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  //I'll use it widely in this page
        foreach ($answers as $answer) {
            if ($useranswer == null && $i == 0) {
                // I have the $i == 0 because it is easier to blast through it all at once.
                if (isset($pagestats[$this->properties->id])) {
                    $stats = $pagestats[$this->properties->id];
                    $total = $stats["total"];
                    unset($stats["total"]);
                    foreach ($stats as $valentered => $ntimes) {
                        $data = '<input class="form-control" type="text" size="50" ' .
                                'disabled="disabled" readonly="readonly" value="'.
                                s(format_float($valentered, strlen($valentered), true, true)).'" />';
                        $percent = $ntimes / $total * 100;
                        $percent = round($percent, 2);
                        $percent .= "% ".get_string("enteredthis", "opendsa_activity");
                        $answerdata->answers[] = array($data, $percent);
                    }
                } else {
                    $answerdata->answers[] = array(get_string("nooneansweredthisquestion", "opendsa_activity"), " ");
                }
                $i++;
            } else if ($useranswer != null && ($answer->id == $useranswer->answerid || ($answer == end($answers) &&
                    empty($answerdata->answers)))) {
                // Get in here when the user answered or for the last answer.
                $data = '<input class="form-control" type="text" size="50" ' .
                        'disabled="disabled" readonly="readonly" value="'.
                        s(format_float($useranswer->useranswer, strlen($useranswer->useranswer), true, true)).'">';
                if (isset($pagestats[$this->properties->id][$useranswer->useranswer])) {
                    $percent = $pagestats[$this->properties->id][$useranswer->useranswer] / $pagestats[$this->properties->id]["total"] * 100;
                    $percent = round($percent, 2);
                    $percent .= "% ".get_string("enteredthis", "opendsa_activity");
                } else {
                    $percent = get_string("nooneenteredthis", "opendsa_activity");
                }
                $answerdata->answers[] = array($data, $percent);

                if ($answer->id == $useranswer->answerid) {
                    if ($answer->response == null) {
                        if ($useranswer->correct) {
                            $answerdata->response = get_string("thatsthecorrectanswer", "opendsa_activity");
                        } else {
                            $answerdata->response = get_string("thatsthewronganswer", "opendsa_activity");
                        }
                    } else {
                        $answerdata->response = $answer->response;
                    }
                    if ($this->opendsa_activity->custom) {
                        $answerdata->score = get_string("pointsearned", "opendsa_activity").": ".$answer->score;
                    } elseif ($useranswer->correct) {
                        $answerdata->score = get_string("receivedcredit", "opendsa_activity");
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "opendsa_activity");
                    }
                } else {
                    $answerdata->response = get_string("thatsthewronganswer", "opendsa_activity");
                    if ($this->opendsa_activity->custom) {
                        $answerdata->score = get_string("pointsearned", "opendsa_activity").": 0";
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "opendsa_activity");
                    }
                }
            }
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }

    /**
     * Make updates to the form data if required. In this case to put the all other answer data into the write section of the form.
     *
     * @param stdClass $data The form data to update.
     * @return stdClass The updated fom data.
     */
    public function update_form_data(stdClass $data) : stdClass {
        $answercount = count($this->get_answers());

        // If no answers provided, then we don't need to check anything.
        if (!$answercount) {
            return $data;
        }

        // Check for other answer entry.
        $lastanswer = $data->{'answer_editor[' . ($answercount - 1) . ']'};
        if (strpos($lastanswer, OPENDSA_ACTIVITY_OTHER_ANSWERS) !== false) {
            $data->{'answer_editor[' . ($this->opendsa_activity->maxanswers + 1) . ']'} =
                    $data->{'answer_editor[' . ($answercount - 1) . ']'};
            $data->{'response_editor[' . ($this->opendsa_activity->maxanswers + 1) . ']'} =
                    $data->{'response_editor[' . ($answercount - 1) . ']'};
            $data->{'jumpto[' . ($this->opendsa_activity->maxanswers + 1) . ']'} = $data->{'jumpto[' . ($answercount - 1) . ']'};
            $data->{'score[' . ($this->opendsa_activity->maxanswers + 1) . ']'} = $data->{'score[' . ($answercount - 1) . ']'};
            $data->enableotheranswers = true;

            // Unset the old values.
            unset($data->{'answer_editor[' . ($answercount - 1) . ']'});
            unset($data->{'response_editor[' . ($answercount - 1) . ']'});
            unset($data->{'jumpto['. ($answercount - 1) . ']'});
            unset($data->{'score[' . ($answercount - 1) . ']'});
        }

        return $data;
    }
}

class opendsa_activity_add_page_form_numerical extends opendsa_activity_add_page_form_base {

    public $qtype = 'numerical';
    public $qtypestring = 'numerical';
    protected $answerformat = '';
    protected $responseformat = OPENDSA_ACTIVITY_ANSWER_HTML;

    public function custom_definition() {
        $answercount = $this->_customdata['opendsa_activity']->maxanswers;
        for ($i = 0; $i < $answercount; $i++) {
            $this->_form->addElement('header', 'answertitle'.$i, get_string('answer').' '.($i+1));
            $this->add_answer($i, null, ($i < 1), '', [
                    'identifier' => 'numericanswer',
                    'component' => 'mod_opendsa_activity'
            ]);
            $this->add_response($i);
            $this->add_jumpto($i, null, ($i == 0 ? OPENDSA_ACTIVITY_NEXTPAGE : OPENDSA_ACTIVITY_THISPAGE));
            $this->add_score($i, null, ($i===0)?1:0);
        }
        // Wrong answer jump.
        $this->_form->addElement('header', 'wronganswer', get_string('allotheranswers', 'opendsa_activity'));
        $newcount = $answercount + 1;
        $this->_form->addElement('advcheckbox', 'enableotheranswers', get_string('enabled', 'opendsa_activity'));
        $this->add_response($newcount);
        $this->add_jumpto($newcount, get_string('allotheranswersjump', 'opendsa_activity'), OPENDSA_ACTIVITY_NEXTPAGE);
        $this->add_score($newcount, get_string('allotheranswersscore', 'opendsa_activity'), 0);
    }

    /**
     * We call get data when storing the data into the db. Override to format the floats properly
     *
     * @return object|void
     */
    public function get_data() : ?stdClass {
        $data = parent::get_data();

        if (!empty($data->answer_editor)) {
            foreach ($data->answer_editor as $key => $answer) {
                $data->answer_editor[$key] = helper::opendsa_activity_unformat_numeric_value($answer);
            }
        }

        return $data;
    }

    /**
     * Return submitted data if properly submitted or returns NULL if validation fails or
     * if there is no submitted data with formatted numbers
     *
     * @return object submitted data; NULL if not valid or not submitted or cancelled
     */
    public function get_submitted_data() : ?stdClass {
        $data = parent::get_submitted_data();

        if (!empty($data->answer_editor)) {
            foreach ($data->answer_editor as $key => $answer) {
                $data->answer_editor[$key] = helper::opendsa_activity_unformat_numeric_value($answer);
            }
        }

        return $data;
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form) after formatting numbers
     *
     *
     * @param stdClass|array $defaults object or array of default values
     */
    public function set_data($defaults) {
        if (is_object($defaults)) {
            $defaults = (array) $defaults;
        }

        $editor = 'answer_editor';
        foreach ($defaults as $key => $answer) {
            if (substr($key, 0, strlen($editor)) == $editor) {
                $defaults[$key] = helper::opendsa_activity_format_numeric_value($answer);
            }
        }

        parent::set_data($defaults);
    }
}

class opendsa_activity_display_answer_form_numerical extends moodleform {

    public function definition() {
        global $USER, $OUTPUT;
        $mform = $this->_form;
        $contents = $this->_customdata['contents'];

        // Disable shortforms.
        $mform->setDisableShortforms();

        $mform->addElement('header', 'pageheader');

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        $hasattempt = false;
        $attrs = array('size'=>'50', 'maxlength'=>'200');
        if (isset($this->_customdata['opendsa_activity_id'])) {
            $opendsa_activity_id = $this->_customdata['opendsa_activity_id'];
            if (isset($USER->modattempts[$opendsa_activity_id]->useranswer)) {
                $attrs['readonly'] = 'readonly';
                $hasattempt = true;
            }
        }
        $options = new stdClass;
        $options->para = false;
        $options->noclean = true;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        $mform->addElement('float', 'answer', get_string('youranswer', 'opendsa_activity'), $attrs);

        if ($hasattempt) {
            $this->add_action_buttons(null, get_string("nextpage", "opendsa_activity"));
        } else {
            $this->add_action_buttons(null, get_string("submit", "opendsa_activity"));
        }
    }
}
