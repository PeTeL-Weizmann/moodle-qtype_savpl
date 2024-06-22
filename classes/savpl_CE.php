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
 * Compilation and Execution of submission class definition
 *
 * @package    qtype_savpl
 * @copyright  2023 Devlion.co <info@devlion.co>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qtype_savpl;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/mod/vpl/locallib.php');
require_once($CFG->dirroot . '/mod/vpl/jail/jailserver_manager.class.php');
require_once($CFG->dirroot . '/mod/vpl/jail/running_processes.class.php');

use qtype_savpl\editor\vpl_editor_util;
use \Exception;
use \moodle_exception;
use \StdClass;
use \vpl_jailserver_manager;
use \vpl_running_processes;

class savpl_CE {

    protected $userid;

    protected $filerecord;

    protected $question;

    protected $config;

    protected $already;

    private static $languageext = array (
        'ada' => 'ada',
        'adb' => 'ada',
        'ads' => 'ada',
        'all' => 'all',
        'asm' => 'asm',
        'c' => 'c',
        'cc' => 'cpp',
        'cpp' => 'cpp',
        'C' => 'cpp',
        'c++' => 'cpp',
        'clj' => 'clojure',
        'cs' => 'csharp',
        'd' => 'd',
        'erl' => 'erlang',
        'go' => 'go',
        'groovy' => 'groovy',
        'java' => 'java',
        'jl' => 'julia',
        'js' => 'javascript',
        'scala' => 'scala',
        'sql' => 'sql',
        'scm' => 'scheme',
        's' => 'mips',
        'kt' => 'kotlin',
        'lisp' => 'lisp',
        'lsp' => 'lisp',
        'lua' => 'lua',
        'sh' => 'shell',
        'pas' => 'pascal',
        'p' => 'pascal',
        'f77' => 'fortran',
        'f90' => 'fortran',
        'f' => 'fortran',
        'for' => 'fortran',
        'pl' => 'prolog',
        'pro' => 'prolog',
        'htm' => 'html',
        'html' => 'html',
        'hs' => 'haskell',
        'm' => 'matlab',
        'mzn' => 'minizinc',
        'perl' => 'perl',
        'prl' => 'perl',
        'php' => 'php',
        'py' => 'python',
        'v' => 'verilog',
        'vh' => 'verilog',
        'vhd' => 'vhdl',
        'vhdl' => 'vhdl',
        'r' => 'r',
        'R' => 'r',
        'rb' => 'ruby',
        'ruby' => 'ruby',
        'ts' => 'typescript'
    );
    private static $scriptname = array (
        'vpl_run.sh' => 'run',
        'vpl_debug.sh' => 'debug',
        'vpl_evaluate.sh' => 'evaluate'
    );
    private static $scripttype = array (
        'vpl_run.sh' => 0,
        'vpl_debug.sh' => 1,
        'vpl_evaluate.sh' => 2,
        'vpl_evaluate.cases' => 2
    );
    private static $scriptlist = array (
        0 => 'vpl_run.sh',
        1 => 'vpl_debug.sh',
        2 => 'vpl_evaluate.sh'
    );

    private array $files = [];
    private array $execfiles = [];

    const COMPILATIONFN = 'compilation.txt';
    const EXECUTIONFN = 'execution.txt';

    const GRADETAG = 'Grade :=>>';
    const COMMENTTAG = 'Comment :=>>';
    const BEGINCOMMENTTAG = '<|--';
    const ENDCOMMENTTAG = '--|>';

    /**
     * @return mixed
     */
    public function __construct($question, $userid, $files = [], $execfiles = []) {
        global $DB;
        $this->question = $question;
        $this->questiondata = $DB->get_record('question', ['id' => $this->question->questionid]);
        $this->userid = $userid;
        $this->filerecord = vpl_editor_util::get_filerecord($question->questionid);
        $this->config = get_config('mod_vpl');
        $this->files = $files;
        $this->execfiles = $execfiles ?: static::filesdecode(\qtype_savpl\editor\vpl_editor_util::get_files($this->question->questionid, 'execfiles'));
    }

    /**
     * @return []
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Request the execution (run|debug|evaluate)of a user's submission
     * @param mod_vpl $vpl
     * @param int $userid
     * @param string $action
     * @param array $options for the execution
     * @throws Exception
     * @return Object with execution information
     */
    public function execute($action, $options = array()) {

        $code = array (
            'run' => 0,
            'debug' => 1,
            'evaluate' => 2
        );
        /*
        $traslate = array (
            'run' => 'run',
            'debug' => 'debugged',
            'evaluate' => 'evaluated'
        );
        $eventclass = '\mod_vpl\event\submission_' . $traslate[$action];
        $eventclass::log( $submission );
        */
        return $this->run( $code[$action], $options );
    }

    /**
     * Run, debug, evaluate
     *
     * @param int $type
     *            (0=run, 1=debug, evaluate=2)
     */
    public function run($type, $options = array()) {
        global $SESSION;
        // Stop current task if one.
        $this->cancelprocess();
        $options = ( array ) $options;
        $executescripts = array (
            0 => 'vpl_run.sh',
            1 => 'vpl_debug.sh',
            2 => 'vpl_evaluate.sh'
        );
        $data = $this->prepare_execution( $type );
        $data->execute = $executescripts[$type];
        $data->interactive = $type < 2 ? 1 : 0;
        $data->lang = vpl_get_lang( true );
        if (isset( $options['XGEOMETRY'] )) { // TODO refactor to a better solution.
            $data->files['vpl_environment.sh'] .= "\n".vpl_bash_export( 'VPL_XGEOMETRY', $options['XGEOMETRY'] );
        }
        if (isset( $options['COMMANDARGS'] )) {
            $data->commandargs = $options['COMMANDARGS'];
        }
        $localservers = $data->jailservers;
        $maxmemory = $data->maxmemory;
        // Remove jailservers field.
        unset( $data->jailservers );
        // Adapt files to send binary as base64.
        $fileencoding = array();
        $encodefiles = array ();
        foreach ($data->files as $filename => $filedata) {
            if (vpl_is_binary( $filename )) {
                $encodefiles[$filename . '.b64'] = base64_encode( $filedata );
                $fileencoding[$filename . '.b64'] = 1;
                $data->filestodelete[$filename . '.b64'] = 1;
            } else {
                $fileencoding[$filename] = 0;
                $encodefiles[$filename] = $filedata;
            }
            $data->files[$filename] = '';
        }
        $data->files = $encodefiles;
        $data->fileencoding = $fileencoding;
        $jailserver = '';

        $jailresponse = $this->jailrequestaction( $data, $maxmemory, $localservers, $jailserver );
        $parsed = parse_url( $jailserver );
        // Fix jail server port.
        if (! isset( $parsed['port'] ) && $parsed['scheme'] == 'http') {
            $parsed['port'] = 80;
        }
        if (! isset( $jailresponse['port'] )) { // Try to fix old jail servers that don't return port.
            $jailresponse['port'] = $parsed['port'];
        }

        $response = new stdClass();
        $response->server = $parsed['host'];
        $response->monitorPath = $jailresponse['monitorticket'] . '/monitor';
        $response->executionPath = $jailresponse['executionticket'] . '/execute';
        $response->port = $jailresponse['port'];
        $response->securePort = $jailresponse['secureport'];
        $response->wsProtocol = $this->config->websocket_protocol;
        $response->VNCpassword = substr( $jailresponse['executionticket'], 0, 8 );

        $SESSION->savplrunningprocesses[$this->userid][$this->question->questionid] = (object) [
            'server' => $jailserver,
            'adminticket' => $jailresponse['adminticket']
        ];
        return $response;
    }

    /**
     * Recopile execution data to be send to the jail
     *
     * @param array $already=array().
     *            List of based on instances, usefull to avoid infinite recursion
     * @param mod_vpl $vpl. VPl instance to process. Default = null
     * @return object with files, limits, interactive and other info
     */
    public function prepare_execution($type, &$already = array()) {
        global $DB, $CFG;

        if (isset( $already[$this->question->questionid] )) {
            throw new moodle_exception('error:recursivedefinition', 'mod_vpl');
        }
        $call = count( $already );
        $already[$this->question->questionid] = true;

        $data = new stdClass();
        $data->files = array ();
        $data->filestodelete = array ();

        // Execution files.
        $list = $this->execfiles;

        foreach ($list as $filename => $filecontent) {
            // Skip unneeded script.
            if (isset( self::$scripttype[$filename] ) && self::$scripttype[$filename] > $type) {
                continue;
            }
            if (isset( $data->files[$filename] ) && isset( self::$scriptname[$filename] )) {
                $data->files[$filename] .= "\n" . $filecontent;
            } else {
                $data->files[$filename] = $filecontent;
            }
            if (in_array($filename, array_keys(static::$scripttype))) {
                //delete only standard scripts
                $data->filestodelete[$filename] = 1;
            }
        }

        $data->maxtime = ( int ) $this->config->defaultexetime;
        $data->maxfilesize = ( int ) $this->config->defaultexefilesize;
        $data->maxmemory = ( int ) $this->config->defaultexememory;
        $data->maxprocesses = ( int ) $this->config->defaultexeprocesses;
        $data->jailservers = '';
        $data->runscript = $this->question->runscript;
        $data->debugscript = $this->question->debugscript;
        $data->jailservers = $this->config->jail_servers;

        if ($call > 0) { // Stop if at recursive call.
            return $data;
        }
        // Var $submittedlist is $list but removing the files overwrited by teacher's one.
        $submittedlist = array ();
        $list = $this->files;

        foreach ($list as $filename => $filecontent) {
            if (! isset( $data->files[$filename] )) {
                $data->files[$filename] = $filecontent;
            }
            $submittedlist[] = $filename;
        }
        // Get programming language.
        $pln = $this->get_pln( array_keys($list) );
        // Adapt Java and HTML memory limit.
        if ($pln == 'java' || $pln == 'html') {
            $javaoffset = 128 * 1024 * 1024; // Checked at Ubuntu 12.04 64 and CentOS 6.5 64.
            if ($data->maxmemory + $javaoffset > $data->maxmemory) {
                $data->maxmemory += $javaoffset;
            } else {
                $data->maxmemory = ( int ) PHP_INT_MAX;
            }
        }
        // Limit resource to maximum.
        $data->maxtime = min( $data->maxtime, ( int ) $this->config->maxexetime );
        $data->maxfilesize = min( $data->maxfilesize, ( int ) $this->config->maxexefilesize );
        $data->maxmemory = min( $data->maxmemory, ( int ) $this->config->maxexememory );
        $data->maxprocesses = min( $data->maxprocesses, ( int ) $this->config->maxexeprocesses );
        // Info send with script.
        $info = "#!/bin/bash\n";
        $info .= vpl_bash_export( 'VPL_LANG', vpl_get_lang( true ) );
        $info .= vpl_bash_export( 'MOODLE_USER_ID',  $this->userid );

        if ($user = $DB->get_record( 'user', array ( 'id' => $this->userid ) )) {
            $info .= vpl_bash_export( 'MOODLE_USER_NAME', fullname( $user ) );
            $info .= vpl_bash_export( 'MOODLE_USER_EMAIL', $user->email );
        }

        if ($type == 2) { // If evaluation add information.
            $info .= vpl_bash_export( 'VPL_MAXTIME', $data->maxtime );
            $info .= vpl_bash_export( 'VPL_MAXMEMORY',  $data->maxmemory );
            $info .= vpl_bash_export( 'VPL_MAXFILESIZE',  $data->maxfilesize );
            $info .= vpl_bash_export( 'VPL_MAXPROCESSES',  $data->maxprocesses );

            if ($this->questiondata->defaultmark) {
                $info .= vpl_bash_export( 'VPL_GRADEMIN',  strval(number_format(0, 5)) /*TODO*/  );
                $info .= vpl_bash_export( 'VPL_GRADEMAX',  strval(number_format($this->questiondata->defaultmark, 5)) );
            }
            $info .= vpl_bash_export( 'VPL_COMPILATIONFAILED', get_string( 'VPL_COMPILATIONFAILED', VPL ) );
        }
        $filenames = '';
        $num = 0;
        foreach ($submittedlist as $filename) {
            $filenames .= $filename . "\n";
            $info .= vpl_bash_export( 'VPL_SUBFILE' . $num, $filename );
            $num ++;
        }
        $info .= 'export VPL_SUBFILES="' . $filenames . "\"\n";
        // Add identifications of variations if exist.
        $info .= vpl_bash_export( 'VPL_VARIATION', '' );

        for ($i = 0; $i <= $type; $i ++) {
            $script = self::$scriptlist[$i];
            if (isset( $data->files[$script] ) && trim( $data->files[$script] ) > '') {
                if (substr( $data->files[$script], 0, 2 ) != '#!') {
                    // No shebang => add bash.
                    $data->files[$script] = "#!/bin/bash\n" . $data->files[$script];
                }
            } else {
                $filesadded = $this->get_default_script( $script, $pln, $data );
                foreach ($filesadded as $filename => $filedata) {
                    if (trim( $filedata ) > '') {
                        $data->files[$filename] = $filedata;
                        $data->filestodelete[$filename] = 1;
                    }
                }
            }
        }

        // Add script file with VPL environment information.
        $data->files['vpl_environment.sh'] = $info;
        $data->files['common_script.sh'] = file_get_contents( $CFG->dirroot . '/mod/vpl/jail/default_scripts/common_script.sh' );

        if (isset($data->files['vpl_evaluate.cases_qvpl'])) {
            $data->files['vpl_evaluate.cases'] = $data->files['vpl_evaluate.cases_qvpl'];
        }

        // TODO change jail server to avoid this patch.
        if (count( $data->filestodelete ) == 0) { // If keeping all files => add dummy.
            $data->filestodelete['__vpl_to_delete__'] = 1;
        }
        // Info to log who/what.
        $data->userid = $this->userid;
        $data->questionid = $this->question->questionid;
        return $data;
    }

    /**
     * Return the programming language name based on submitted files extensions
     *
     * @param $filelist array
     *            of files submitted to check type
     * @return string programming language name
     */
    public function get_pln($filelist) {
        foreach ($filelist as $checkfilename) {
            $ext = pathinfo( $checkfilename, PATHINFO_EXTENSION );
            if (isset( self::$languageext[$ext] )) {
                return self::$languageext[$ext];
            }
        }
        return 'default';
    }

    /**
     * Return the default script to manage the action and detected language
     *
     * @param $script string 'vpl_run.sh','vpl_debug.sh' o 'vpl_evaluate.sh'
     * @param $pln string Programming Language Name
     * @param $data object execution data
     *
     * @return array key=>filename value =>filedata
     */
    public function get_default_script($script, $pln, $data) {
        global $CFG;

        $ret = array ();
        $path = $CFG->dirroot . '/mod/vpl/jail/default_scripts/';
        $scripttype = self::$scriptname[$script];
        $field = $scripttype . 'script';
        if ( isset($data->$field) &&  $data->$field > '' ) {
            $pln = $data->$field;
        }
        $filename = $path . $pln . '_' . $scripttype . '.sh';
        if (file_exists( $filename )) {
            $ret[$script] = file_get_contents( $filename );
        } else {
            $filename = $path . 'default' . '_' . $scripttype . '.sh';
            if (file_exists( $filename )) {
                $ret[$script] = file_get_contents( $filename );
            } else {
                $ret[$script] = file_get_contents( $path . 'default.sh' );
            }
        }
        if ($script == 'vpl_evaluate.sh') {
            $ret['vpl_evaluate.cpp'] = file_get_contents( $path . 'vpl_evaluate.cpp' );
        }
        $manager = has_capability('moodle/course:update', $this->filerecord->context);
        if ($pln == 'all' && $manager) { // Test all scripts.
            $dirpath = dirname( __FILE__ ) . '/jail/default_scripts';
            if (file_exists( $dirpath )) {
                $dirlst = opendir( $dirpath );
                while ( false !== ($filename = readdir( $dirlst )) ) {
                    if ($filename == "." || $filename == "..") {
                        continue;
                    }
                    if (substr( $filename, - 7 ) == '_run.sh' ||
                        substr( $filename, - 9 ) == '_hello.sh' ||
                        substr( $filename, - 9 ) == '_debug.sh' ) {
                        $ret[$filename] = file_get_contents( $path . $filename );
                    }
                }
                closedir( $dirlst );
            }
        }
        return $ret;
    }

    public function jailaction($server, $action, $data) {
        global $CFG;

        if (! function_exists( 'xmlrpc_encode_request' )) {
            throw new Exception( 'Inernal server error: PHP XMLRPC requiered' );
        }

        $plugin = new stdClass();
        require($CFG->dirroot . '/mod/vpl/version.php');
        $pluginversion = $plugin->version;
        $data->pluginversion = $pluginversion;

        $request = xmlrpc_encode_request( $action, $data, array (
                'encoding' => 'UTF-8'
        ) );
        $error = '';
        $response = vpl_jailserver_manager::get_response( $server, $request, $error );

        if ($response === false) {
            $manager = has_capability('moodle/course:update', $this->filerecord->context);
            if ($manager) {
                throw new Exception( get_string( 'serverexecutionerror', VPL ) . "\n" . $error );
            }
            throw new Exception( get_string( 'serverexecutionerror', VPL ) );
        }
        return $response;
    }

    public function jailrequestaction($data, $maxmemory, $localservers, &$server) {
        $error = '';
        $server = vpl_jailserver_manager::get_server( $maxmemory, $localservers, $error );
        if ($server == '') {
            $manager = has_capability('moodle/course:update', $this->filerecord->context);
            $men = get_string( 'nojailavailable', VPL );
            if ($manager) {
                $men .= ": " . $error;
            }
            throw new Exception( $men );
        }
        return $this->jailaction( $server, 'request', $data );
    }

    public function jailreaction($action, $processinfo = false) {
        global $SESSION;

        if ($processinfo === false) {
            $processinfo = $SESSION->savplrunningprocesses[$this->userid][$this->question->questionid];
        }
        if ($processinfo === false) {
            throw new Exception( 'Process not found' );
        }
        $server = $processinfo->server;
        $data = new stdClass();
        $data->adminticket = $processinfo->adminticket;
        return $this->jailaction( $server, $action, $data );
    }

    /**
     * Send update command to the jail
     *
     * @param mod_vpl $vpl. VPl instance to process. Default = null
     * @param string[string] $files. Files to send
     * @return boolean if update sent
     */
    public function update($files) {
        global $SESSION;

        $processinfo = $SESSION->savplrunningprocesses[$this->userid][$this->question->questionid];

        $data = new stdClass();
        $data->files = $files;

        if ($processinfo == null) { // No process to cancel.
            return false;
        }
        $server = $processinfo->server;
        $data = new stdClass();
        $data->files = $files;
        $fileencoding = array();
        $encodefiles = array ();
        foreach ($data->files as $filename => $filedata) {
            if (vpl_is_binary( $filename )) {
                $encodefiles[$filename . '.b64'] = base64_encode( $filedata );
                $fileencoding[$filename . '.b64'] = 1;
                $data->filestodelete[$filename . '.b64'] = 1;
            } else {
                $fileencoding[$filename] = 0;
                $encodefiles[$filename] = $filedata;
            }
            $data->files[$filename] = '';
        }
        $data->files = $encodefiles;
        $data->fileencoding = $fileencoding;
        $data->adminticket = $processinfo->adminticket;
        try {
            $response = $this->jailaction( $server, 'update', $data );
        } catch ( Exception $e ) {
            return false;
        }
        return $response['update'] > 0;
    }

    public function retrieveresult() {
        $response = $this->jailreaction( 'getresult' );
        if ($response === false) {
            throw new Exception( get_string( 'serverexecutionerror', VPL ) );
        }
        if ($response['interactive'] == 0) {
            $this->saveCE( $response );
            if ($response['executed'] > 0) {
                $data = new StdClass();
                $data->grade = $this->proposedGrade( $response['execution'] );
                $data->comments = $this->proposedComment( $response['execution'] );
            }
        }
        return $this->get_CE_for_editor( $response );
    }

    /**
     * Save Compilation Execution result to files
     *
     * @param $result array
     *            response from server
     * @return void
     */
    public function savece($result) {
        global $DB;
        ignore_user_abort( true );
        $oldce = $this->getce();
        // Count new evaluaions.
        $newevaluation = false;
        $manager = has_capability('moodle/course:update', $this->filerecord->context);

        if ( $oldce['executed'] == 0 && $result['executed'] > 0
            && ! $manager ) {
            $newevaluation = true;
        }
        // After first execution, keep execution state of the submission.
        if ( $oldce['executed'] > 0 && $result['executed'] == 0) {
            $result['executed'] = 1;
            $result['execution'] = '';
        }

        $compfn = $this->get_data_directory() . '/' . self::COMPILATIONFN;
        if (file_exists( $compfn )) {
            unlink( $compfn );
        }
        $execfn = $this->get_data_directory() . '/' . self::EXECUTIONFN;
        if (file_exists( $execfn )) {
            unlink( $execfn );
        }
        file_put_contents( $compfn, $result['compilation'] );
        if ($result['executed'] > 0) {
            file_put_contents( $execfn, $result['execution'] );
        }
    }

    public function get_ce_for_editor($response = null) {
        $ce = new stdClass();
        $ce->compilation = '';
        $ce->evaluation = '';
        $ce->execution = '';
        $ce->grade = '';
        $ce->nevaluations = 1/*TODO*/;

        $ce->freeevaluations = 0/*TODO*/;
        $ce->reductionbyevaluation = 0/*TODO*/;

        if ($response == null) {
            $response = $this->getce();
        }
        if ($response['compilation']) {
            $ce->compilation = $response['compilation'];
        }
        if ($response['executed'] > 0) {
            $rawexecution = $response['execution'];
            $evaluation = $this->proposedcomment( $rawexecution );
            $proposedgrade = $this->proposedgrade( $rawexecution );
            $ce->evaluation = $evaluation;
            if (strlen( $proposedgrade )) {
                $sgrade = $proposedgrade . '/' . $this->questiondata->defaultmark;
                $ce->grade = get_string( 'proposedgrade', 'vpl', $sgrade );
            }
            // Show raw ejecution if no grade or comments.
            $manager = has_capability('moodle/course:update', $this->filerecord->context);
            if ((strlen( $rawexecution ) > 0 && (strlen( $evaluation ) + strlen( $proposedgrade ) == 0)) || $manager) {
                $ce->execution = $rawexecution;
            }
        }
        return $ce;
    }

    /**
     * Get Compilation Execution information from files
     *
     * @return array with server response fields
     */
    public function getce() {
        $ret = array ();
        $compfn = $this->get_data_directory() . '/' . self::COMPILATIONFN;
        if (file_exists( $compfn )) {
            $ret['compilation'] = file_get_contents( $compfn );
        } else {
            $ret['compilation'] = 0;
        }
        $execfn = $this->get_data_directory() . '/' . self::EXECUTIONFN;
        if (file_exists( $execfn )) {
            $ret['executed'] = 1;
            $ret['execution'] = file_get_contents( $execfn );
        } else {
            $ret['executed'] = 0;
        }

        return $ret;
    }

    public function proposedcomment($text) {
        $incomment = false;
        $ret = '';
        $nl = vpl_detect_newline( $text );
        $lines = explode( $nl, $text );
        foreach ($lines as $line) {
            $line = rtrim( $line ); // Remove \r, spaces & tabs.
            $tline = trim( $line );
            if ($incomment) {
                if ($tline == self::ENDCOMMENTTAG) {
                    $incomment = false;
                } else {
                    $ret .= $line . "\n";
                }
            } else {
                if (strpos( $line, self::COMMENTTAG ) === 0) {
                    $ret .= substr( $line, strlen( self::COMMENTTAG ) ) . "\n";
                } else if ($tline == self::BEGINCOMMENTTAG) {
                    $incomment = true;
                }
            }
        }
        return $ret;
    }

    public function proposedgrade($text) {
        $ret = '';
        $nl = vpl_detect_newline( $text );
        $lines = explode( $nl, $text );
        foreach ($lines as $line) {
            if (strpos( $line, self::GRADETAG ) === 0) {
                $ret = trim( substr( $line, strlen( self::GRADETAG ) ) );
            }
        }
        return $ret;
    }

    /**
     * Get data directory path
     * @return string config data directory path
     */
    public function get_data_directory() {
        global $CFG;
        return make_writable_directory($CFG->dataroot . '/savpl_data/usersdata/' . $this->userid);
    }

    public function isrunning() {
        try {
            $response = $this->jailreaction( 'running' );
        } catch ( Exception $e ) {
            return false;
        }
        return $response['running'] > 0;
    }

    public function cancelprocess() {
        global $SESSION;

        if (!isset($SESSION->savplrunningprocesses[$this->userid][$this->question->questionid])) {
             // No process to cancel.
            return;
        }

        $processinfo = $SESSION->savplrunningprocesses[$this->userid][$this->question->questionid];
        try {
            $this->jailreaction( 'stop', $processinfo );
        } catch ( Exception $e ) {
            // No matter, consider that the process stopped.
            debugging( "Process in execution server not sttoped or not found", DEBUG_DEVELOPER );
        }
        unset($SESSION->savplrunningprocesses[$this->userid][$this->question->questionid]);
    }

    public static function get_scripttype() {
        return static::$scripttype;
    }

    /**
     * Translates files from IDE to internal format
     *
     * @param array $postfiles atributes encoding, name and contents
     * @return array contents indexed by filenames
     */
    public static function filesfromide($postfiles) {
        $files = Array ();
        foreach ($postfiles as $file) {
            $files[$file->name] = $file->contents;
        }
        return $files;
    }

    /**
     * Translates files from internal format to IDE format
     *
     * @param string[string] $from contents indexed by filenames
     * @return array of stdClass
     */
    public static function filestoide($from) {
        $files = Array ();
        foreach ($from as $name => $data) {
            $file = new stdClass();
            $file->name = $name;
            if ( vpl_is_binary($name, $data) ) {
                $file->encoding = 1;
            } else {
                $file->encoding = 0;
            }
            $file->contents = $data;
            $files[] = $file;
        }
        return $files;
    }

    /**
     * Translates files from internal format to IDE format
     *
     * @param string[string] $from contents indexed by filenames
     * @return array of stdClass
     */
    public static function filesdecode($files) {
        if (!empty($files)) {
            foreach ($files as $name => $data) {
                if (vpl_is_binary($name, $data)) {
                    $files[$name] = base64_decode($data);
                }
            }
        }

        return $files;
    }
}
