/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let Service;
const {Buffer} = require('buffer');

const AttachedPopover = require('./Widgets/AttachedPopover');

module.exports =

//#*
// The service that is exposed to other packages.
//#
(Service = (function() {
    Service = class Service {
        static initClass() {
            /**
             * @var {Object}
            */
            this.prototype.proxy = null;

            /**
             * @var {Object}
            */
            this.prototype.projectManager = null;
        }

        /**
         * Constructor.
         *
         * @param {CachingProxy} proxy
         * @param {Object}       projectManager
        */
        constructor(proxy, projectManager) {
            this.proxy = proxy;
            this.projectManager = projectManager;
        }

        /**
         * Retrieves the settings (that are specific to this package) for the currently active project. If there is no
         * active project or the project does not have any settings, null is returned.
         *
         * @return {Object|null}
        */
        getCurrentProjectSettings() {
            return this.projectManager.getCurrentProjectSettings();
        }

        /**
         * Retrieves a list of available classes in the specified URI.
         *
         * @param {String} uri
         *
         * @return {Promise}
        */
        getClassListForFile(uri) {
            return this.proxy.getClassListForFile(uri);
        }

        /**
         * Retrieves a list of available global constants.
         *
         * @return {Promise}
        */
        getGlobalConstants() {
            return this.proxy.getGlobalConstants();
        }

        /**
         * Retrieves a list of available global functions.
         *
         * @return {Promise}
        */
        getGlobalFunctions() {
            return this.proxy.getGlobalFunctions();
        }

        /**
         * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
         *
         * @param {String} className
         *
         * @return {Promise}
        */
        getClassInfo(className) {
            return this.proxy.getClassInfo(className);
        }

        /**
         * Resolves a local type in the specified file, based on use statements and the namespace.
         *
         * @param {String}   uri
         * @param {Position} position
         * @param {String}   type
         * @param {String}   kind The kind of element. Either 'classlike', 'constant' or 'function'.
         *
         * @return {CancellablePromise}
        */
        resolveType(uri, position, type, kind) {
            return this.proxy.resolveType(uri, position, type, kind);
        }

        /**
         * Localizes a type to the specified file, making it relative to local use statements, if possible. If not
         * possible, null is returned.
         *
         * @param {String}   uri
         * @param {Position} position
         * @param {String}   type
         * @param {String}   kind The kind of element. Either 'classlike', 'constant' or 'function'.
         *
         * @return {CancellablePromise}
        */
        localizeType(uri, position, type, kind) {
            return this.proxy.localizeType(uri, position, type, kind);
        }

        /**
         * Fetches all available variables at a specific location.
         *
         * @param {String}      uri      The URI of the file to examine.
         * @param {String|null} source   The source code to search. May be null if a file is passed instead.
         * @param {Position}    position The position into the file to examine.
         *
         * @return {Promise}
        */
        getAvailableVariablesByOffset(uri, source, position) {
            return this.proxy.getAvailableVariables(uri, source, position);
        }

        /**
         * Deduces the resulting types of an expression.
         *
         * @param {String|null} expression        The expression to deduce the type of, e.g. '$this->foo()'. If null,
         *                                        the expression just before the specified offset will be used.
         * @param {String}      uri               The URI of the file to examine.
         * @param {String|null} source            The source code to search. May be null if a file is passed instead.
         * @param {Position}    position          The position into the file to examine.
         * @param {bool}        ignoreLastElement Whether to remove the last element or not, this is useful when the
         *                                        user is still writing code, e.g. "$this->foo()->b" would normally
         *                                        return thetype (class) of 'b', as it is the last element, but as the
         *                                        user is still writing code, you may instead be interested in the type
         *                                        of 'foo()' instead.
         *
         * @return {CancellablePromise}
        */
        deduceTypes(expression, uri, source, position, ignoreLastElement) {
            return this.proxy.deduceTypes(expression, uri, source, position, ignoreLastElement);
        }

        /**
         * Convenience alias for {@see deduceTypes}.
         *
         * @param {String}     expression
         * @param {TextEditor} editor
         * @param {Range}      bufferPosition
         *
         * @return {Promise}
        */
        deduceTypesAt(expression, editor, bufferPosition) {
            const bufferText = editor.getBuffer().getText();

            return this.deduceTypes(expression, editor.getPath(), bufferText, bufferPosition);
        }

        /**
         * Determines the current class' FQCN based on the specified buffer position.
         *
         * @param {TextEditor} editor         The editor that contains the class (needed to resolve relative class
         *                                    names).
         * @param {Point}      bufferPosition
         *
         * @return {Promise}
        */
        determineCurrentClassName(editor, bufferPosition) {
            return new Promise((resolve, reject) => {
                const path = editor.getPath();

                if ((path == null)) {
                    reject();
                    return;
                }

                const successHandler = classesInFile => {
                    let bestMatch = null;

                    for (let name in classesInFile) {
                        const classInfo = classesInFile[name];

                        if ((bufferPosition.row >= classInfo.range.start.line) && (bufferPosition.row < classInfo.range.end.line)) {
                            bestMatch = name;
                        }
                    }

                    resolve(bestMatch);
                };

                const failureHandler = () => {
                    return reject();
                };

                return this.getClassListForFile(path).then(successHandler, failureHandler);
            });
        }

        /**
         * Retrieves all variables that are available at the specified buffer position.
         *
         * @param {TextEditor} editor
         * @param {Range}      bufferPosition
         *
         * @return {Promise}
        */
        getAvailableVariables(editor, bufferPosition) {
            return this.getAvailableVariablesByOffset(editor.getPath(), editor.getBuffer().getText(), bufferPosition);
        }

        /**
         * Creates an attached popover with the specified constructor arguments.
        */
        createAttachedPopover() {
            return new AttachedPopover(...arguments);
        }
    };
    Service.initClass();
    return Service;
})());
