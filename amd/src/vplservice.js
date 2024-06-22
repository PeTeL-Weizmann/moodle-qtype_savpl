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
 * Provides utility method to communicate with a VPL (this is an API wrapper to use VPLUtil)
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// VPLUtil has to be loaded to use this module.
/* globals VPLUtil */
define(['jquery', 'core/url'], function($, url) {

    /**
     * Build ajax url to call with VPLUtil.
     * @param {String|Number} questionId QUESTION ID.
     * @param {String|Number} userId User ID.
     * @param {String} file (optional) Ajax file to use. Defaults to edit.
     * @return {String} The ajax url built.
     */
    function getAjaxUrl(questionId, userId, file) {
        if (file === undefined) {
            file = 'edit';
        }
        return url.relativeUrl('/question/type/savpl/ajax') + '/' + file + '.json.php?id=' + questionId + '&userId=' + userId + '&action=';
    }

    var VPLService = {};

    // Cache for info.
    var cache = {
        reqfile: [],
        execfiles: []
    };

    // Retrieve specified files from the VPL (either 'reqfile' or 'execfile').
    // Note : these files are stored in cache. To clear it, the user has to reload the page.
    VPLService.info = function(filesType, questionId) {
        if (cache[filesType][questionId] != undefined) {
            return $.Deferred().resolve(cache[filesType][questionId]).promise();
        } else {
            var deferred = filesType == 'reqfile' ?
                VPLUtil.requestAction('resetfiles', '', {}, getAjaxUrl(questionId, '')) :
                VPLUtil.requestAction('load', '', {}, getAjaxUrl(questionId, '', 'executionfiles'));
            return deferred
            .then(function(response) {
                var files = filesType == 'reqfile' ?
                    response.files[0] :
                    response.files;
                cache[filesType][questionId] = files;
                return files;
            }).promise();
        }
    };

    // Save student answer to VPL, by replacing {{ANSWER}} in the template by the student answer.
    VPLService.save = function(questionId, answer, filestype) {
        return $.ajax(url.relativeUrl('/question/type/savpl/ajax/save.json.php'), {
            data: {
                qid: questionId,
                answer: answer,
                filestype: filestype
            },
            method: 'POST'
        }).promise();
    };

    // Execute the specified action (should be 'run' or 'evaluate').
    // Note that this function does not call save, it has to be called beforehand if needed.
    // Note also that callback may be called several times
    // (especially one time with (false) execution error and one time right after with execution result).
    VPLService.exec = function(action, questionId, userId, terminal, callback) {
        // Build the options object for VPLUtil.
        var options = {
            ajaxurl: getAjaxUrl(questionId, userId),
            resultSet: false,
            setResult: function(result) {
                this.resultSet = true;
                callback(result);
            },
            close: function() {
                // If connection is closed without a result set, display an error.
                // /!\ It can happen that result will be set about 0.3s after closing.
                // -> Set a timeout to avoid half-second display of error.
                // Note : if delay between close and result is greater than timeout, it is fine
                // (there will just be a 0.1s error display before displaying the result).
                var _this = this;
                setTimeout(function() {
                    if (!_this.resultSet) {
                        callback({execerror: M.util.get_string('execerrordetails', 'qtype_savpl')});
                    }
                }, 600);
            },

            // The following will only be used for the 'run' action.
            getConsole: function() {
                return terminal;
            },
            run: function(type, conInfo, ws) {
                var _this = this;
                terminal.connect(conInfo.executionURL, function() {
                    ws.close();
                    if (!_this.resultSet) {
                        // This may happen for the run action.
                        callback({});
                    }
                });
            }
        };

        return VPLUtil.requestAction(action, '', {}, options.ajaxurl)
        .done(function(response) {
            VPLUtil.webSocketMonitor(response, '', '', options);
        }).promise();
    };

    return {
        call: function(service, ...args) {
            // Deactivate VPLUtil progress bar, as we have our own progress indicator.
            VPLUtil.progressBar = function() {
                this.setLabel = function() {
                    return;
                };
                this.close = function() {
                    return;
                };
                this.isClosed = function() {
                    return true;
                };
            };
            // Call service.
            return VPLService[service](...args);
        },

        langOfFile: function(fileName) {
            return VPLUtil.langType(fileName.split('.').pop());
        },

        isBinary: function(fileName) {
            return VPLUtil.isBinary(fileName);
        }
    };
});
