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
 * Run the drop script
 *
 * @package    qtype_savpl
 * @copyright  2024 Devlion.ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);
//disable
die();

require_once(__DIR__ . '/../../../config.php');
global $CFG, $DB;

require_once($CFG->libdir.'/clilib.php');      // cli only functions

$dbman = $DB->get_manager();
$table = new \xmldb_table('question_savpl');

foreach (['execfiles', 'precheckexecfiles'] as $filearea) {
    $field = new xmldb_field($filearea);
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    }
}