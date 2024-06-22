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
 * Processes AJAX requests from IDE
 *
 * @package mod_vpl
 * @copyright 2012 Juan Carlos Rodríguez-del-Pino
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Juan Carlos Rodríguez-del-Pino <jcrodriguez@dis.ulpgc.es>
 */

define( 'AJAX_SCRIPT', true );

require(__DIR__ . '/../../../../config.php');
global $PAGE, $OUTPUT, $USER, $COURSE, $DB, $CFG;

require($CFG->dirroot . '/question/type/savpl/locallib.php');

$result = new stdClass();
$result->success = true;
$result->response = new stdClass();
$result->error = '';
try {
    if (! isloggedin()) {
        throw new Exception( get_string( 'loggedinnot' ) );
    }
    $id = required_param( 'id', PARAM_INT ); // Question id.
    $action = required_param( 'action', PARAM_ALPHANUMEXT );
    $userid = optional_param( 'userid', false, PARAM_INT );
    $subid = optional_param( 'subid', false, PARAM_INT );
    $copy = optional_param('privatecopy', false, PARAM_INT);

    // TODO use or not sesskey."require_sesskey();".
    require_login( $COURSE, false );

    $PAGE->set_url( new moodle_url( '/question/type/savpl/ajax/edit.json.php', array (
            'id' => $id,
            'action' => $action
    ) ) );
    echo $OUTPUT->header(); // Send headers.
    $rawdata = file_get_contents( "php://input" );
    $rawdatasize = strlen( $rawdata );
    if ($_SERVER['CONTENT_LENGTH'] != $rawdatasize) {
        throw new Exception( "Ajax POST error: CONTENT_LENGTH expected " . $_SERVER['CONTENT_LENGTH'] . " found $rawdatasize)" );
    }
    \mod_vpl\util\phpconfig::increase_memory_limit();
    $actiondata = json_decode( $rawdata );
    if (empty($userid)) {
        $userid = $USER->id;
    }

    $question = $DB->get_record('question_savpl', array('questionid' => $id));
    $savpl_CE = new \qtype_savpl\savpl_CE($question, $userid);
    switch ($action) {
        case 'run':
        case 'debug':
        case 'evaluate':
            $answerobjs = \qtype_savpl\answers::get_records(['questionid' => $id, 'userid' => $userid]);
            if (!$answerobj = array_pop($answerobjs)) {
                throw new \moodle_exception('answermissing', 'qtype_savpl');
            }

            $reqfile = [
                $question->templatefilename => $answerobj->get('answer')
            ];

            $execfiles = \qtype_savpl\savpl_CE::filesdecode(json_decode($question->execfiles, true));

            $filestype = $answerobj->get('filestype');
            if ($filestype == 'run') {
                $filestokeep = [];
                $execfiles = qtype_savpl_format_execution_files($execfiles, $filestokeep);
            } else if ($filestype == 'precheck') {
                $execfilesdata = $question->precheckpreference == 'diff' ? json_decode($question->precheckexecfiles) : $execfiles;
                $execfiles = qtype_savpl_format_execution_files($execfilesdata);
            }
            $answerobj->delete();
            $files = $reqfile + $execfiles;

            $savpl_CE = new \qtype_savpl\savpl_CE($question, $userid, $files);
            $result->response = $savpl_CE->execute($action, $actiondata);
            break;
        case 'retrieve':
            $result->response =$savpl_CE->retrieveresult();
            break;
        case 'cancel':
            $savpl_CE->cancelprocess();
            $result->response = true;
            break;
        case 'getjails':
            $result->response->servers = vpl_jailserver_manager::get_https_server_list( $savpl_CE->get_config()->jailservers );
            break;
        default:
            throw new Exception( 'ajax action error: ' + $action );
    }
} catch ( Exception $e ) {
    $result->success = false;
    $result->error = $e->getMessage();
}
echo json_encode( $result );
die();
