<?php
// This file is part of Moodle - https://moodle.org/
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
 * savpl definition class.
 * @package    qtype_savpl
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../../config.php');
require_once(__DIR__.'/locallib.php');

require_login();

/**
 * Represents a savpl.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_savpl_question extends question_graded_automatically {

    /**
     * Initial question attempt step id.
     * @var int $stepid
     */
    private $stepid;

    /**
     * {@inheritDoc}
     * @see question_definition::get_expected_data()
     */
    public function get_expected_data() {
        return array('answer' => PARAM_RAW);
    }

    /**
     * {@inheritDoc}
     * @see question_definition::get_correct_response()
     */
    public function get_correct_response() {
        return array('answer' => $this->teachercorrection);
    }

    /**
     * Wrapper to get the answer in a response object, handling unset variable.
     * @param array $response the response object
     * @return string the answer
     */
    private function get_answer(array $response) {
        return isset($response['answer']) ? $response['answer'] : '';
    }

    public function summarise_response(array $response) {
        return str_replace("\r", "", $this->get_answer($response));
    }

    public function is_complete_response(array $response) {
        return $this->get_answer($response) != $this->answertemplate;
    }

    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseanswer', SAQVPL);
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'answer');
    }

    public function apply_attempt_state(question_attempt_step $step) {
        parent::apply_attempt_state($step);
        // Store initial attempt step id, to save evaluation details as a qt var in grade_response().
        $this->stepid = $step->get_id();
    }

    public function grade_response(array $response) {
        global $DB;
        $deletesubmissions = get_config(SAQVPL, 'deletevplsubmissions') == '1';
        $result = qtype_savpl_evaluate($this->get_answer($response), $this, $deletesubmissions);

        $vplresult = $result->vplresult;
        $grade = qtype_savpl_extract_fraction($vplresult, $this->defaultmark);

        if ($grade !== null) {
            if ($this->gradingmethod == 0) {
                // All or nothing.
                $grade = floor($grade);
            }
        } else {
            if ($result->serverwassilent) {
                $details = get_string('serverwassilent', SAQVPL);
            } else {
                $details = get_string('lastservermessage', SAQVPL, $result->lastmessage);
            }
            $vplresult->evaluationerror = get_string('nogradeerror', SAQVPL, $details);
            $grade = 0;
        }

        // Store evaluation details as a qt var of initial attempt step,
        // to retrieve it from renderer (in order display the details from the renderer).
        $table = 'question_attempt_step_data';
        $params = array('attemptstepid' => $this->stepid, 'name' => '_evaldata');
        $currentrecord = $DB->get_record($table, $params);
        if ($currentrecord === false) {
            $newrecord = array_merge($params, array('value' => json_encode($vplresult)));
            $DB->insert_record($table, $newrecord, false);
        } else {
            $currentrecord->value = json_encode($vplresult);
            $DB->update_record($table, $currentrecord);
        }

        return array($grade, question_state::graded_state_for_fraction($grade));
    }
}
