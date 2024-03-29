/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
module.exports =

/**
 * Base class for providers.
 */
class AbstractProvider {
    /**
     * Constructor.
    */
    constructor() {
        /**
         * The service (that can be used to query the source code and contains utility methods).
        */
        this.service = null;

        /**
         * Service to insert snippets into the editor.
        */
        this.snippetManager = null;
    }

    /**
     * Initializes this provider.
     *
     * @param {mixed} service
    */
    activate(service) {
        this.service = service;
        const dependentPackage = 'language-php';

        // It could be that the dependent package is already active, in that case we can continue immediately.
        // If not, we'll need to wait for the listener to be invoked
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
        atom.workspace.onDidDestroyPane(() => {
            const panes = atom.workspace.getPanes();

            if (panes.length === 1) {
                return this.registerEventsForPane(panes[0]);
            }
        });

        // Having to re-register events as when a new pane is created the old panes lose the events.
        return atom.workspace.onDidAddPane(observedPane => {
            const panes = atom.workspace.getPanes();

            const result = [];

            for (const pane of panes) {
                if (pane !== observedPane) {
                    result.push(this.registerEventsForPane(pane));
                } else {
                    result.push(undefined);
                }
            }

            return result;
        });
    }

    /**
     * Registers the necessary event handlers for the editors in the specified pane.
     *
     * @param {Pane} pane
    */
    registerEventsForPane(pane) {
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
     * @param {TextEditor} _ TextEditor to register events to.
    */
    // eslint-disable-next-line no-unused-vars
    registerEvents(_) {}

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

    /**
     * @param {Array}    functionParameters
     * @param {String}   editorUri
     * @param {Position} bufferPosition
     *
     * @return {Array}
     */
    async localizeFunctionParameterTypeHints(functionParameters, editorUri, bufferPosition) {
        await Promise.all(functionParameters.map(async (parameter) => {
            if (!parameter.typeHint) {
                return parameter.typeHint;
            }

            parameter.typeHint = await this.service.localizeType(
                editorUri,
                bufferPosition,
                parameter.typeHint,
                'classlike'
            );

            return parameter;
        }));
    }

    /**
     * @param {Array}    functionParameters
     * @param {String}   editorUri
     * @param {Position} bufferPosition
     *
     * @return {Array}
     */
    async localizeFunctionParametersTypes(functionParameters, editorUri, bufferPosition) {
        await Promise.all(functionParameters.map(async (parameter) => {
            await this.localizeFunctionParameterTypes(parameter, editorUri, bufferPosition);
        }));
    }

    /**
     * @param {Object}   functionParameter
     * @param {String}   editorUri
     * @param {Position} bufferPosition
     *
     * @return {Array}
     */
    async localizeFunctionParameterTypes(functionParameter, editorUri, bufferPosition, property = 'types') {
        await Promise.all(functionParameter[property].map(async (type) => {
            if (!type.type) {
                return type.type;
            }

            type.type = await this.service.localizeType(
                editorUri,
                bufferPosition,
                type.type,
                'classlike'
            );

            return type;
        }));
    }
};
