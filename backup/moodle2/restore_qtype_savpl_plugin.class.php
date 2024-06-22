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
 * Restore functions for Moodle 2.
 * @package    qtype_savpl
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides information to restore VPL Questions.
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_savpl_plugin extends restore_qtype_extrafields_plugin {
    // This question type uses extra_question_fields(), so almost nothing to do.

    /**
     * Process the qtype/savpl element
     * @param array $data question data
     */
    public function process_savpl($data) {
        $this->really_process_extra_question_fields($data);
        $syscontext = \context_system::instance();

        foreach (['execfiles', 'precheckexecfiles'] as $filearea) {
            // If the current progress object is set up and ready to receive
            // indeterminate progress, then use it, otherwise don't. (This check is
            // just in case this function is ever called from somewhere not within
            // the execute() method here, which does set up progress like this.)
            $progress = $this->task->get_progress();
            if (!$progress->is_in_progress_section() ||
                $progress->get_current_max() !== \core\progress\base::INDETERMINATE) {
                $progress = null;
            }


            $results = restore_dbops::send_files_to_pool($this->task->get_basepath(), $this->get_restoreid(), 'qtype_savpl',
                $filearea, $syscontext->id, $this->task->get_userid(), 'question_created', null, $syscontext->id, true,
                $progress);
            $resultstoadd = array();

            foreach ($results as $result) {
                $this->task->log($result->message, $result->level);
                $resultstoadd[$result->code] = true;
            }
            $this->task->add_result($resultstoadd);
        }
    }

    /**
     * Given one component/filearea/context and
     * optionally one source itemname to match itemids
     * put the corresponding files in the pool
     *
     * If you specify a progress reporter, it will get called once per file with
     * indeterminate progress.
     *
     * @param string $basepath the full path to the root of unzipped backup file
     * @param string $restoreid the restore job's identification
     * @param string $component
     * @param string $filearea
     * @param int $oldcontextid
     * @param int $dfltuserid default $file->user if the old one can't be mapped
     * @param string|null $itemname
     * @param int|null $olditemid
     * @param int|null $forcenewcontextid explicit value for the new contextid (skip mapping)
     * @param bool $skipparentitemidctxmatch
     * @param \core\progress\base $progress Optional progress reporter
     * @return array of result object
     */
    static function send_files_to_pool($basepath, $restoreid, $component, $filearea,
        $oldcontextid, $dfltuserid, $itemname = null, $olditemid = null,
        $forcenewcontextid = null, $skipparentitemidctxmatch = false,
        \core\progress\base $progress = null) {
        global $DB, $CFG;

        $backupinfo = backup_general_helper::get_backup_information(basename($basepath));
        $includesfiles = $backupinfo->include_files;

        $results = array();

        if ($forcenewcontextid) {
            // Some components can have "forced" new contexts (example: questions can end belonging to non-standard context mappings,
            // with questions originally at system/coursecat context in source being restored to course context in target). So we need
            // to be able to force the new contextid
            $newcontextid = $forcenewcontextid;
        } else {
            // Get new context, must exist or this will fail
            $newcontextrecord = restore_dbops::get_backup_ids_record($restoreid, 'context', $oldcontextid);
            if (!$newcontextrecord || !$newcontextrecord->newitemid) {
                throw new restore_dbops_exception('unknown_context_mapping', $oldcontextid);
            }
            $newcontextid = $newcontextrecord->newitemid;
        }

        // Sometimes it's possible to have not the oldcontextids stored into backup_ids_temp->parentitemid
        // columns (because we have used them to store other information). This happens usually with
        // all the question related backup_ids_temp records. In that case, it's safe to ignore that
        // matching as far as we are always restoring for well known oldcontexts and olditemids
        $parentitemctxmatchsql = ' AND i.parentitemid = f.contextid ';
        if ($skipparentitemidctxmatch) {
            $parentitemctxmatchsql = '';
        }

        // Important: remember how files have been loaded to backup_files_temp
        //   - info: contains the whole original object (times, names...)
        //   (all them being original ids as loaded from xml)

        // itemname = null, we are going to match only by context, no need to use itemid (all them are 0)
        if ($itemname == null) {
            $sql = "SELECT id AS bftid, contextid, component, filearea, itemid, itemid AS newitemid, info
                      FROM {backup_files_temp}
                     WHERE backupid = ?
                       AND contextid = ?
                       AND component = ?
                       AND filearea  = ?";
            $params = array($restoreid, $oldcontextid, $component, $filearea);

            // itemname not null, going to join with backup_ids to perform the old-new mapping of itemids
        } else {
            $sql = "SELECT f.id AS bftid, f.contextid, f.component, f.filearea, f.itemid, i.newitemid, f.info
                      FROM {backup_files_temp} f
                      JOIN {backup_ids_temp} i ON i.backupid = f.backupid
                                              $parentitemctxmatchsql
                                              AND i.itemid = f.itemid
                     WHERE f.backupid = ?
                       AND f.contextid = ?
                       AND f.component = ?
                       AND f.filearea = ?
                       AND i.itemname = ?";
            $params = array($restoreid, $oldcontextid, $component, $filearea, $itemname);
        }

        $fs = get_file_storage();         // Get moodle file storage
        $basepath = $basepath . '/files/';// Get backup file pool base
        // Report progress before query.
        if ($progress) {
            $progress->progress();
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $rec) {
            // Report progress each time around loop.
            if ($progress) {
                $progress->progress();
            }

            $file = (object)backup_controller_dbops::decode_backup_temp_info($rec->info);

            // ignore root dirs (they are created automatically)
            if ($file->filepath == '/' && $file->filename == '.') {
                continue;
            }

            // set the best possible user
            $mappeduser = self::get_backup_ids_record($restoreid, 'user', $file->userid);
            $mappeduserid = !empty($mappeduser) ? $mappeduser->newitemid : $dfltuserid;

            // dir found (and not root one), let's create it
            if ($file->filename == '.') {
                $fs->create_directory($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $mappeduserid);
                continue;
            }

            // Updated the times of the new record.
            // The file record should reflect when the file entered the system,
            // and when this record was created.
            $time = time();
            $newitemid = restore_dbops::get_backup_ids_record($restoreid, 'id', $oldcontextid);

            // The file record to restore.
            $file_record = array(
                'contextid'    => $newcontextid,
                'component'    => $component,
                'filearea'     => $filearea,
                'itemid'       => $rec->newitemid,
                'filepath'     => $file->filepath,
                'filename'     => $file->filename,
                'timecreated'  => $time,
                'timemodified' => $time,
                'userid'       => $mappeduserid,
                'source'       => $file->source,
                'author'       => $file->author,
                'license'      => $file->license,
                'sortorder'    => $file->sortorder
            );

            if (empty($file->repositoryid)) {
                // If contenthash is empty then gracefully skip adding file.
                if (empty($file->contenthash)) {
                    $result = new stdClass();
                    $result->code = 'file_missing_in_backup';
                    $result->message = sprintf('missing file (%s) contenthash in backup for component %s', $file->filename, $component);
                    $result->level = backup::LOG_WARNING;
                    $results[] = $result;
                    continue;
                }
                // this is a regular file, it must be present in the backup pool
                $backuppath = $basepath . backup_file_manager::get_backup_content_file_location($file->contenthash);

                // Some file types do not include the files as they should already be
                // present. We still need to create entries into the files table.
                if ($includesfiles) {
                    // The file is not found in the backup.
                    if (!file_exists($backuppath)) {
                        $results[] = self::get_missing_file_result($file);
                        continue;
                    }

                    // create the file in the filepool if it does not exist yet
                    if (!$fs->file_exists($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $file->filename)) {

                        // If no license found, use default.
                        if ($file->license == null){
                            $file->license = $CFG->sitedefaultlicense;
                        }

                        $fs->create_file_from_pathname($file_record, $backuppath);
                    }
                } else {
                    // This backup does not include the files - they should be available in moodle filestorage already.

                    // Create the file in the filepool if it does not exist yet.
                    if (!$fs->file_exists($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $file->filename)) {

                        // Even if a file has been deleted since the backup was made, the file metadata may remain in the
                        // files table, and the file will not yet have been moved to the trashdir. e.g. a draft file version.
                        // Try to recover from file table first.
                        if ($foundfiles = $DB->get_records('files', array('contenthash' => $file->contenthash), '', '*', 0, 1)) {
                            // Only grab one of the foundfiles - the file content should be the same for all entries.
                            $foundfile = reset($foundfiles);
                            $fs->create_file_from_storedfile($file_record, $foundfile->id);
                        } else {
                            $filesystem = $fs->get_file_system();
                            $restorefile = $file;
                            $restorefile->contextid = $newcontextid;
                            $restorefile->itemid = $rec->newitemid;
                            $storedfile = new stored_file($fs, $restorefile);

                            // Ok, let's try recover this file.
                            // 1. We check if the file can be fetched locally without attempting to fetch
                            //    from the trash.
                            // 2. We check if we can get the remote filepath for the specified stored file.
                            // 3. We check if the file can be fetched from the trash.
                            // 4. All failed, say we couldn't find it.
                            if ($filesystem->is_file_readable_locally_by_storedfile($storedfile)) {
                                $localpath = $filesystem->get_local_path_from_storedfile($storedfile);
                                $fs->create_file_from_pathname($file, $localpath);
                            } else if ($filesystem->is_file_readable_remotely_by_storedfile($storedfile)) {
                                $remotepath = $filesystem->get_remote_path_from_storedfile($storedfile);
                                $fs->create_file_from_pathname($file, $remotepath);
                            } else if ($filesystem->is_file_readable_locally_by_storedfile($storedfile, true)) {
                                $localpath = $filesystem->get_local_path_from_storedfile($storedfile, true);
                                $fs->create_file_from_pathname($file, $localpath);
                            } else {
                                // A matching file was not found.
                                $results[] = self::get_missing_file_result($file);
                                continue;
                            }
                        }
                    }
                }

                // store the the new contextid and the new itemid in case we need to remap
                // references to this file later
                $DB->update_record('backup_files_temp', array(
                    'id' => $rec->bftid,
                    'newcontextid' => $newcontextid,
                    'newitemid' => $rec->newitemid), true);

            } else {
                // this is an alias - we can't create it yet so we stash it in a temp
                // table and will let the final task to deal with it
                if (!$fs->file_exists($newcontextid, $component, $filearea, $rec->newitemid, $file->filepath, $file->filename)) {
                    $info = new stdClass();
                    // oldfile holds the raw information stored in MBZ (including reference-related info)
                    $info->oldfile = $file;
                    // newfile holds the info for the new file_record with the context, user and itemid mapped
                    $info->newfile = (object) $file_record;

                    restore_dbops::set_backup_ids_record($restoreid, 'file_aliases_queue', $file->id, 0, null, $info);
                }
            }
        }
        $rs->close();
        return $results;
    }
}
