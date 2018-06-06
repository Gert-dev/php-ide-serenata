/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let LinterProvider;
const {CompositeDisposable} = require('atom');

module.exports =

//#*
// Provider of linter messages to the (indie) linter service.
//#
(LinterProvider = (function() {
    LinterProvider = class LinterProvider {
        static initClass() {
        /**
         * @var {String}
        */
            this.prototype.scope = 'file';

            /**
         * @var {Boolean}
        */
            this.prototype.lintsOnChange = true;

            /**
         * @var {Array}
        */
            this.prototype.grammarScopes = ['source.php'];

            /**
         * @var {Object}
        */
            this.prototype.service = null;

            /**
         * @var {Object}
        */
            this.prototype.config = null;

            /**
         * @var {CompositeDisposable}
        */
            this.prototype.disposables = null;

            /**
         * @var {Object}
        */
            this.prototype.indieLinter = null;

            /**
         * @var {CancellablePromise}
        */
            this.prototype.pendingRequestPromise = null;
        }

        /**
       * Constructor.
       *
       * @param {Config} config
      */
        constructor(config) {
            this.config = config;
        }

        /**
       * @param {Object} indieLinter
      */
        setIndieLinter(indieLinter) {
            this.indieLinter = indieLinter;
            return this.messages = [];
        }

        /**
       * Initializes this provider.
       *
       * @param {Object} service
      */
        activate(service) {
            this.service = service;
            this.disposables = new CompositeDisposable();

            return this.attachListeners(this.service);
        }

        /**
       * Deactives the provider.
      */
        deactivate() {
            return this.disposables.dispose();
        }

        /**
       * @param {Object} service
      */
        attachListeners(service) {
            this.disposables.add(service.onDidFinishIndexing(response => {
                const editor = this.findTextEditorByPath(response.path);

                if ((editor == null)) { return; }
                if ((this.indieLinter == null)) { return; }

                return this.lint(editor, response.source);
            })
            );

            return this.disposables.add(service.onDidFailIndexing(response => {
                const editor = this.findTextEditorByPath(response.path);

                if ((editor == null)) { return; }
                if ((this.indieLinter == null)) { return; }

                return this.lint(editor, response.source);
            })
            );
        }

        /**
       * @param {TextEditor} editor
       * @param {String}     source
      */
        lint(editor, source) {
            if (this.pendingRequestPromise != null) {
                this.pendingRequestPromise.cancel();
                this.pendingRequestPromise = null;
            }

            const successHandler = response => {
                return this.processSuccess(editor, response, source);
            };

            const failureHandler = response => {
                return this.processFailure(editor);
            };

            this.pendingRequestPromise = this.invokeLint(editor.getPath(), source);

            return this.pendingRequestPromise.then(successHandler, failureHandler);
        }

        /**
       * @param {String} path
       * @param {String} source
       *
       * @return {CancellablePromise}
      */
        invokeLint(path, source) {
            const options = {
                noUnknownClasses         : !this.config.get('linting.showUnknownClasses'),
                noUnknownMembers         : !this.config.get('linting.showUnknownMembers'),
                noUnknownGlobalFunctions : !this.config.get('linting.showUnknownGlobalFunctions'),
                noUnknownGlobalConstants : !this.config.get('linting.showUnknownGlobalConstants'),
                noUnusedUseStatements    : !this.config.get('linting.showUnusedUseStatements'),
                noDocblockCorrectness    : !this.config.get('linting.validateDocblockCorrectness'),
                noMissingDocumentation   : !this.config.get('linting.showMissingDocs')
            };

            return this.service.lint(path, source, options);
        }

        /**
       * @param {TextEditor} editor
       * @param {Object}     response
       * @param {String}     source
       *
       * @return {Array}
      */
        processSuccess(editor, response, source) {
            const messages = [];

            for (const item of response.errors) {
                messages.push(this.createLinterErrorMessageForOutputItem(editor, item, source));
            }

            for (const item of response.warnings) {
                messages.push(this.createLinterWarningMessageForOutputItem(editor, item, source));
            }

            return this.indieLinter.setMessages(editor.getPath(), messages);
        }

        /**
       * @param {TextEditor} editor
       *
       * @return {Array}
      */
        processFailure(editor) {
            return this.indieLinter.setMessages(editor.getPath(), []);
        }

        /**
       * @param {TextEditor} editor
       * @param {Object}     item
       * @param {String}     source
       *
       * @return {Object}
      */
        createLinterErrorMessageForOutputItem(editor, item, source) {
            return this.createLinterMessageForOutputItem(editor, item, source, 'error');
        }

        /**
       * @param {TextEditor} editor
       * @param {Object}     item
       * @param {String}     source
       *
       * @return {Object}
      */
        createLinterWarningMessageForOutputItem(editor, item, source) {
            return this.createLinterMessageForOutputItem(editor, item, source, 'warning');
        }

        /**
       * @param {TextEditor} editor
       * @param {Object}     item
       * @param {String}     source
       * @param {String}     severity
       *
       * @return {Object}
      */
        createLinterMessageForOutputItem(editor, item, source, severity) {
            const startCharacterOffset = this.service.getCharacterOffsetFromByteOffset(item.start, source);
            const endCharacterOffset   = this.service.getCharacterOffsetFromByteOffset(item.end, source);

            const startPoint = editor.getBuffer().positionForCharacterIndex(startCharacterOffset);
            const endPoint   = editor.getBuffer().positionForCharacterIndex(endCharacterOffset);

            return {
                excerpt  : item.message,
                severity,

                location: {
                    file     : editor.getPath(),
                    position : [startPoint, endPoint]
                }
            };
        }

        /**
       * Retrieves the text editor that is managing the file with the specified path.
       *
       * @param {String} path
       *
       * @return {TextEditor|null}
      */
        findTextEditorByPath(path) {
            for (const textEditor of atom.workspace.getTextEditors()) {
                if (textEditor && (textEditor.getPath() === path)) {
                    return textEditor;
                }
            }

            return null;
        }
    };
    LinterProvider.initClass();
    return LinterProvider;
})());
