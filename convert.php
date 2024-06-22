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
 * Run the convertation script
 *
 * @package    qtype_savpl
 * @copyright  2024 Devlion.ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
global $CFG, $DB;

require_once($CFG->libdir.'/clilib.php');      // cli only functions

$fs = get_file_storage();

raise_memory_limit(MEMORY_UNLIMITED);
$offset = 0;
$limit = 100;

$contextid = \context_system::instance()->id;
$component = 'qtype_savpl';
$filepath = '/';
$filerecord = [
    'contextid' => $contextid,
    'component' => $component,
    'filearea' => '',
    'itemid' => 0,
    'filepath' => $filepath,
    'filename' => ''
];
$questions = $DB->get_records('question_savpl', null, '', '*', $offset, $limit);

while(!empty($questions)) {
    foreach ($questions as $question) {
        foreach (['execfiles', 'precheckexecfiles'] as $filearea) {
            $fs->delete_area_files($contextid, $component, $filearea, $question->questionid);
            $files = json_decode($question->$filearea, true);
            if (!empty($files)) {
                foreach ($files as $filename => $filecontent) {
                    $filerecord['filearea'] = $filearea;
                    $filerecord['itemid'] = $question->questionid;
                    $filerecord['filename'] = $filename;
                    if ($storedfile = $fs->get_file($contextid, $component, $filearea, $question->questionid, $filepath, $filename)) {
                        $storedfile->delete();
                    }

                    $fs->create_file_from_string($filerecord, $filecontent);
                }
            }

            $DB->set_field('question_savpl', $filearea, '');
        }
    }
    $offset += $limit;
    mtrace('OFFSET ' . $offset);
    unset($questions);
    $questions = $DB->get_records('question_savpl', null, '', '*', $offset, $limit);
}