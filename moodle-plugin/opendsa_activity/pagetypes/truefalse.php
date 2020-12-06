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
 * True/false
 *
 * @package mod_opendsa_activity
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

/** True/False question type */
define("OPENDSA_ACTIVITY_PAGE_TRUEFALSE",     "2");

class opendsa_activity_page_type_truefalse extends opendsa_activity_page {

    protected $type = opendsa_activity_page::TYPE_QUESTION;
    protected $typeidstring = 'truefalse';
    protected $typeid = OPENDSA_ACTIVITY_PAGE_TRUEFALSE;
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
        global $USER, $CFG, $PAGE;
        $answers = $this->get_answers();
        foreach ($answers as $key => $answer) {
            $answers[$key] = parent::rewrite_answers_urls($answer);
        }
        shuffle($answers);

        $params = array('answers'=>$answers, 'opendsa_activity_id'=>$this->opendsa_activity->id, 'contents'=>$this->get_contents(), 'attempt'=>$attempt);
        $mform = new opendsa_activity_display_answer_form_truefalse($CFG->wwwroot.'/mod/opendsa_activity/continue.php', $params);
        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
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
    public function check_answer() {
        global $DB, $CFG;
        $formattextdefoptions = new stdClass();
        $formattextdefoptions->noclean = true;
        $formattextdefoptions->para = false;

        $answers = $this->get_answers();
        shuffle($answers);
        $params = array('answers'=>$answers, 'opendsa_activity_id'=>$this->opendsa_activity->id, 'contents'=>$this->get_contents());
        $mform = new opendsa_activity_display_answer_form_truefalse($CFG->wwwroot.'/mod/opendsa_activity/continue.php', $params);
        $data = $mform->get_data();
        require_sesskey();

        $result = parent::check_answer();

        if (empty($data->answerid)) {
            $result->noanswer = true;
            return $result;
        }
        $result->answerid = $data->answerid;
        $answer = $DB->get_record("opendsa_activity_answers", array("id" => $result->answerid), '*', MUST_EXIST);
        $answer = parent::rewrite_answers_urls($answer);
        if ($this->opendsa_activity->jumpto_is_correct($this->properties->id, $answer->jumpto)) {
            $result->correctanswer = true;
        }
        if ($this->opendsa_activity->custom) {
            if ($answer->score > 0) {
                $result->correctanswer = true;
            } else {
                $result->correctanswer = false;
            }
        }
        $result->newpageid = $answer->jumpto;
        $result->response  = format_text($answer->response, $answer->responseformat, $formattextdefoptions);
        $result->studentanswer = $result->userresponse = $answer->answer;
        return $result;
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass();
        $options->noclean = true;
        $options->para = false;
        $i = 1;
        foreach ($answers as $answer) {
            $answer = parent::rewrite_answers_urls($answer);
            $cells = array();
            if ($this->opendsa_activity->custom && $answer->score > 0) {
                // if the score is > 0, then it is correct
                $cells[] = '<label class="correct">' . get_string('answer', 'opendsa_activity') . " {$i}</label>: \n";
            } else if ($this->opendsa_activity->custom) {
                $cells[] = '<label>' . get_string('answer', 'opendsa_activity') . " {$i}</label>: \n";
            } else if ($this->opendsa_activity->jumpto_is_correct($this->properties->id, $answer->jumpto)) {
                // underline correct answers
                $cells[] = '<span class="correct">' . get_string('answer', 'opendsa_activity') . " {$i}</span>: \n";
            } else {
                $cells[] = '<label class="correct">' . get_string('answer', 'opendsa_activity') . " {$i}</label>: \n";
            }
            $cells[] = format_text($answer->answer, $answer->answerformat, $options);
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

    /**
     * Updates the page and its answers
     *
     * @global moodle_database $DB
     * @global moodle_page $PAGE
     * @param stdClass $properties
     * @return bool
     */
    public function update($properties, $context = null, $maxbytes = null) {
        global $DB, $PAGE;
        $answers  = $this->get_answers();
        $properties->id = $this->properties->id;
        $properties->opendsa_activity_id = $this->opendsa_activity->id;
        $properties->timemodified = time();
        $properties = file_postupdate_standard_editor($properties, 'contents', array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes), context_module::instance($PAGE->cm->id), 'mod_opendsa_activity', 'page_contents', $properties->id);
        $DB->update_record("opendsa_activity_pages", $properties);

        // Trigger an event: page updated.
        \mod_opendsa_activity\event\page_updated::create_from_opendsa_activity_page($this, $context)->trigger();

        // need to reset offset for correct and wrong responses
        $this->opendsa_activity->maxanswers = 2;
        for ($i = 0; $i < $this->opendsa_activity->maxanswers; $i++) {
            if (!array_key_exists($i, $this->answers)) {
                $this->answers[$i] = new stdClass;
                $this->answers[$i]->opendsa_activity_id = $this->opendsa_activity->id;
                $this->answers[$i]->pageid = $this->id;
                $this->answers[$i]->timecreated = $this->timecreated;
            }

            if (!empty($properties->answer_editor[$i]) && is_array($properties->answer_editor[$i])) {
                $this->answers[$i]->answer = $properties->answer_editor[$i]['text'];
                $this->answers[$i]->answerformat = $properties->answer_editor[$i]['format'];
            }

            if (!empty($properties->response_editor[$i]) && is_array($properties->response_editor[$i])) {
                $this->answers[$i]->response = $properties->response_editor[$i]['text'];
                $this->answers[$i]->responseformat = $properties->response_editor[$i]['format'];
            }

            // we don't need to check for isset here because properties called it's own isset method.
            if ($this->answers[$i]->answer != '') {
                if (isset($properties->jumpto[$i])) {
                    $this->answers[$i]->jumpto = $properties->jumpto[$i];
                }
                if ($this->opendsa_activity->custom && isset($properties->score[$i])) {
                    $this->answers[$i]->score = $properties->score[$i];
                }
                if (!isset($this->answers[$i]->id)) {
                    $this->answers[$i]->id =  $DB->insert_record("opendsa_activity_answers", $this->answers[$i]);
                } else {
                    $DB->update_record("opendsa_activity_answers", $this->answers[$i]->properties());
                }
                // Save files in answers and responses.
                $this->save_answers_files($context, $maxbytes, $this->answers[$i],
                        $properties->answer_editor[$i], $properties->response_editor[$i]);
            } else if (isset($this->answers[$i]->id)) {
                $DB->delete_records('opendsa_activity_answers', array('id'=>$this->answers[$i]->id));
                unset($this->answers[$i]);
            }
        }
        return true;
    }

    public function stats(array &$pagestats, $tries) {
        if(count($tries) > $this->opendsa_activity->maxattempts) { // if there are more tries than the max that is allowed, grab the last "legal" attempt
            $temp = $tries[$this->opendsa_activity->maxattempts - 1];
        } else {
            // else, user attempted the question less than the max, so grab the last one
            $temp = end($tries);
        }
        if ($this->properties->qoption) {
            $userresponse = explode(",", $temp->useranswer);
            foreach ($userresponse as $response) {
                if (isset($pagestats[$temp->pageid][$response])) {
                    $pagestats[$temp->pageid][$response]++;
                } else {
                    $pagestats[$temp->pageid][$response] = 1;
                }
            }
        } else {
            if (isset($pagestats[$temp->pageid][$temp->answerid])) {
                $pagestats[$temp->pageid][$temp->answerid]++;
            } else {
                $pagestats[$temp->pageid][$temp->answerid] = 1;
            }
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
        $formattextdefoptions = new stdClass(); //I'll use it widely in this page
        $formattextdefoptions->para = false;
        $formattextdefoptions->noclean = true;
        $formattextdefoptions->context = $answerpage->context;

        foreach ($answers as $answer) {
            $answer = parent::rewrite_answers_urls($answer);
            if ($this->properties->qoption) {
                if ($useranswer == null) {
                    $userresponse = array();
                } else {
                    $userresponse = explode(",", $useranswer->useranswer);
                }
                if (in_array($answer->id, $userresponse)) {
                    // make checked
                    $data = "<input  readonly=\"readonly\" disabled=\"disabled\" name=\"answer[$i]\" checked=\"checked\" type=\"checkbox\" value=\"1\" />";
                    if (!isset($answerdata->response)) {
                        if ($answer->response == null) {
                            if ($useranswer->correct) {
                                $answerdata->response = get_string("thatsthecorrectanswer", "opendsa_activity");
                            } else {
                                $answerdata->response = get_string("thatsthewronganswer", "opendsa_activity");
                            }
                        } else {
                            $answerdata->response = format_text($answer->response, $answer->responseformat, $formattextdefoptions);
                        }
                    }
                    if (!isset($answerdata->score)) {
                        if ($this->opendsa_activity->custom) {
                            $answerdata->score = get_string("pointsearned", "opendsa_activity").": ".$answer->score;
                        } elseif ($useranswer->correct) {
                            $answerdata->score = get_string("receivedcredit", "opendsa_activity");
                        } else {
                            $answerdata->score = get_string("didnotreceivecredit", "opendsa_activity");
                        }
                    }
                } else {
                    // unchecked
                    $data = "<input type=\"checkbox\" readonly=\"readonly\" name=\"answer[$i]\" value=\"0\" disabled=\"disabled\" />";
                }
                if (($answer->score > 0 && $this->opendsa_activity->custom) || ($this->opendsa_activity->jumpto_is_correct($this->properties->id, $answer->jumpto) && !$this->opendsa_activity->custom)) {
                    $data .= "<div class=highlight>".format_text($answer->answer, $answer->answerformat, $formattextdefoptions)."</div>";
                } else {
                    $data .= format_text($answer->answer, $answer->answerformat, $formattextdefoptions);
                }
            } else {
                if ($useranswer != null and $answer->id == $useranswer->answerid) {
                    // make checked
                    $data = "<input  readonly=\"readonly\" disabled=\"disabled\" name=\"answer[$i]\" checked=\"checked\" type=\"checkbox\" value=\"1\" />";
                    if ($answer->response == null) {
                        if ($useranswer->correct) {
                            $answerdata->response = get_string("thatsthecorrectanswer", "opendsa_activity");
                        } else {
                            $answerdata->response = get_string("thatsthewronganswer", "opendsa_activity");
                        }
                    } else {
                        $answerdata->response = format_text($answer->response, $answer->responseformat, $formattextdefoptions);
                    }
                    if ($this->opendsa_activity->custom) {
                        $answerdata->score = get_string("pointsearned", "opendsa_activity").": ".$answer->score;
                    } elseif ($useranswer->correct) {
                        $answerdata->score = get_string("receivedcredit", "opendsa_activity");
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "opendsa_activity");
                    }
                } else {
                    // unchecked
                    $data = "<input type=\"checkbox\" readonly=\"readonly\" name=\"answer[$i]\" value=\"0\" disabled=\"disabled\" />";
                }
                if (($answer->score > 0 && $this->opendsa_activity->custom) || ($this->opendsa_activity->jumpto_is_correct($this->properties->id, $answer->jumpto) && !$this->opendsa_activity->custom)) {
                    $data .= "<div class=\"highlight\">".format_text($answer->answer, $answer->answerformat, $formattextdefoptions)."</div>";
                } else {
                    $data .= format_text($answer->answer, $answer->answerformat, $formattextdefoptions);
                }
            }
            if (isset($pagestats[$this->properties->id][$answer->id])) {
                $percent = $pagestats[$this->properties->id][$answer->id] / $pagestats[$this->properties->id]["total"] * 100;
                $percent = round($percent, 2);
                $percent .= "% ".get_string("checkedthisone", "opendsa_activity");
            } else {
                $percent = get_string("noonecheckedthis", "opendsa_activity");
            }

            $answerdata->answers[] = array($data, $percent);
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }
}

class opendsa_activity_add_page_form_truefalse extends opendsa_activity_add_page_form_base {

    public $qtype = 'truefalse';
    public $qtypestring = 'truefalse';
    protected $answerformat = OPENDSA_ACTIVITY_ANSWER_HTML;
    protected $responseformat = OPENDSA_ACTIVITY_ANSWER_HTML;

    public function custom_definition() {
        $this->_form->addElement('header', 'answertitle0', get_string('correctresponse', 'opendsa_activity'));
        $this->add_answer(0, null, true, $this->get_answer_format());
        $this->add_response(0);
        $this->add_jumpto(0, get_string('correctanswerjump', 'opendsa_activity'), OPENDSA_ACTIVITY_NEXTPAGE);
        $this->add_score(0, get_string('correctanswerscore', 'opendsa_activity'), 1);

        $this->_form->addElement('header', 'answertitle1', get_string('wrongresponse', 'opendsa_activity'));
        $this->add_answer(1, null, true, $this->get_answer_format());
        $this->add_response(1);
        $this->add_jumpto(1, get_string('wronganswerjump', 'opendsa_activity'), OPENDSA_ACTIVITY_THISPAGE);
        $this->add_score(1, get_string('wronganswerscore', 'opendsa_activity'), 0);
    }
}

class opendsa_activity_display_answer_form_truefalse extends moodleform {

    public function definition() {
        global $USER, $OUTPUT;
        $mform = $this->_form;
        $answers = $this->_customdata['answers'];
        $opendsa_activity_id = $this->_customdata['opendsa_activity_id'];
        $contents = $this->_customdata['contents'];
        if (array_key_exists('attempt', $this->_customdata)) {
            $attempt = $this->_customdata['attempt'];
        } else {
            $attempt = new stdClass();
            $attempt->answerid = null;
        }

        // Disable shortforms.
        $mform->setDisableShortforms();

        $mform->addElement('header', 'pageheader');

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        $hasattempt = false;
        $disabled = '';
        if (isset($USER->modattempts[$opendsa_activity_id]) && !empty($USER->modattempts[$opendsa_activity_id])) {
            $hasattempt = true;
            $disabled = array('disabled' => 'disabled');
        }

        $options = new stdClass();
        $options->para = false;
        $options->noclean = true;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        $i = 0;
        $radiobuttons = array();
        foreach ($answers as $answer) {
            $ansid = 'answerid';
            if ($hasattempt) {
                $ansid = 'answer_id';
            }

            $answer = opendsa_activity_page_type_truefalse::rewrite_answers_urls($answer);
            $radiobuttons[] = $mform->createElement('radio', $ansid, null,
                format_text($answer->answer, $answer->answerformat, $options), $answer->id, $disabled);

            $mform->setType($ansid, PARAM_INT);
            if ($hasattempt && $answer->id == $USER->modattempts[$opendsa_activity_id]->answerid) {
                $mform->setDefault($ansid, $attempt->answerid);
                $mform->addElement('hidden', 'answerid', $answer->id);
                $mform->setType('answerid', PARAM_INT);
            }
            $i++;
        }

        $radiogroup = $mform->addGroup($radiobuttons, $ansid, '', array(''), false);
        $radiogroup->setAttributes(array('class' => 'answeroptiongroup'));

        if ($hasattempt) {
            $this->add_action_buttons(null, get_string("nextpage", "opendsa_activity"));
        } else {
            $this->add_action_buttons(null, get_string("submit", "opendsa_activity"));
        }

    }

}
