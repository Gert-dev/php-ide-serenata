/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS205: Consider reworking code to avoid use of IIFEs
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let AbstractProvider;
const {CompositeDisposable} = require('atom');

module.exports =

//#*
// Base class for providers.
//#
(AbstractProvider = (function() {
    AbstractProvider = class AbstractProvider {
        static initClass() {
            /**
             * The class selectors for which autocompletion triggers.
             *
             * @var {String}
            */
            this.prototype.scopeSelector = '.source.php';

            /**
             * The inclusion priority of the provider.
             *
             * @var {Number}
            */
            this.prototype.inclusionPriority = 1;

            /**
             * Whether to let autocomplete-plus handle the actual filtering, that way we don't need to manually filter (e.g.
             * using fuzzaldrin) ourselves and the user can configure filtering settings on the base package.
             *
             * Set to false as the core does the filtering to avoid sending a large amount of suggestions back over the socket.
             *
             * @var {Boolean}
            */
            this.prototype.filterSuggestions = false;

            /**
             * The class selectors autocompletion is explicitly disabled for (overrules the {@see scopeSelector}).
             *
             * @var {String}
            */
            this.prototype.disableForScopeSelector = null;

            /**
             * Whether to exclude providers with a lower priority.
             *
             * This ensures the default, built-in suggestions from the language-php package do not show up.
             *
             * @var {Boolean}
            */
            this.prototype.excludeLowerPriority = true;

            /**
             * The service (that can be used to query the source code and contains utility methods).
             *
             * @var {Object}
            */
            this.prototype.service = null;

            /**
             * @var {CompositeDisposable}
            */
            this.prototype.disposables = null;

            /**
             * @var {CancellablePromise}
            */
            this.prototype.pendingRequestPromise = null;
        }

        /**
         * Initializes this provider.
         *
         * @param {mixed} service
        */
        activate(service) {
            this.service = service;
            const dependentPackage = 'language-php';

            this.disposables = new CompositeDisposable();

            // It could be that the dependent package is already active, in that case we can continue immediately. If not,
            // we'll need to wait for the listener to be invoked
            if (atom.packages.isPackageActive(dependentPackage)) {
                this.doActualInitialization();
            }

            this.disposables.add(atom.packages.onDidActivatePackage(packageData => {
                if (packageData.name !== dependentPackage) { return; }

                return this.doActualInitialization();
            })
            );

            return this.disposables.add(atom.packages.onDidDeactivatePackage(packageData => {
                if (packageData.name !== dependentPackage) { return; }

                return this.deactivate();
            })
            );
        }

        /**
         * Does the actual initialization.
        */
        doActualInitialization() {
            this.disposables.add(atom.workspace.observeTextEditors(editor => {
                if (/text.html.php$/.test(editor.getGrammar().scopeName)) {
                    return this.registerEvents(editor);
                }
            })
            );

            // When you go back to only have one pane the events are lost, so need to re-register.
            this.disposables.add(atom.workspace.onDidDestroyPane(pane => {
                const panes = atom.workspace.getPanes();

                if (panes.length === 1) {
                    return this.registerEventsForPane(panes[0]);
                }
            })
            );

            // Having to re-register events as when a new pane is created the old panes lose the events.
            this.disposables.add(atom.workspace.onDidAddPane(observedPane => {
                const panes = atom.workspace.getPanes();

                return (() => {
                    const result = [];
                    for (const pane of panes) {
                        if (pane !== observedPane) {
                            result.push(this.registerEventsForPane(pane));
                        } else {
                            result.push(undefined);
                        }
                    }
                    return result;
                })();
            })
            );

            this.disposables.add(atom.workspace.onDidStopChangingActivePaneItem(item => {
                return this.stopPendingRequests();
            })
            );

            return this.disposables.add(atom.workspace.onDidChangeActiveTextEditor(item => {
                return this.stopPendingRequests();
            })
            );
        }

        /**
         * Registers the necessary event handlers for the editors in the specified pane.
         *
         * @param {Pane} pane
        */
        registerEventsForPane(pane) {
            return (() => {
                const result = [];
                for (const paneItem of pane.items) {
                    if (atom.workspace.isTextEditor(paneItem)) {
                        if (/text.html.php$/.test(paneItem.getGrammar().scopeName)) {
                            result.push(this.registerEvents(paneItem));
                        } else {
                            result.push(undefined);
                        }
                    } else {
                        result.push(undefined);
                    }
                }
                return result;
            })();
        }

        /**
         * Registers the necessary event handlers.
         *
         * @param {TextEditor} editor TextEditor to register events to.
        */
        registerEvents(editor) {
            return this.disposables.add(editor.onDidChangeCursorPosition(event => {
                // Don't trigger whilst actually typing, just when moving.
                if (event.textChanged === true) { return; }

                return this.onChangeCursorPosition(editor);
            })
            );
        }

        /**
         * @param {TextEditor} editor
        */
        onChangeCursorPosition(editor) {
            return this.stopPendingRequests();
        }

        /**
         * Deactives the provider.
        */
        deactivate() {
            this.disposables.dispose();

            return this.stopPendingRequests();
        }

        /**
         * Entry point for all requests from autocomplete-plus.
         *
         * @param {TextEditor} editor
         * @param {Point}      bufferPosition
         * @param {String}     scopeDescriptor
         * @param {String}     prefix
         *
         * @return {Promise|Array}
        */
        getSuggestions({editor, bufferPosition, scopeDescriptor, prefix}) {
            this.stopPendingRequests();

            if (!this.service) { return []; }

            const successHandler = suggestions => {
                return suggestions.map(suggestion => {
                    return this.getAdaptedSuggestion(suggestion);
                });
            };

            const failureHandler = () => {
                return []; // Just return no suggestions.
            };

            this.pendingRequestPromise = this.service.autocompleteAt(editor, bufferPosition);

            return this.pendingRequestPromise.then(successHandler, failureHandler);
        }

        /**
         * @param {Object} suggestion
         *
         * @return {Array}
        */
        getAdaptedSuggestion(suggestion) {
            const adaptedSuggestion = {
                text               : suggestion.filterText,
                snippet            : suggestion.insertText.replace(/\\/g, '\\\\'),
                type               : suggestion.kind,
                displayText        : suggestion.label,
                leftLabelHTML      : this.getSuggestionLeftLabel(suggestion),
                rightLabelHTML     : this.getSuggestionRightLabel(suggestion),
                description        : suggestion.documentation,
                className          : `php-ide-serenata-autocompletion-suggestion${suggestion.isDeprecated ? ' php-ide-serenata-autocompletion-strike' : ''}`,

                extraData: {
                    additionalTextEdits: suggestion.additionalTextEdits
                }
            };

            // TODO: Better would be to support the textEdit property sent brck by the core's suggestions via
            // onDidInsertSuggestion.
            if ((suggestion.extraData != null ? suggestion.extraData.prefix : undefined) != null) {
                adaptedSuggestion.replacementPrefix = suggestion.extraData.prefix;
            }

            return adaptedSuggestion;
        }

        /**
         * Builds the right label for a PHP function or method.
         *
         * @param {Object} suggestion Information about the function or method.
         *
         * @return {String}
        */
        getSuggestionLeftLabel(suggestion) {
            let leftLabel = '';

            if ((suggestion.extraData != null ? suggestion.extraData.protectionLevel : undefined) === 'public') {
                leftLabel += '<span class="icon icon-globe import">&nbsp;</span>';
            } else if ((suggestion.extraData != null ? suggestion.extraData.protectionLevel : undefined) === 'protected') {
                leftLabel += '<span class="icon icon-shield">&nbsp;</span>';
            } else if ((suggestion.extraData != null ? suggestion.extraData.protectionLevel : undefined) === 'private') {
                leftLabel += '<span class="icon icon-lock selector">&nbsp;</span>';
            }

            if ((suggestion.extraData != null ? suggestion.extraData.returnTypes : undefined) != null) {
                leftLabel += this.getTypeSpecificationFromTypeArray(suggestion.extraData.returnTypes.split('|'));
            }

            return leftLabel;
        }

        /**
         * Builds the right label for a PHP function or method.
         *
         * @param {Object} suggestion Information about the function or method.
         *
         * @return {String}
        */
        getSuggestionRightLabel(suggestion) {
            if (((suggestion.extraData != null ? suggestion.extraData.declaringStructure : undefined) == null)) { return null; }

            // Determine the short name of the location where this item is defined.
            const declaringStructureShortName = '';

            if (suggestion.extraData.declaringStructure && suggestion.extraData.declaringStructure.fqcn) {
                return this.getClassShortName(suggestion.extraData.declaringStructure.fqcn);
            }

            return declaringStructureShortName;
        }

        /**
         * @param {Array} typeArray
         *
         * @return {String}
        */
        getTypeSpecificationFromTypeArray(typeArray) {
            const typeNames = typeArray.map(type => {
                return this.getClassShortName(type);
            });

            return typeNames.join('|');
        }

        /**
         * Retrieves the short name for the specified class name (i.e. the last segment, without the class namespace).
         *
         * @param {String} className
         *
         * @return {String}
        */
        getClassShortName(className) {
            if (!className) { return null; }

            const parts = className.split('\\');
            return parts.pop();
        }

        /**
         * Called when the user confirms an autocompletion suggestion.
         *
         * @param {TextEditor} editor
         * @param {Position}   triggerPosition
         * @param {Object}     suggestion
        */
        onDidInsertSuggestion({editor, triggerPosition, suggestion}) {
            if (!((suggestion.extraData.additionalTextEdits != null ? suggestion.extraData.additionalTextEdits.length : undefined) > 0)) { return; }

            return editor.transact(() => {
                return suggestion.extraData.additionalTextEdits.map((additionalTextEdit) => {
                    editor.setTextInBufferRange(
                        [
                            [additionalTextEdit.range.start.line, additionalTextEdit.range.start.character],
                            [additionalTextEdit.range.end.line, additionalTextEdit.range.end.character]
                        ],
                        additionalTextEdit.newText
                    );
                });
            });
        }

        /**
         * Stops any pending requests.
        */
        stopPendingRequests() {
            if (this.pendingRequestPromise != null) {
                this.pendingRequestPromise.cancel();
                return this.pendingRequestPromise = null;
            }
        }
    };
    AbstractProvider.initClass();
    return AbstractProvider;
})());
