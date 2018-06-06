/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let GotoDefinitionProvider;
const SymbolHelpers = require('./SymbolHelpers');

module.exports =

//#*
// Handles goto definition (code navigation).
//#
(GotoDefinitionProvider = (function() {
    GotoDefinitionProvider = class GotoDefinitionProvider {
        static initClass() {
            /**
             * @var {Object}
            */
            this.prototype.service = null;

            /**
             * @var {CancellablePromise}
            */
            this.prototype.pendingRequestPromise = null;

            /**
             * @var {PhpInvoker}
            */
            this.prototype.phpInvoker = null;
        }

        /**
         * @param {Object} phpInvoker
        */
        constructor(phpInvoker) {
            this.phpInvoker = phpInvoker;
        }

        /**
         * @param {Object} service
        */
        activate(service) {
            return this.service = service;
        }

        /**
         * @param {TextEditor} editor
         * @param {Point}      bufferPosition
        */
        getSuggestion(editor, bufferPosition) {
            if (this.pendingRequestPromise != null) {
                this.pendingRequestPromise.cancel();
                this.pendingRequestPromise = null;
            }

            if ((this.service == null)) { return null; }

            const successHandler = result => {
                if ((result == null)) { return null; }

                return {
                    range : SymbolHelpers.getRangeForSymbolAtPosition(editor, bufferPosition),

                    callback : () => {
                        return atom.workspace.open(this.phpInvoker.denormalizePlatformAndRuntimePath(result.uri), {
                            initialLine    : (result.line - 1),
                            searchAllPanes: true
                        });
                    }
                };
            };

            const failureHandler = () => {
                return null;
            };

            this.pendingRequestPromise = this.service.gotoDefinitionAt(editor, bufferPosition);

            return this.pendingRequestPromise.then(successHandler, failureHandler);
        }
    };
    GotoDefinitionProvider.initClass();
    return GotoDefinitionProvider;
})());
