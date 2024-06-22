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
 * Defines the behavior of the editing form for a savpl.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* globals ace */
define(['jquery', 'jqueryui', 'core/log', 'qtype_savpl/vplservice', 'qtype_savpl/codeeditors',
'qtype_savpl/scriptsloader'], function($, jqui, log, VPLService, CodeEditors, ScriptsLoader) {

    /**
     * Set editor content to a new content.
     * This is a hard reset (undo history is cleared).
     * @param {Editor} aceEditor Ace editor object to reset.
     * @param {String} newContent The new content to put in the editor.
     */
    function updateEditorContent(aceEditor, newContent) {
        aceEditor.getSession().setValue(newContent);
        aceEditor.getSession().setUndoManager(new ace.UndoManager());
    }

    /**
     * Update display of execution files editors.
     * This does not include file tabs nor content updating. This is only a visibility update on some elements.
     */
    function updateExecfilesVisibility() {
        var selectedVpl = $('#id_templatevpl').val() > '';
        var showPrecheckExecfiles = $('#id_precheckpreference').val() == 'diff';
        $('.novplmessage').toggle(!selectedVpl);
        $('#execfileslist, #fitem_id_execfile').toggle(selectedVpl);

        $('#fitem_id_precheckexecfileslist').toggle(showPrecheckExecfiles);
        $('#precheckexecfileslist, #fitem_id_precheckexecfile').toggle(selectedVpl && showPrecheckExecfiles);

        // Refresh previously hidden editor (which otherwise does not display correctly).
        if ($('#ace_placeholder_precheckexecfile').length) {
            ace.edit('ace_placeholder_precheckexecfile').resize();
        }
    }

    /**
     * Apply the current VPL template choice to form elements that depends on it (template content, execution files, ...).
     * @param {Boolean} keepContents Whether editors contents should be kept.
     *  This is typically false on initialization, true otherwise.
     */
    function applyTemplateChoice(keepContents) {
        var selectedVpl = $('#id_templatevpl').val();
        updateExecfilesVisibility();
        if (selectedVpl) {
            // Update template content.
            VPLService.call('info', 'reqfile', selectedVpl)
            .then(function(reqfile) {
                var lang = VPLService.langOfFile(reqfile.name);
                // Apply language change on editors.
                $('.code-editor:not(.manylangs) .ace-placeholder').each(function() {
                    ace.edit(this).getSession().setMode('ace/mode/' + lang);
                });

                // Store lang in hidden form element.
                $('[name=templatelang]').val(lang);

                if (!keepContents) {
                    // Choice changed (it is not initialization):
                    // Reinitialize template content to its original (VPL) state.
                    updateEditorContent(ace.edit('ace_placeholder_templatecontext'), reqfile.contents);
                }
            })
            .fail(function(message) { // The .catch method doesn't exist as it is a $.Deferred object (and not Promise).
                log.error(message, 'Error retrieving required files info for VPL ' + selectedVpl);
            });

            // Update execution files.
            VPLService.call('info', 'execfiles', selectedVpl)
            .then(function(execfiles) {
                // Filter new exec files to exclude standard scripts.
                execfiles = execfiles.filter(function(execfile) {
                    return !['vpl_run.sh', 'vpl_debug.sh', 'vpl_evaluate.sh', 'pre_vpl_run.sh'].includes(execfile.name);
                });

                setupExecfiles(execfiles, keepContents, '#execfileslist',
                        'ace_placeholder_execfile', $('[name=execfiles]'));
                setupExecfiles(execfiles, keepContents, '#precheckexecfileslist',
                        'ace_placeholder_precheckexecfile', $('[name=precheckexecfiles]'));
            })
            .fail(function(message) { // The .catch method doesn't exist as it is a $.Deferred object (and not Promise).
                log.error(message, 'Error retrieving execution files info for VPL ' + selectedVpl);
            });
        }
    }

    /**
     * Setup execution files editor and tabs to specified files.
     * @param {{name: String, contents: String}[]} execfiles Array of execution files.
     * @param {Boolean} keepContents Whether current contents should be kept, or overwritten.
     * @param {String} fileTabs Tabs selector for execution files.
     * @param {String} placeholder Placeholder id for editor.
     * @param {jQuery} $hiddenField Hidden form field in which files data is stored.
     */
    function setupExecfiles(execfiles, keepContents, fileTabs, placeholder, $hiddenField) {
        var aceEditor = ace.edit(placeholder);
        // Empty exec files list.
        var $fileTabs = $(fileTabs).html('');

        // Create exec files object to store in hidden form element, and create file tabs.
        var execfilesObj = {};
        var initialContents = $hiddenField.val().length > 0 ? JSON.parse($hiddenField.val()) : {};
        execfiles.forEach(function(execfile) {
            var content;
            if (VPLService.isBinary(execfile.name)) {
                // Binary file are not editable here.
                content = 'UNUSED';
            } else {
                content = (keepContents && initialContents[execfile.name]) || execfile.contents;
            }
            execfilesObj[execfile.name] = content;
            $fileTabs.append('<li class="execfilename float-left">' +
                                 '<span class="clickable rounded-top' + (content.startsWith('UNUSED') ? ' unused' : '') + '">' +
                                     execfile.name +
                                 '</span>' +
                             '</li>');
        });
        $hiddenField.val(JSON.stringify(execfilesObj));

        // Setup file tabs navigation.
        $(fileTabs + ' .execfilename span').click(function(event) {
            if (!$(this).is('.currentfile')) {
                updateExecfile($(fileTabs + ' .currentfile'), $(this), aceEditor, $hiddenField);
            }
            event.preventDefault();
        });

        // On form submit, write current editor value to the field that will be saved.
        $('input[type=submit]').click(function() {
            storeExecfile($(fileTabs + ' .currentfile').text(), aceEditor.getValue(), $hiddenField);
        });

        // Initialize/re-initialize editor.
        updateExecfile(null, $(fileTabs + ' .execfilename span').first(), aceEditor, $hiddenField);
    }

    /**
     * Store a file's content to hidden form element.
     * @param {String} fileName File name.
     * @param {String} fileContent File content.
     * @param {jQuery} $hiddenField Hidden form field in which files data is stored.
     */
    function storeExecfile(fileName, fileContent, $hiddenField) {
        var execfiles = JSON.parse($hiddenField.val());
        execfiles[fileName] = fileContent;
        $hiddenField.val(JSON.stringify(execfiles));
    }

    /**
     * Update Ace editor and tabs after user swapped to another file.
     * @param {jQuery} $prevFile The previously selected tab.
     * @param {jQuery} $newFile The new tab selected by the user.
     * @param {Editor} aceEditor Ace editor object.
     * @param {jQuery} $hiddenField Hidden form field in which files data is stored.
     */
    function updateExecfile($prevFile, $newFile, aceEditor, $hiddenField) {
        var prevFileContent = aceEditor.getValue();
        if ($prevFile !== null) {
            $prevFile.removeClass('currentfile');
            $prevFile.toggleClass('unused', prevFileContent.startsWith('UNUSED'));
            storeExecfile($prevFile.text(), prevFileContent, $hiddenField);
        }
        $newFile.addClass('currentfile');
        var newFile = $newFile.text();
        if (VPLService.isBinary(newFile)) {
            if ($('.qvpl-binary-file').length == 0) {
                $(aceEditor.container).parent().addClass('invisible');
                $('<pre>')
                .addClass('qvpl-binary-file visible bg-white text-center h-100 p-3 position-relative')
                .css('z-index', '1')
                .text(M.util.get_string('binaryfile', 'mod_vpl'))
                .appendTo($(aceEditor.container));
            }
        } else {
            $('.qvpl-binary-file').remove();
            $(aceEditor.container).parent().removeClass('invisible');
            aceEditor.getSession().setMode('ace/mode/' + VPLService.langOfFile(newFile));
        }
        updateEditorContent(aceEditor, JSON.parse($hiddenField.val())[newFile]);
    }

    /**
     * Setup behaviour for template VPL change, by displaying a dialog with Overwrite/Merge/Cancel options.
     * The dialog will only show up if there is data to merge/overwrite.
     * @param {String} helpButton HTML fragment for help button.
     */
    function setupTemplateChangeManager(helpButton) {
        var $templateSelect = $('#id_templatevpl');
        var templateChangeMessage = M.util.get_string('templatevplchangeprompt', 'qtype_savpl') + helpButton;
        var $templateChangeDialog = $('<div class="py-3">' + templateChangeMessage + '</div>').dialog({
            autoOpen: false,
            dialogClass: 'vplchangedialog p-3 bg-white',
            title: M.util.get_string('templatevplchange', 'qtype_savpl'),
            closeOnEscape: false,
            modal: true,
            buttons:
            [{
                text: M.util.get_string('overwrite', 'qtype_savpl'),
                'class': 'btn btn-primary mx-1',
                click: function() {
                    // Apply the change without merging.
                    $templateSelect.data('current', $templateSelect.val());
                    $(this).dialog('close');
                    applyTemplateChoice(false);
                }
            },
            {
                text: M.util.get_string('merge', 'qtype_savpl'),
                'class': 'btn btn-primary mx-1',
                click: function() {
                    // Apply the change with merging.
                    $templateSelect.data('current', $templateSelect.val());
                    $(this).dialog('close');
                    applyTemplateChoice(true);
                }
            },
            {
                text: M.util.get_string('cancel', 'moodle'),
                'class': 'btn btn-secondary mx-1',
                click: function() {
                    // Undo select change.
                    $templateSelect.val($templateSelect.data('current'));
                    $(this).dialog('close');
                }
            }],
            open: function() {
                // By default, focus is on help button - make it less aggressive by focusing on the dialog.
                $('.vplchangedialog').focus();
            }
        });
        $templateSelect.focus(function() {
            // Save the previous value to manage cancel.
            $(this).data('current', $(this).val());
        }).change(function() {
            if ($templateSelect.val() && $('[name=execfiles]').val() != '') {
                // There is data to merge/overwrite, open a dialog to prompt the user.
                $templateChangeDialog.dialog('open');
            } else {
                // There is nothing to merge/overwrite, simply apply the change.
                $templateSelect.data('current', $templateSelect.val());
                applyTemplateChoice(false);
            }
        });
    }

    return {
        setup: function(aceTheme, templateChangeHelpButton, vplVersion) {
            // Setup all form editors.
            CodeEditors.setupFormEditors(aceTheme, function() {
                ScriptsLoader.loadVPLUtil(vplVersion, function() {
                    // Setup form behavior (VPL template, execution files, etc).
                    applyTemplateChoice(true);
                    // Manage VPL template change.
                    setupTemplateChangeManager(templateChangeHelpButton);
                    $('#id_precheckpreference').change(updateExecfilesVisibility);
                });
            });
        }
    };
});