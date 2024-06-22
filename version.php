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
 * Plug-in version and dependencies description.
 * @package    qtype_savpl
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_savpl';
$plugin->version   = 2024040700;
$plugin->requires  = 2018051700; // Moodle 3.5.
$plugin->maturity  = MATURITY_STABLE;
$plugin->release = '1.6.1';

$plugin->dependencies = array('mod_vpl' => 2017112412); // VPL 3.3.
