/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let ExtractMethodProvider;
const {Range} = require('atom');

const AbstractProvider = require('./AbstractProvider');

module.exports =

//#*
// Provides method extraction capabilities.
//#
(ExtractMethodProvider = (function() {
    ExtractMethodProvider = class ExtractMethodProvider extends AbstractProvider {
        static initClass() {
            /**
             * View that the user interacts with when extracting code.
             *
             * @type {Object}
            */
            this.prototype.view = null;

            /**
             * Builder used to generate the new method.
             *
             * @type {Object}
            */
            this.prototype.builder = null;
        }

        /**
         * @param {Object} builder
        */
        constructor(builder) {
            super();

            this.builder = builder;
        }

        /**
         * @inheritdoc
        */
        activate(service) {
            super.activate(service);

            return atom.commands.add('atom-text-editor', { 'php-ide-serenata:extract-method': () => {
                return this.executeCommand();
            }
            }
            );
        }

        /**
         * @inheritdoc
        */
        deactivate() {
            super.deactivate();

            if (this.view) {
                this.view.destroy();
                return this.view = null;
            }
        }

        /**
         * @inheritdoc
        */
        getIntentionProviders() {
            return [{
                grammarScopes: ['source.php'],
                getIntentions: ({textEditor, bufferPosition}) => {
                    const activeTextEditor = atom.workspace.getActiveTextEditor();

                    if (!activeTextEditor) { return []; }
                    if ((this.getCurrentProjectPhpVersion() == null)) { return []; }

                    const selection = activeTextEditor.getSelectedBufferRange();

                    // Checking if a selection has been made
                    if ((selection.start.row === selection.end.row) && (selection.start.column === selection.end.column)) {
                        return [];
                    }

                    return [
                        {
                            priority : 200,
                            icon     : 'git-branch',
                            title    : 'Extract Method',

                            selected : () => {
                                return this.executeCommand();
                            }
                        }
                    ];
                }
            }];
        }

        /**
         * Executes the extraction.
        */
        executeCommand() {
            const activeTextEditor = atom.workspace.getActiveTextEditor();

            if (!activeTextEditor) { return; }

            const tabText = activeTextEditor.getTabText();

            const selection = activeTextEditor.getSelectedBufferRange();

            // Checking if a selection has been made
            if ((selection.start.row === selection.end.row) && (selection.start.column === selection.end.column)) {
                atom.notifications.addInfo('Serenata', {
                    detail: 'Please select the code to extract and try again.'
                });

                return;
            }

            const line = activeTextEditor.lineTextForBufferRow(selection.start.row);

            const findSingleTab = new RegExp(`(${tabText})`, 'g');

            const matches = (line.match(findSingleTab) || []).length;

            // If the first line doesn't have any tabs then add one.
            let highlightedText = activeTextEditor.getTextInBufferRange(selection);
            const selectedBufferFirstLine = highlightedText.split('\n')[0];

            if ((selectedBufferFirstLine.match(findSingleTab) || []).length === 0) {
                highlightedText = `${tabText}` + highlightedText;
            }

            // Replacing double indents with one, so it can be shown in the preview area of panel.
            const multipleTabTexts = Array(matches).fill(`${tabText}`);
            const findMultipleTab = new RegExp(`^${multipleTabTexts.join('')}`, 'mg');
            const reducedHighlightedText = highlightedText.replace(findMultipleTab, `${tabText}`);

            this.builder.setEditor(activeTextEditor);
            this.builder.setMethodBody(reducedHighlightedText);

            this.getView().storeFocusedElement();
            return this.getView().present();
        }

        /**
         * Called when the user has cancel the extraction in the modal.
        */
        onCancel() {
            return this.builder.cleanUp();
        }

        /**
         * Called when the user has confirmed the extraction in the modal.
         *
         * @param  {Object} settings
         *
         * @see ParameterParser.buildMethod for structure of settings
        */
        onConfirm(settings) {
            const successHandler = methodCall => {
                const activeTextEditor = atom.workspace.getActiveTextEditor();

                const selectedBufferRange = activeTextEditor.getSelectedBufferRange();

                const highlightedBufferPosition = selectedBufferRange.end;

                let row = 0;

                while (true) {
                    row++;
                    const descriptions = activeTextEditor.scopeDescriptorForBufferPosition(
                        [highlightedBufferPosition.row + row, activeTextEditor.getTabLength()]
                    );
                    const indexOfDescriptor = descriptions.scopes.indexOf('punctuation.section.scope.end.php');
                    if ((indexOfDescriptor > -1) || (row === activeTextEditor.getLineCount())) { break; }
                }

                row = highlightedBufferPosition.row + row;

                const line = activeTextEditor.lineTextForBufferRow(row);

                const endOfLine = line != null ? line.length : undefined;

                let replaceRange = [
                    [row, 0],
                    [row, endOfLine]
                ];

                const previousText = activeTextEditor.getTextInBufferRange(replaceRange);

                settings.tabs = true;

                const nestedSuccessHandler = newMethodBody => {
                    settings.tabs = false;

                    this.builder.cleanUp();

                    return activeTextEditor.transact(() => {
                        // Matching current indentation
                        const selectedText = activeTextEditor.getSelectedText();
                        let spacing = selectedText.match(/^\s*/);
                        if (spacing !== null) {
                            spacing = spacing[0];
                        }

                        activeTextEditor.insertText(spacing + methodCall);

                        // Remove any extra new lines between functions
                        const nextLine = activeTextEditor.lineTextForBufferRow(row + 1);
                        if (nextLine === '') {
                            activeTextEditor.setSelectedBufferRange(
                                [
                                    [row + 1, 0],
                                    [row + 1, 1]
                                ]
                            );
                            activeTextEditor.deleteLine();
                        }


                        // Re working out range as inserting method call will delete some
                        // lines and thus offsetting this
                        row -= selectedBufferRange.end.row - selectedBufferRange.start.row;

                        if (this.snippetManager != null) {
                            activeTextEditor.setCursorBufferPosition([row + 1, 0]);

                            const body = `\n${newMethodBody}`;

                            const result = this.getTabStopsForBody(body);

                            const snippet = {
                                body,
                                lineCount: result.lineCount,
                                tabStopList: result.tabStops
                            };

                            snippet.tabStopList.toArray = () => {
                                return snippet.tabStopList;
                            };

                            return this.snippetManager.insertSnippet(
                                snippet,
                                activeTextEditor
                            );
                        } else {
                            // Re working out range as inserting method call will delete some
                            // lines and thus offsetting this
                            row -= selectedBufferRange.end.row - selectedBufferRange.start.row;

                            replaceRange = [
                                [row, 0],
                                [row, (line != null ? line.length : undefined)]
                            ];

                            return activeTextEditor.setTextInBufferRange(
                                replaceRange,
                                `${previousText}\n\n${newMethodBody}`
                            );
                        }
                    });
                };

                const nestedFailureHandler = () => {
                    return settings.tabs = false;
                };

                return this.builder.buildMethod(settings).then(nestedSuccessHandler, nestedFailureHandler);
            };

            const failureHandler = () => {};
            // Do nothing.

            return this.builder.buildMethodCall(settings.methodName).then(successHandler, failureHandler);
        }

        /**
         * Gets all the tab stops and line count for the body given
         *
         * @param  {String} body
         *
         * @return {Object}
        */
        getTabStopsForBody(body) {
            const lines = body.split('\n');
            let row = 0;
            let lineCount = 0;
            let tabStops = [];
            const tabStopIndex = {};

            for (const line of lines) {
                var match;
                const regex = /(\[[\w ]*?\])(\s*\$[a-zA-Z0-9_]+)?/g;
                // Get tab stops by looping through all matches
                while ((match = regex.exec(line)) !== null) {
                    let key = match[2]; // 2nd capturing group (variable name)
                    const replace = match[1]; // 1st capturing group ([type])
                    const range = new Range(
                        [row, match.index],
                        [row, match.index + match[1].length]
                    );

                    if (key !== undefined) {
                        key = key.trim();
                        if (tabStopIndex[key] !== undefined) {
                            tabStopIndex[key].push(range);
                        } else {
                            tabStopIndex[key] = [range];
                        }
                    } else {
                        tabStops.push([range]);
                    }
                }

                row++;
                lineCount++;
            }

            for (const objectKey of Object.keys(tabStopIndex)) {
                tabStops.push(tabStopIndex[objectKey]);
            }

            tabStops = tabStops.sort(this.sortTabStops);

            return {
                tabStops,
                lineCount
            };
        }

        /**
         * Sorts the tab stops by their row and column
         *
         * @param  {Array} a
         * @param  {Array} b
         *
         * @return {Integer}
        */
        sortTabStops(a, b) {
            // Grabbing first range in the array
            a = a[0];
            b = b[0];

            // b is before a in the rows
            if (a.start.row > b.start.row) {
                return 1;
            }

            // a is before b in the rows
            if (a.start.row < b.start.row) {
                return -1;
            }

            // On same line but b is before a
            if (a.start.column > b.start.column) {
                return 1;
            }

            // On same line but a is before b
            if (a.start.column < b.start.column) {
                return -1;
            }

            // Same position
            return 0;
        }

        /**
         * @return {View}
        */
        getView() {
            if ((this.view == null)) {
                const View = require('./ExtractMethodProvider/View');

                this.view = new View(this.onConfirm.bind(this), this.onCancel.bind(this));
                this.view.setBuilder(this.builder);
            }

            return this.view;
        }
    };
    ExtractMethodProvider.initClass();
    return ExtractMethodProvider;
})());
