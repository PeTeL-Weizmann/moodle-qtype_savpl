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
 * Backup functions for Moodle 2.
 * @package    qtype_savpl
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides information to backup VPL Questions.
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_savpl_plugin extends backup_qtype_extrafields_plugin {
    protected function define_question_plugin_structure() {
        $qtypeobj = question_bank::get_qtype($this->pluginname);

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', $qtypeobj->name());

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // This qtype uses standard question_answers, add them here
        // to the tree before any other information that will use them.
        $this->add_question_question_answers($pluginwrapper);
        $answers = $pluginwrapper->get_child('answers');
        $answer = $answers->get_child('answer');

        // Extra question fields.
        $extraquestionfields = $qtypeobj->extra_question_fields();
        if (!empty($extraquestionfields)) {
            $tablename = array_shift($extraquestionfields);
            $child = new backup_nested_element($qtypeobj->name(), array('id'), $extraquestionfields);
            $pluginwrapper->add_child($child);
            $child->set_source_table($tablename, array($qtypeobj->questionid_column_name() => backup::VAR_PARENTID));
        }

        // Extra answer fields.
        $extraanswerfields = $qtypeobj->extra_answer_fields();
        if (!empty($extraanswerfields)) {
            $tablename = array_shift($extraanswerfields);
            $child = new backup_nested_element('extraanswerdata', array('id'), $extraanswerfields);
            $answer->add_child($child);
            $child->set_source_table($tablename, array('answerid' => backup::VAR_PARENTID));
        }

        // Don't need to annotate ids nor files.

        $context = \context_system::instance();
        $pluginwrapper->annotate_files('qtype_savpl', 'execfiles', backup::VAR_PARENTID, $context->id);
        $pluginwrapper->annotate_files('qtype_savpl', 'precheckexecfiles', backup::VAR_PARENTID, $context->id);

        return $plugin;
    }
}
