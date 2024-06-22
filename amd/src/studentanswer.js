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
 * Defines the behavior of the student's answer form for a savpl.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* globals Terminal */
/* globals VPLTerminal */
define(['jquery', 'qtype_savpl/codeeditors',
'qtype_savpl/vplservice', 'qtype_savpl/scriptsloader'], function($, CodeEditors, VPLService, ScriptsLoader) {

    /**
     * For VPLTerminal constructor - determines what to display on titlebar.
     * In our case, we just want to display if process is running or exited.
     * @param {String} key Key to map.
     * @return {String} Mapped string.
     */
    function str(key) {
        switch (key) {
            case 'console': return '[Process';
            case 'connected':
            case 'connecting':
            case 'running': return 'running]';
            case 'connection_closed': return 'exited]';
            default: return key;
        }
    }

    /**
     * Escape special html characters in a text.
     * @param {String} text HTML text to escape.
     * @return {String} Escaped text.
     */
    function escapeHtml(text) {
        var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return text.replace(/[&<>"']/g, function(c) {
            return map[c];
        });
    }

    /**
     * Build an html string to display the specified field of the result,
     * formatting titles (field name) and subtitles (lines starting with '-').
     * @param {Object} result Evaluation/execution result object.
     * @param {String} field Field of result to display.
     * @param {String} level CSS class fragment for error level.
     * @return {String} Formatted result as HTML fragment.
     */
    function makeResultHtml(result, field, level) {
        if (result[field]) {
            var formattedText = '';
            escapeHtml(result[field]).split('\n').forEach(function(line) {
                if (line.charAt(0) == '-') {
                    line = '<span class="vpl-test-title font-italic rounded px-1">' + line + '</span>';
                }
                formattedText += line + '\n';
            });
            return '<span class="vpl-result-title vpl-title-' + level + ' d-block font-weight-bold border border-dark pl-1 mb-1">' +
                        M.util.get_string(field, 'qtype_savpl') +
                    '</span>' +
                    formattedText.trim();
        }
        return '';
    }

    /**
     * Display result on screen in specified display.
     * If result is null, this method will try to get it from data-result attribute.
     * @param {String} displayId ID of DOM element in which result should be displayed.
     * @param {?Object} result Evaluation/execution result object, or null.
     */
    function displayResult(displayId, result) {
        var $display = $('#' + displayId);
        if (result === null) {
            // This method parses the JSON by itself - no need to parse it.
            result = $display.data('result');
        }
        var html = makeResultHtml(result, 'compilation', 'error')
            + makeResultHtml(result, 'evaluation', 'info')
            + makeResultHtml(result, 'execerror', 'error')
            + makeResultHtml(result, 'evaluationerror', 'error');
        if (!html) {
            html = makeResultHtml(result, 'execution', 'error');
        }
        $display[html ? 'show' : 'hide']();
        $display.html(html);
    }

    /**
     * Set up student answer box (ace editor, terminal and reset/correction, run and pre-check buttons).
     * @param {String|Number} questionId Question ID, used for DOM identifiers.
     * @param {String|Number} vplId VPL ID, used for ajax calls.
     * @param {String|Number} userId User ID, used for ajax calls.
     * @param {String} aceTheme Theme to set ace editors to (typically user's selected theme on VPL plugin).
     * @param {String} textareaName HTML name attribute of textarea used for student answer.
     * @param {Number} vplVersion Version number of the VPL plugin (used to decide how to load scripts as they moved in v3.4.0).
     */
    function setup(questionId, userId, aceTheme, textareaName, vplVersion) {
        // This is the textarea that will recieve student's answer.
        var $textarea = $('textarea[name="' + textareaName + '"]');
        console.log('TEXTAREANAME');
        console.log(textareaName);
        var $resetAndCorrectionButtons = $('#qvpl_reset_q' + questionId + ', #qvpl_correction_q' + questionId);

        // Setup ace editor THEN buttons (so Run and Check correctly take current ace text).
        CodeEditors.setupQuestionEditor(aceTheme, $textarea, $resetAndCorrectionButtons, $textarea.data('lineoffset'), function() {

            if ($textarea.attr('readonly') == 'readonly') {
                // We are in review (readonly) mode - do nothing more.
                return;
            }

            ScriptsLoader.loadVPLTerminal(vplVersion, function() {
                // Setup terminal to fix number of rows (default is too much to fit within a quiz).
                var oldPrototype = Terminal.prototype;
                Terminal = function(data) { // eslint-disable-line no-global-assign
                    data.rows = 10;
                    return new oldPrototype.constructor(data);
                };
                Terminal.prototype = oldPrototype;

                // Initialize the terminal on the wrapper.
                var wrapperId = 'terminal_wrapper_q' + questionId;
                var terminal = new VPLTerminal(wrapperId, wrapperId, str);
                $('#' + wrapperId).dialog('option', 'draggable', false);

                // Deactivate message function (it normally displays a ticking timer, which is annoying).
                terminal.setMessage = function() {
                    return;
                };

                // Move the terminal to a nice place within the question box.
                var qvplButtons = '#qvpl_buttons_q' + questionId;
                var $globalTerminalWrapper = $('#' + wrapperId).parent();
                $globalTerminalWrapper.insertAfter(qvplButtons);

                // Override connect function, that indirectly sets the terminal to be displayed somewhere else.
                var oldConnect = terminal.connect;
                terminal.connect = function() {
                    oldConnect.apply(terminal, arguments);
                    $globalTerminalWrapper.css('top', 0).css('left', 0);
                    $('body > .ui-widget-overlay.ui-front').first().remove(); // Remove the modal lock overlay.
                };

                // Change close button style to match the general question style.
                $globalTerminalWrapper.find('.ui-dialog-titlebar-close')
                .html('<i class="fa fa-close"></i>')
                .addClass('btn btn-secondary close-terminal');

                // Setup a VPL button (run, debug, or evaluate).
                var setupButton = function(action, icon, filestype) {
                    var $button = $(qvplButtons + ' button[data-action="' + action + '"]');
                    var $icon = $('<i class="fa fa-' + icon + '"></i>');
                    $('<span class="ml-2">').append($icon).appendTo($button);
                    var reenableButtons = function() {
                        $icon.attr('class', 'fa fa-' + icon);
                        $('.qvpl-buttons *').removeAttr('disabled');
                    };

                    $button.click(function() {
                        $('.qvpl-buttons *').attr('disabled', 'disabled');
                        $('.close-terminal').trigger('click');
                        $icon.attr('class', 'fa fa-refresh fa-spin');
                        // We got nested callbacks, but we can't promisify them,
                        // as callback may be called several times depending on the underlying websocket messages order.
                        VPLService.call('save', questionId, $textarea.val(), filestype)
                        .then(function() {
                            return VPLService.call('exec', action, questionId, userId, terminal, function(result) {
                                displayResult('vpl_result_q' + questionId, result);
                                reenableButtons();
                            });
                        })
                        .fail(function(details) {
                            displayResult('vpl_result_q' + questionId, {execerror: details});
                            reenableButtons();
                        });
                    });
                };

                setupButton('run', 'rocket', 'run');
                setupButton('debug', 'check-square-o', 'precheck');
                setupButton('evaluate', 'check-square-o', 'precheck');
            });
        });
    }

    return {
        setup: setup,
        displayResult: displayResult
    };
});
