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
 * Question type class for the savpl question type.
 * @package    qtype_savpl
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/locallib.php');

/**
 * The savpl type class.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_savpl extends question_type {

    /**
     * {@inheritDoc}
     * @see question_type::extra_question_fields()
     */
    public function extra_question_fields() {
        global $PAGE;

        $return = array("question_savpl",
            "templatelang",
            "templatecontext",
            "answertemplate",
            "teachercorrection",
            "validateonsave",
            "templatefilename",
            "execfiles",
            "precheckpreference",
            "precheckexecfiles",
            "gradingmethod",
            "disablerun"
        );

        if ($PAGE->has_set_url() && $PAGE->url->compare(new moodle_url('/mod/quiz/report.php'), URL_MATCH_BASE)) {

            $return = array("question_savpl",
                "templatelang",
                "templatecontext",
                "answertemplate",
                "teachercorrection",
                "validateonsave",
                "templatefilename",
                "precheckpreference",
                "gradingmethod",
                "disablerun"
            );
        }

        return $return;
    }

    /**
     * Saves question-type specific options
     *
     * This is called by {@link save_question()} to save the question-type specific data from a
     * submitted form. This method takes the form data and formats into the correct format for
     * writing to the database. It then calls the parent method to actually write the data.
     *
     * @param object $form  This holds the information from the editing form,
     *                          it is not a standard question object.
     * @return object $result->error or $result->noticeyesno or $result->notice
     */
    public function save_question_options($form) {
        global $DB;
        // Start a try block to catch any exceptions generated when we attempt to parse and
        // then add the answers and variables to the database.

        if ($form->oldparent) {
            //since we have no ability to get execfiles from formdata - we just get it from old question
            $oldfiles = \qtype_savpl\editor\vpl_editor_util::get_files($form->oldparent, 'execfiles');
            \qtype_savpl\editor\vpl_editor_util::save_files($form->id, $oldfiles, 'execfiles');
        }

        parent::save_question_options($form);
    }
}
