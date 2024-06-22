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
 * Backup functions from Moodle 1.
 * @package    qtype_savpl
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * VPL Question type conversion handler.
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle1_qtype_savpl_handler extends moodle1_qtype_handler {

    /**
     * Returns the list of paths within one &lt;QUESTION&gt; that this qtype needs to have included
     * in the grouped question structure
     *
     * @return array of strings
     */
    public function get_question_subpaths() {
        return array(
            'ANSWERS/ANSWER',
            'savpl',
        );
    }

    /**
     * Writes converted data into questions.xml
     *
     * @param array $data grouped question data
     * @param array $raw grouped raw QUESTION data
     */
    public function process_question(array $data, array $raw) {
        // Convert and write the answers first.
        if (isset($data['answers'])) {
            $this->write_answers($data['answers'], $this->pluginname);
        }

        // Convert and write the savpl extra fields.
        foreach ($data['savpl'] as $savpl) {
            $savpl['id'] = $this->converter->get_nextid();
            $this->write_xml('savpl', $savpl, array('/savpl/id'));
        }
    }
}
