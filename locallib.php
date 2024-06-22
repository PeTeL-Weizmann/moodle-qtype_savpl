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
 * Lib for savpl question type.
 * @package    qtype_savpl
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('SAQVPL', 'qtype_savpl');

use qtype_savpl\savpl_CE;
/**
 * Format and filter execution files provided by the user.
 * This method adds a suffix (_qvpl) to file names, and filters out files specified as UNUSED.
 * @param array $execfiles The files to format and filter.
 * @param array $selector If specified, only the files with name contained in this array will be considered.
 * @return array The resulting files array.
 */
function qtype_savpl_format_execution_files($execfiles, $selector=null) {
    $formattedfiles = array();
    if (!empty($execfiles)) {
        foreach ($execfiles as $name => $content) {
            if ($selector === null || in_array($name, $selector)) {
                if (substr($content, 0, 6) != 'UNUSED' && $name == 'vpl_evaluate.cases') {
                    $formattedfiles[$name.'_qvpl'] = $content;
                }
            }
        }
    }

    return $formattedfiles;
}

/**
 * Insert answer into required file and format it for submission.
 * @param object $question The question data.
 * @param string $answer The answer to the question, to include in submission.
 * @return array Files ready for submission.
 */
function qtype_savpl_get_reqfile_for_submission($question, $answer) {
    global $CFG;

    $reqfilename = $question->templatefilename;

    // Escape all backslashes, as following operation deletes them.
    $answer = preg_replace('/\\\\/', '$0$0', $answer);
    // Replace the {{ANSWER}} tag, propagating indentation.
    $answeredreqfile = preg_replace('/([ \t]*)(.*)\{\{ANSWER\}\}/i',
            '$1${2}'.implode("\n".'${1}', explode("\n", $answer)),
            $question->templatecontext);

    return array($reqfilename => $answeredreqfile);
}

/**
 * Evaluate an answer to a question by submitting it to the VPL and requesting an evaluate.
 * @param string $answer The answer to evaluate.
 * @param object $question The question data.
 * @param bool $deletesubmissions Whether user submissions should be discarded at the end of the operation.
 * @return object The evaluation result.
 */
function qtype_savpl_evaluate($answer, $question, $deletesubmissions) {
    global $USER, $CFG, $DB;
    require_once($CFG->dirroot .'/mod/vpl/vpl.class.php');
    require_once($CFG->dirroot .'/mod/vpl/forms/edit.class.php');
    require_once(__DIR__.'/classes/util/lock.php');
    $userid = $USER->id;

    $reqfile = qtype_savpl_get_reqfile_for_submission($question, $answer);
    $execfiles = savpl_CE::filesdecode(\qtype_savpl\editor\vpl_editor_util::get_files($question->id, 'execfiles'));
    $formattedexecfiles = qtype_savpl_format_execution_files($execfiles);
    $files = $reqfile + $execfiles + $formattedexecfiles;

    // Default.
    $result = new stdClass();
    $result->vplresult = new stdClass();
    $result->lastmessage = '';
    $result->serverwassilent = '';

    // Try to evaluate several times (as internal evaluation errors may occur).
    $tries = 0;
    do {
        $tries++;
        try {
            $lastmessage = '';
            $serverwassilent = true;

            // Forbid simultaneous evaluations (as the VPL won't allow multiple executions at once).
            $sem = savpl_semaphor_get($userid);
            savpl_semaphor_acquire($sem);

            $savplquestion = $DB->get_record('question_savpl', array('questionid' => $question->id));
            $savpl_CE = new savpl_CE($savplquestion, $userid, $files);
            $coninfo = $savpl_CE->execute("evaluate");

            $wsprotocol = $coninfo->wsProtocol;
            if ( $wsprotocol == 'always_use_wss' ||
                ($wsprotocol == 'depends_on_https' && stripos($_SERVER['SERVER_PROTOCOL'], 'https') !== false) ) {
                $port = $coninfo->securePort;
                $protocol = 'https://';
            } else {
                $port = $coninfo->port;
                $protocol = 'http://';
            }

            // Set up a curl execution to listen to VPL execution server,
            // so we can stop as soon as we get the 'retrieve:' message (meaning that evaluation is complete).
            $ch = curl_init($protocol . $coninfo->server . ':' . $port . '/' . $coninfo->monitorPath);

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Connection: Upgrade",
                "Upgrade: websocket",
                "Host:".$coninfo->server,
                "Sec-WebSocket-Key: ".base64_encode(uniqid()),
                "Sec-WebSocket-Version: 13"
            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($chdummy, $data) use (&$lastmessage, &$serverwassilent) {
                $lastmessage = $data;
                $serverwassilent = false;
                if (strpos($data, 'retrieve:') !== false) {
                    // Interrupt curl exec.
                    return -1;
                }
                return strlen($data);
            });

            curl_exec($ch);
            curl_close($ch);

            $result = new stdClass();
            $result->vplresult = $savpl_CE->retrieveresult();
            $result->lastmessage = $lastmessage;
            $result->serverwassilent = $serverwassilent;
            $retry = false;

        } catch (Exception $e) {
            // There was an error during evaluation - retry.
            $result = new stdClass();
            $result->vplresult = new stdClass();
            $result->lastmessage = $lastmessage;
            $result->serverwassilent = $serverwassilent;
            $retry = true;
        }

        savpl_semaphor_release($sem);

        // Retry up to 10 times.
    } while ($retry && $tries < 10);

    return $result;
}

/**
 * Compute the fraction (grade between 0 and 1) from the result of an evaluation.
 * @param object $result The evaluation result.
 * @param int $templatevpl The ID of the VPL this evaluation has been executed on.
 * @return float|null The fraction if any, or null if there was no grade.
 */
function qtype_savpl_extract_fraction($result, $maxgrade) {
    if ($result->grade) {
        global $CFG;
        $formattedgrade = floatval(preg_replace('/.*: (.*) *\/.*/', '$1', $result->grade));
        $fraction = $formattedgrade / $maxgrade;

        return $fraction;
    } else {
        return null;
    }
}
