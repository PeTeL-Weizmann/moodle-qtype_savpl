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
 * Defines the editing form for the savpl question type.
 * @package    qtype_savpl
 * @copyright  2023 Devlion.co <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../../config.php');
global $CFG;

require_once(__DIR__.'/locallib.php');
//require_once($CFG->dirroot . '/mod/vpl/forms/executionoptions.php');

require_login();

use \qtype_savpl\editor\vpl_editor_util;
/**
 * savpl editing form definition.
 * @copyright  2023 Devlion.co <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_savpl_edit_form extends question_edit_form {

    /**
     * Question type name.
     * @see question_edit_form::qtype()
     */
    public function qtype() {
        return 'savpl';
    }

    /**
     * Add our fields to the form.
     * @param MoodleQuickForm $mform The form being built.
     * @see question_edit_form::definition_inner()
     */
    protected function definition_inner($mform) {
        // Create form fields.
        $mform->addElement('advcheckbox', 'disablerun', null, get_string('disablerun', SAQVPL));
        $mform->setType('disablerun', PARAM_INT);

        $this->add_vpl_template_field($mform);
        $this->add_answer_template_field($mform);
        $this->add_teacher_correction_field($mform);
        $this->add_execfiles_field($mform);

        // Setup Ace editors and form behavior.
        global $PAGE, $OUTPUT, $CFG;
        $modvplcfg = get_config('mod_vpl');
        $acetheme = get_user_preferences('vpl_acetheme', isset($modvplcfg->editor_theme) ? $modvplcfg->editor_theme : 'chrome');
        $templatechangehelp = $OUTPUT->help_icon('templatevplchange', SAQVPL, get_string('help'));

        $plugin = new stdClass();
        require($CFG->dirroot . '/mod/vpl/version.php');
        $vplversion = $plugin->version;
        unset($plugin);

        $PAGE->requires->strings_for_js(array('merge', 'overwrite', 'templatevplchange', 'templatevplchangeprompt'), SAQVPL);
        $PAGE->requires->string_for_js('cancel', 'moodle');
        $PAGE->requires->js_call_amd(SAQVPL.'/editform', 'setup', array($acetheme, $templatechangehelp, $vplversion));
    }

    /**
     * Add a field for selecting the template VPL and editing the template.
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function add_vpl_template_field($mform) {
        $this->create_header($mform, 'qvplbase');

        global $COURSE, $CFG;

        $mform->addElement('hidden', 'templatelang');
        $mform->setType('templatelang', PARAM_RAW);

        $mform->addElement('text', 'templatefilename', get_string('templatefilename', 'qtype_savpl'));
        $mform->setType('templatefilename', PARAM_FILE);
        $mform->addRule('templatefilename', null, 'required', null, 'client');

        $mform->addElement('static', 'templatefilenamesave',
            get_string('templatefilenamesave_label', 'qtype_savpl'),
            get_string('templatefilenamesave_message', 'qtype_savpl'));

        $buttonarray = array();

        $buttonarray[] = $mform->createElement('submit', 'updatebutton',
            get_string('savechangesandcontinueediting', 'question'));
        $buttonarray[] = $mform->createElement('submit', 'saveinnewversionandcontinueediting',
            get_string('saveinnewversionandcontinueediting', 'local_petel'));

        $mform->addGroup($buttonarray, 'updatetemplatebuttonar', '', array(' '), false);
        $mform->closeHeaderBefore('updatetemplatebuttonar');

        $this->add_codeeditor($mform, 'templatecontext');
        $mform->setDefault('templatecontext', '{{ANSWER}}');

        /*
        $this->add_codeeditor($mform, 'vplrunsh');
        $this->add_codeeditor($mform, 'vpldebugsh');
        $this->add_codeeditor($mform, 'vplevaluatesh');
        $this->add_codeeditor($mform, 'vplevaluatecases');
        */
        if (isset($this->question->id) && !empty($this->question->id)) {
            $options = [];
            $options['restrictededitor'] = false;
            $options['save'] = true;
            $options['run'] = true;
            $options['debug'] = true;
            $options['evaluate'] = true;
            $options['ajaxurl'] = $CFG->wwwroot . "/question/type/savpl/executionfiles.json.php?id={$this->question->id}&courseid={$COURSE->id}&action=";
            $options['download'] = $CFG->wwwroot . "/question/type/savpl/downloadexecutionfiles.php?id={$this->question->id}";
            $options['resetfiles'] = false;
            $options['minfiles'] = 0;
            $options['maxfiles'] = 1000;
            $options['saved'] = true;
            $options['minfiles'] = 4; //TODO

            vpl_editor_util::generate_requires($options);

            $static = vpl_editor_util::print_tag();
            $static .= vpl_editor_util::print_js_i18n();

            $mform->addElement('static', 'executionfiles', get_string('executionfiles', 'qtype_savpl'), $static);
        }
        $strautodetect = get_string('autodetect', 'mod_vpl');
        $strrunscript = get_string('runscript', 'mod_vpl');
        $runlist = array_merge(array('' => $strautodetect), $this->get_runlist());
        $mform->addElement( 'select', 'runscript', $strrunscript, $runlist );
        $mform->addHelpButton('runscript', 'runscript', 'mod_vpl');

        $strdebugscript = get_string('debugscript', 'mod_vpl');
        $debuglist = array_merge(array('' => $strautodetect), $this->get_debuglist());
        $mform->addElement( 'select', 'debugscript', $strdebugscript, $debuglist );
        $mform->addHelpButton('debugscript', 'debugscript', 'mod_vpl');
    }

    /**
     * Add a field for the answer template.
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function add_answer_template_field($mform) {
        $this->create_header($mform, 'answertemplate');
        $this->add_codeeditor($mform, 'answertemplate');
    }

    /**
     * Add a field for a correction from the teacher (optional).
     * @param MoodleQuickForm $mform the form being built.
     * @copyright Inspired from Coderunner question type.
     */
    protected function add_teacher_correction_field($mform) {
        $this->create_header($mform, 'teachercorrection');
        $this->add_codeeditor($mform, 'teachercorrection');

        $mform->addElement('advcheckbox', 'validateonsave', null, get_string('validateonsave', SAQVPL));
        $mform->setDefault('validateonsave', false);
        $mform->addHelpButton('validateonsave', 'validateonsave', SAQVPL);
    }

    /**
     * Add a field for the execution files and grading options.
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function add_execfiles_field($mform) {
        global $CFG, $COURSE;
        $this->create_header($mform, 'execfilesevalsettings');

        //$this->add_fileset_editor($mform, 'execfiles', 'execfileslist', 'execfile');

        $mform->addElement('select', 'precheckpreference', get_string('precheckpreference', SAQVPL),
            array('none' => get_string('noprecheck', SAQVPL),
                'dbg' => get_string('precheckisdebug', SAQVPL),
                'same' => get_string('precheckhassamefiles', SAQVPL),
                'diff' => get_string('precheckhasownfiles', SAQVPL),
            ));
        $mform->setDefault('precheckpreference', 'same');
        $mform->addHelpButton('precheckpreference', 'precheckpreference', SAQVPL);

        if (isset($this->question->id) && !empty($this->question->id) && $this->question->options->precheckpreference == 'diff') {
            $options = [];
            $options['restrictededitor'] = false;
            $options['save'] = true;
            $options['run'] = true;
            $options['debug'] = true;
            $options['evaluate'] = true;
            $options['ajaxurl'] = $CFG->wwwroot . "/question/type/savpl/precheckfiles.json.php?id={$this->question->id}&courseid={$COURSE->id}&action=";
            $options['download'] = $CFG->wwwroot . "/question/type/savpl/downloadprecheckfiles.php?id={$this->question->id}";
            $options['resetfiles'] = false;
            $options['minfiles'] = 0;
            $options['maxfiles'] = 1000;
            $options['saved'] = true;

            vpl_editor_util::generate_requires($options);

            $static = vpl_editor_util::print_tag();
            $static .= vpl_editor_util::print_js_i18n();

            $mform->addElement('static', 'precheckexecfiles', get_string('precheckexecfiles', 'qtype_savpl'), $static);
        }

        $mform->addElement('select', 'gradingmethod',
            get_string('gradingmethod', SAQVPL),
            array(get_string('allornothing', SAQVPL), get_string('scaling', SAQVPL)));
        $mform->setDefault('gradingmethod', 1);
        $mform->addHelpButton('gradingmethod', 'gradingmethod', SAQVPL);
    }

    /**
     * Add an editor managing several files (with tabs).
     * @param MoodleQuickForm $mform the form being built.
     * @param string $name the name of the (hidden) field in which the files will be written.
     * @param string $listname the id of the file tabs element in DOM.
     * @param string $editorname the name of the editor.
     */
    private function add_fileset_editor($mform, $name, $listname, $editorname) {
        $mform->addElement('hidden', $name);
        $mform->setType($name, PARAM_RAW);
        $mform->addElement('static', $listname, get_string($name, SAQVPL),
            '<em class="novplmessage">'.get_string('selectavpl', SAQVPL, '#id_qvplbaseheader').'</em>
            <ul id="'.$listname.'" class="filelist inline-list"></ul>');
        $mform->addHelpButton($listname, $name, SAQVPL);

        $mform->addElement('textarea', $editorname, '', array('rows' => 1, 'class' => 'code-editor manylangs'));
    }

    /**
     * Add a code editor with an help button.
     * @param MoodleQuickForm $mform the form being built.
     * @param string $field the name of the editor.
     * @param array $attributes (optional) the attributes to add to the editor.
     */
    private function add_codeeditor($mform, $field, $attributes=null) {
        $mform->addElement('textarea', $field, get_string($field, SAQVPL),
            array('rows' => 1, 'class' => 'code-editor'));
        if ($attributes != null) {
            $mform->updateElementAttr($field, $attributes);
        }
        $mform->addHelpButton($field, $field, SAQVPL);
    }

    /**
     * Start a new form section with given name.
     * @param MoodleQuickForm $mform the form being built.
     * @param string $identifier the name of the section.
     */
    private function create_header($mform, $identifier) {
        $mform->addElement('header', $identifier.'header', get_string($identifier, SAQVPL));
        $mform->setExpanded($identifier.'header', true);
    }

    /**
     * Validate teacher correction against test cases.
     * @param array $submitteddata The data from the form.
     * @param array $files
     * @see question_edit_form::validation()
     */
    public function validation($submitteddata, $files) {
        require_sesskey();
        $errors = parent::validation($submitteddata, $files);

        if ($submitteddata['validateonsave']) {
            $question = new stdClass();
            foreach ($submitteddata as $key => $value) {
                $question->$key = $value;
            }

            try {
                $result = qtype_savpl_evaluate($submitteddata['teachercorrection'], $question, false);
                $vplres = $result->vplresult;
                $grade = qtype_savpl_extract_fraction($vplres, $question->defaultmark);
                if ($vplres->compilation) {
                    $errors['teachercorrection'] = '<pre style="color:inherit">'.htmlspecialchars($vplres->compilation).'</pre>';
                } else if ($grade !== null) {
                    if ($grade < 1.0) {
                        $errors['teachercorrection'] = '<pre style="color:inherit">'.htmlspecialchars($vplres->evaluation).'</pre>';
                    }
                } else {
                    if ($result->serverwassilent) {
                        $details = get_string('serverwassilent', SAQVPL);
                    } else {
                        $details = get_string('lastservermessage', SAQVPL, $result->lastmessage);
                    }
                    $errors['teachercorrection'] = get_string('nogradeerror', SAQVPL, $details);
                }
            } catch (Exception $e) {
                $errors['teachercorrection'] = get_string('nogradeerror', SAQVPL, $e->getMessage());
            }
        }

        return $errors;
    }

    protected function get_dirlist($dir, $endwith) {
        $avoid = array('default' => 1);
        $el = strlen($endwith);
        $dirlist = scandir($dir);
        $list = array();
        foreach ($dirlist as $file) {
            if ( substr($file, - $el) == $endwith) {
                $name = substr($file, 0, - $el);
                if ( ! isset( $avoid[$name] ) ) {
                    $list[$name] = strtoupper($name) . $this->get_scriptdescription($dir . '/' . $file);
                }
            }
        }
        return $list;
    }

    protected function get_runlist() {
        global $CFG;
        return $this->get_dirlist($CFG->dirroot . '/mod/vpl/jail/default_scripts', '_run.sh');
    }

    protected function get_debuglist() {
        global $CFG;
        return $this->get_dirlist($CFG->dirroot . '/mod/vpl/jail/default_scripts', '_debug.sh');
    }

    protected function get_scriptdescription($filename) {
        $data = file_get_contents($filename);
        if ($data === false ) {
            return '';
        }
        $matches = [];
        $result = preg_match('/@vpl_script_description (.*)$/im', $data, $matches);
        if ( $result ) {
            return ': ' . $matches[1];
        }
        return '';
    }

    /**
     * Return submitted data without validation or NULL if there is no submitted data.
     * note: $slashed param removed
     *
     * @return object submitted data; NULL if not submitted
     */
    function get_data() {
        $data = parent::get_data();
        if ($data !== NULL && (!isset($data->id) || empty($data->id))) {
            $scripttypes = \qtype_savpl\savpl_CE::get_scripttype();
            $data->execfiles = json_encode(array_fill_keys(array_keys($scripttypes), ''));
        }

        return $data;
    }
}
