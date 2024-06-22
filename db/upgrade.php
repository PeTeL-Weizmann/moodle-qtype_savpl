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
 * Upgrade script for savpl question type
 *
 * @package    qtype_savpl
 * @copyright  2024 Devlion.ltd <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade code for the savpl question type.
 * @param int $oldversion the version we are upgrading from.
 */
function xmldb_qtype_savpl_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();
    $result = true;

    if ($oldversion < 2024040700) {
        // Define field usecase to be added to question_savpl.
        $table = new xmldb_table('question_savpl');
        $field = new xmldb_field('disablerun', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'gradingmethod');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Savepoint reached.
        //upgrade_plugin_savepoint(true, 2024040700, 'qtype', 'savpl');
    }

    return true;
}
