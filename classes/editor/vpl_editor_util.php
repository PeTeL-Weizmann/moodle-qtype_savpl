<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * IDE utility functions
 *
 * @package    qtype_savpl
 * @copyright  2023 Devlion.co <info@devlion.co>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Juan Carlos Rodríguez-del-Pino <jcrodriguez@dis.ulpgc.es>
 */

namespace qtype_savpl\editor;

use moodle_url;

class vpl_editor_util {
    public static function generate_jquery() {
        global $PAGE;
        $PAGE->requires->jquery();
        $PAGE->requires->jquery_plugin('ui');
        $PAGE->requires->jquery_plugin('ui-css');
    }
    public static function generate_requires_evaluation() {
        global $PAGE;
        self::generate_jquery();
        $PAGE->requires->css( new moodle_url( '/question/type/savpl/editor/VPLIDE.css' ) );
    }
    public static function generate_requires($options) {
        global $PAGE, $COURSE;
        global $CFG;
        $coursecontext = \context_course::instance($COURSE->id);

        $plugincfg = get_config('mod_vpl');
        $tagid = 'vplide';
        if ( isset($plugincfg->editor_theme) ) {
            $options['theme'] = $plugincfg->editor_theme;
        } else {
            $options['theme'] = 'chrome';
        }
        $options['fontSize'] = get_user_preferences('vpl_editor_fontsize', 12);
        $options['theme'] = get_user_preferences('vpl_acetheme', $options['theme']);
        $options['lang'] = $CFG->lang;
        $options['postMaxSize'] = \mod_vpl\util\phpconfig::get_post_max_size();
        $options['isGroupActivity'] = false;
        $options['isTeacher'] = has_capability('moodle/course:update', $coursecontext);
        self::generate_jquery();
        $PAGE->requires->js( new moodle_url( '/question/type/savpl/editor/zip/inflate.js' ) );
        $PAGE->requires->js( new moodle_url( '/question/type/savpl/editor/zip/unzip.js' ) );
        $PAGE->requires->js( new moodle_url( '/question/type/savpl/editor/xterm/term.js' ) );
        $PAGE->requires->js( new moodle_url( '/question/type/savpl/editor/noVNC/include/util.js' ) );
        $PAGE->requires->css( new moodle_url( '/question/type/savpl/editor/VPLIDE.css' ) );
        $PAGE->requires->js_call_amd('qtype_savpl/vplide', 'init', array($tagid, $options));
    }
    public static function print_js_i18n() {
        global $CFG;
        $return = '        <script>
        window.VPLi18n = ' . json_encode(self::i18n()) .';
        </script>';
        if ($CFG->debugdeveloper) {
            $return .= '<script>window.VPLDebugMode = true;</script>';
        }

        return $return;
    }
    public static function print_js_description($vpl, $userid) {
        $html = $vpl->get_variation_html($userid);
        $html .= $vpl->get_fulldescription_with_basedon();
        $html = '<script>window.VPLDescription = ' . json_encode($html) . ';</script>';

        return $html;
    }
    public static function print_tag() {
        global $OUTPUT;
        $templatedata = ['tagid' => 'vplide'];
        return $OUTPUT->render_from_template('qtype_savpl/editor_tag', $templatedata);
    }
    /**
     * Get the list of i18n translations for the editor
     */
    public static function i18n() {
        $vplwords = array (
                'about',
                'acceptcertificates',
                'acceptcertificatesnote',
                'binaryfile',
                'browserupdate',
                'changesNotSaved',
                'clipboard',
                'comments',
                'compilation',
                'connected',
                'connecting',
                'connection_closed',
                'connection_fail',
                'console',
                'copy',
                'create_new_file',
                'cut',
                'description',
                'debug',
                'debugging',
                'delete',
                'delete_file_fq',
                'delete_file_q',
                'download',
                'edit',
                'evaluate',
                'evaluating',
                'execution',
                'getjails',
                'file',
                'filelist',
                'filenotadded',
                'filenotdeleted',
                'filenotrenamed',
                'find',
                'find_replace',
                'fullscreen',
                'incorrect_file_name',
                'keyboard',
                'maxfilesexceeded',
                'new',
                'next',
                'load',
                'loading',
                'open',
                'options',
                'outofmemory',
                'paste',
                'print',
                'redo',
                'regularscreen',
                'rename',
                'rename_file',
                'resetfiles',
                'retrieve',
                'run',
                'running',
                'save',
                'saving',
                'select_all',
                'shortcuts',
                'sureresetfiles',
                'timeleft',
                'timeout',
                'undo',
                'multidelete',
                'basic',
                'intermediate',
                'advanced',
                'variables',
                'operatorsvalues',
                'control',
                'inputoutput',
                'functions',
                'lists',
                'math',
                'text',
                'start',
                'startanimate',
                'stop',
                'pause',
                'resume',
                'step',
                'breakpoint',
                'selectbreakpoint',
                'removebreakpoint',
                'maxpostsizeexceeded',
        );
        $words = array (
                'cancel',
                'closebuttontitle',
                'error',
                'import',
                'modified',
                'no',
                'notice',
                'ok',
                'required',
                'sort',
                'warning',
                'yes',
                'deleteselected',
                'selectall',
                'deselectall',
                'reset'
        );
        $list = Array ();
        foreach ($vplwords as $word) {
            $list[$word] = get_string( $word, 'mod_vpl' );
        }
        foreach ($words as $word) {
            $list[$word] = get_string( $word );
        }
        $list['close'] = get_string( 'closebuttontitle' );
        $list['more'] = get_string( 'showmore', 'form' );
        $list['less'] = get_string( 'showless', 'form' );
        $list['fontsize'] = get_string( 'fontsize', 'editor' );
        $list['theme'] = get_string( 'theme' );
        return $list;
    }
    public static function generate_evaluate_script($ajaxurl, $nexturl) {
        global $PAGE;
        $options = Array ();
        $options['ajaxurl'] = $ajaxurl;
        $options['nexturl'] = $nexturl;
        $PAGE->requires->js_call_amd('mod_vpl/evaluationmonitor', 'init', array($options) );
    }
    public static function generate_batch_evaluate_sript($ajaxurls) {
        $options = Array ();
        $options['ajaxurls'] = $ajaxurls;
        $joptions = json_encode( $options );
        $output = self::print_js_i18n();
        $output .= '<script>VPL_Batch_Evaluation(' . $joptions . ');</script>';

        return $output;
    }

    public static function save_files($questionid, $files, $filearea) {
        global $DB;

        $fs = get_file_storage();
        $contextid = \context_system::instance()->id;
        $component = 'qtype_savpl';
        $filepath = '/';
        $filerecord = [
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $questionid,
            'filepath' => $filepath,
            'filename' => ''
        ];

        $fs->delete_area_files($contextid, $component, $filearea, $questionid);

        foreach ($files as $filename => $filecontent) {
            $filerecord['filename'] = $filename;
            $fs->create_file_from_string($filerecord, $filecontent);
        }

        $DB->set_field('question_savpl', $filearea, '', ['questionid' => $questionid]);
    }

    public static function get_files($questionid, $field) {
        global $DB;

        $return = [];

        $question = $DB->get_record('question_savpl', array('questionid' => $questionid));
        if (property_exists($question, $field)) {
            $return = json_decode($question->$field, true);
        }

        if (empty($return)) {
            $fs = get_file_storage();
            $syscontext = \context_system::instance();


            foreach ($fs->get_area_files($syscontext->id, 'qtype_savpl', $field, $questionid, 'id', false) as $file) {
                if ($file->is_directory()) {
                    // Do not export directories.
                    // If they contain file contents the directory structure will be created in the zip file.
                    continue;
                }
                $return[$file->get_filename()] = $file->get_content();
            }
        }

        return $return;
    }

    public static function get_filerecord($questionid) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $qbentry = get_question_bank_entry($questionid);
        $contextid = $DB->get_field_sql("SELECT contextid FROM {question_categories} qc WHERE qc.id = ?", [$qbentry->questioncategoryid]);

        $filerecord = (object) [
            'contextid' => $contextid,
            'context' => \context::instance_by_id($contextid),
            'component' => 'qtype_savpl',
            'filearea' => 'requestedfiles',
            'itemid' => $questionid,
            'sortorder' => 0,
            'filepath' => '/',
        ];

        return $filerecord;
    }

    /**
     * Outputs a zip file and removes it. Must be called before any other output
     *
     * @param string $zipfilename. Name of the ZIP file with the data
     * @param string $name of file to be shown, without '.zip'
     *
     */
    static function savpl_output_zip($zipfilename, $name) {
        if (! file_exists($zipfilename)) {
            debugging("Zip file not found " . $zipfilename, DEBUG_DEVELOPER);
            throw new \moodle_exception('error:zipnotfound', 'mod_vpl');
        }
        // Send zipdata.
        $blocksize = 1000 * 1024;
        $size = filesize( $zipfilename );
        $cname = rawurlencode( $name . '.zip' );
        $contentdisposition = 'Content-Disposition: attachment;';
        $contentdisposition .= ' filename="' . $name . '.zip";';
        $contentdisposition .= ' filename*=utf-8\'\'' . $cname;

        @header( 'Content-Length: ' . $size );
        @header( 'Content-Type: application/zip; charset=utf-8' );
        @header( $contentdisposition );
        @header( 'Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0' );
        @header( 'Content-Transfer-Encoding: binary' );
        @header( 'Expires: 0' );
        @header( 'Pragma: no-cache' );
        @header( 'Accept-Ranges: none' );
        // Get zip data.
        $offset = 0;
        while ($offset < $size) {
            echo file_get_contents( $zipfilename, false,  null, $offset, $blocksize);
            $offset += $blocksize;
        }
        // Remove zip file.
        unlink( $zipfilename );
    }

    static public function generate_zip_file($questionid) {
        global $CFG;
        global $USER;

        require_once($CFG->dirroot . '/mod/vpl/locallib.php');

        $zip = new \ZipArchive();
        $dir = $CFG->dataroot . '/temp/savpl';
        if (! file_exists($dir) ) {
            mkdir($dir, $CFG->directorypermissions, true);
        }
        $zipfilename = tempnam( $dir, 'zip' );
        $filelist = static::get_files($questionid, 'execfiles');
        if (count($filelist) > 0) {
            if ($zip->open( $zipfilename, \ZipArchive::OVERWRITE ) === true) {
                foreach ($filelist as $filename => $data) {
                    $zip->addFromString( $filename, $data );
                }
                $zip->close();
            } else {
                return false;
            }
        } else {
            vpl_fwrite($zipfilename, base64_decode("UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA=="));
        }
        return $zipfilename;
    }

    /**
     * Download files as zip
     *
     * @parm $name name of the generated zip file
     */
    static function download_files($name) {
        $zipfilename = static::generate_zip_file($name);
        if ($zipfilename !== false) {
            static::savpl_output_zip($zipfilename, $name);
            die();
        }
    }
}
