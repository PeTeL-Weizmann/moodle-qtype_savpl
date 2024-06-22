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
 * Basic semaphor function library.
 * @package    qtype_savpl
 * @copyright  2023 Devlion.co <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create a semaphor with given key.
 * @param int $key The key to create the semaphor.
 * @return resource An identifier for the created semaphor, to use with other semaphor functions.
 */
function savpl_semaphor_get($key) {
    global $CFG;
    $dir = $CFG->dataroot . '/savpl_data/semaphor';
    make_writable_directory($dir);
    return fopen($dir . '/lock.sem.'.$key, 'w+');
}
/**
 * Acquire a lock on given semaphor.
 * @param int $semid The semaphor identifier.
 * @return bool
 */
function savpl_semaphor_acquire($semid) {
    return flock($semid, LOCK_EX);
}
/**
 * Release the lock on given semaphor.
 * @param int $semid The semaphor identifier.
 * @return bool
 */
function savpl_semaphor_release($semid) {
    return flock($semid, LOCK_UN);
}
