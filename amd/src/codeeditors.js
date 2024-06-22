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
 * Provides utility methods to setup resizable ace editors into a page.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* globals ace */
define(['jquery', 'core/url'], function($, url) {

    // Global Ace editor theme to use for all editors.
    var aceTheme;

    /**
     * Setup each specified textarea with Ace editor, with a vertical resize feature.
     * It inherits readonly attribute from textarea.
     * @param {jQuery} $textareas JQuery set of textareas from which to set up editors.
     * @param {String} aceSize Initial CSS size of editors.
     * @param {String} aceLang (optional) Lang (mode) to setup editors from.
     * @return {Editor} The last editor set up.
     */
    function setupAceEditors($textareas, aceSize, aceLang) {
        var aceEditor;

        // Vertical resizing.
        var prevY;
        var $placeholderBeingResized = null;

        if (aceLang === undefined) {
            aceLang = 'plain_text';
        }

        $textareas.each(function() {
            var $textarea = $(this);
            var $editorPlaceholder = $('<div>', {
                width: '100%',
                height: aceSize,
                'id': 'ace_placeholder_' + $textarea.attr('name'),
                'class': 'ace-placeholder'
            }).insertAfter($textarea);
            $textarea.hide();

            $('<div>', {
                'id': 'ace_resize_' + $textarea.attr('name'),
                'class': 'ace-resize'
            }).insertAfter($editorPlaceholder)
            .mousedown(function(event) {
                prevY = event.clientY;
                $placeholderBeingResized = $editorPlaceholder;
                event.preventDefault();
            });

            ace.config.set('modePath', url.relativeUrl("/mod/vpl/editor/ace9"));
            ace.config.set('themePath', url.relativeUrl("/mod/vpl/editor/ace9"));

            // This is what creates the Ace editor within the placeholder div.
            aceEditor = ace.edit($editorPlaceholder[0]);
            aceEditor.setOptions({
                theme: 'ace/theme/' + aceTheme,
                mode: 'ace/mode/' + aceLang
            });
            aceEditor.$blockScrolling = Infinity; // Disable ace warning.
            aceEditor.getSession().setValue($textarea.val());
            aceEditor.setReadOnly($textarea.is('[readonly]'));

            // On submit or run/check, propagate the changes to textarea.
            $('input[type=submit], .qvpl-buttons button').click(function() {
                // Cannot use aceEditor here, as it will have another value later.
                $textarea.val(ace.edit('ace_placeholder_' + $textarea.attr('name')).getValue());
            });
        });

        $(window).mousemove(function(event) {
            if ($placeholderBeingResized) {
                $placeholderBeingResized.height(function(i, height) {
                    return height + event.clientY - prevY;
                });
                prevY = event.clientY;
                ace.edit($placeholderBeingResized[0]).resize();
                event.preventDefault();
            }
        }).mouseup(function() {
            $placeholderBeingResized = null;
        });

        return aceEditor;
    }

    /**
     * Loads Ace script from VPL plugin.
     * @return {Promise} A promise that resolves upon load.
     */
    function loadAce() {
        if (typeof ace != 'undefined') {
            return $.Deferred().resolve();
        }
        var ACESCRIPTLOCATION = url.relativeUrl("/mod/vpl/editor/ace9");
        return $.ajax({
            url: ACESCRIPTLOCATION + '/ace.js',
            dataType: 'script',
            cache: true,
            success: function() {
                ace.config.set('basePath', ACESCRIPTLOCATION);
            }
        });
    }

    return {
        // Setup editors in question edition form.
        setupFormEditors: function(theme, callback) {
            aceTheme = theme;
            loadAce().done(function() {
                setupAceEditors($('.code-editor textarea'), '170px');
                callback();
            });
        },

        // Setup editor in answer form.
        setupQuestionEditor: function(theme, $textarea, $setTextButtons, lineOffset, callback) {
            aceTheme = theme;
            loadAce().done(function() {
                console.log('TEXTAREA');
                console.log($textarea);
                // Setup question editor.
                var aceEditor = setupAceEditors($textarea, '200px', $textarea.data('templatelang'));
                // Set first line number to match compilation messages.
                aceEditor.setOption('firstLineNumber', lineOffset);
                // Setup reset and correction buttons (if present, ie. not review mode).
                $setTextButtons.each(function() {
                    var text = $(this).data('text');
                    $(this).removeAttr('data-text');
                    $(this).click(function(event) {
                        if (aceEditor.getValue() != text) {
                            aceEditor.setValue(text);
                        }
                        event.preventDefault();
                    });
                });
                callback();
            });
        }
    };
});