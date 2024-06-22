<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Perform a submission on a VPL during a quiz attempt.
 * This is an ajax call for Run and Pre-check, actual evaluation (Check) is done only on server side.
 *
 * @package qtype_savpl
 * @copyright 2022 Astor Bizard
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define( 'AJAX_SCRIPT', true );

require(__DIR__ . '/../../../../config.php');

global $USER, $DB, $COURSE;

$outcome = new stdClass();
$outcome->success = true;
$outcome->response = new stdClass();
$outcome->error = '';
try {
    require_once(__DIR__ . '/../../../../mod/vpl/vpl.class.php');
    require_once(__DIR__ . '/../../../../mod/vpl/forms/edit.class.php');
    require_once(__DIR__ . '/../locallib.php');
    if (! isloggedin()) {
        throw new Exception( get_string( 'loggedinnot' ) );
    }

    $id = required_param( 'qid', PARAM_INT );
    $userid = $USER->id;
    $answer = required_param( 'answer', PARAM_RAW );
    $filestype = required_param( 'filestype', PARAM_RAW );
    require_login( $COURSE, false );

    $question = $DB->get_record('question_savpl', array('questionid' => $id));
    $filearray = qtype_savpl_get_reqfile_for_submission($question, $answer);
    $data = (object) [
        'questionid' => $id,
        'userid' => $userid,
        'answer' => array_pop($filearray),
        'filestype' => $filestype
    ];

    $answers = new \qtype_savpl\answers(0, $data);
    $outcome->response = !empty(($answers->create())->get('id'));

} catch ( Exception $e ) {
    $outcome->success = false;
    $outcome->error = $e->getMessage();
}
echo json_encode( $outcome );
die();
