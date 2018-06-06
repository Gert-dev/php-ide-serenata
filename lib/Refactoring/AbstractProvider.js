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
module.exports =

//#*
// Base class for providers.
//#
(AbstractProvider = (function() {
    AbstractProvider = class AbstractProvider {
        static initClass() {
            /**
             * The service (that can be used to query the source code and contains utility methods).
            */
            this.prototype.service = null;

            /**
             * Service to insert snippets into the editor.
            */
            this.prototype.snippetManager = null;
        }

        /**
         * Constructor.
        */
        constructor() {}

        /**
         * Initializes this provider.
         *
         * @param {mixed} service
        */
        activate(service) {
            this.service = service;
            const dependentPackage = 'language-php';

            // It could be that the dependent package is already active, in that case we can continue immediately. If not,
            // we'll need to wait for the listener to be invoked
            if (atom.packages.isPackageActive(dependentPackage)) {
                this.doActualInitialization();
            }

            atom.packages.onDidActivatePackage(packageData => {
                if (packageData.name !== dependentPackage) { return; }

                return this.doActualInitialization();
            });

            return atom.packages.onDidDeactivatePackage(packageData => {
                if (packageData.name !== dependentPackage) { return; }

                return this.deactivate();
            });
        }

        /**
         * Does the actual initialization.
        */
        doActualInitialization() {
            atom.workspace.observeTextEditors(editor => {
                if (/text.html.php$/.test(editor.getGrammar().scopeName)) {
                    return this.registerEvents(editor);
                }
            });

            // When you go back to only have one pane the events are lost, so need to re-register.
            atom.workspace.onDidDestroyPane(pane => {
                const panes = atom.workspace.getPanes();

                if (panes.length === 1) {
                    return this.registerEventsForPane(panes[0]);
                }
            });

            // Having to re-register events as when a new pane is created the old panes lose the events.
            return atom.workspace.onDidAddPane(observedPane => {
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
            });
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
         * Deactives the provider.
        */
        deactivate() {}

        /**
         * Retrieves intention providers (by default, the intentions menu shows when the user presses alt-enter).
         *
         * This method should be overwritten by subclasses.
         *
         * @return {array}
        */
        getIntentionProviders() {
            return [];
        }

        /**
         * Registers the necessary event handlers.
         *
         * @param {TextEditor} editor TextEditor to register events to.
        */
        registerEvents(editor) {}

        /**
         * Sets the snippet manager
         *
         * @param {Object} @snippetManager
        */
        setSnippetManager(snippetManager) {
            this.snippetManager = snippetManager;
        }

        /**
         * @return {Number|null}
        */
        getCurrentProjectPhpVersion() {
            const projectSettings = this.service.getCurrentProjectSettings();

            if (projectSettings != null) {
                return projectSettings.phpVersion;
            }

            return null;
        }
    };
    AbstractProvider.initClass();
    return AbstractProvider;
})());
