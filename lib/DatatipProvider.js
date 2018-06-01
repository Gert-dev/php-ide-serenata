/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let DatatipProvider;
const {Disposable, CompositeDisposable} = require('atom');

const SymbolHelpers = require('./SymbolHelpers');

module.exports =

//#*
// Provides datatips (tooltips).
//#
(DatatipProvider = (function() {
    DatatipProvider = class DatatipProvider {
        static initClass() {
            /**
             * The service (that can be used to query the source code and contains utility methods).
             *
             * @var {Object|null}
            */
            this.prototype.service = null;
    
            /**
             * @var {Array}
            */
            this.prototype.grammarScopes = ['text.html.php'];
    
            /**
             * @var {Number}
            */
            this.prototype.priority = 1;
    
            /**
             * @var {String}
            */
            this.prototype.providerName = 'php-integrator';
        }

        /**
         * Initializes this provider.
         *
         * @param {mixed} service
        */
        activate(service) {
            this.service = service;
        }

        /**
         * Deactives the provider.
        */
        deactivate() {}

        /**
         * @param {TextEditor} editor
         * @param {Point}      bufferPosition
         *
         * @return {Promise|null}
        */
        datatip(editor, bufferPosition, heldKeys) {
            if (!this.service.getCurrentProjectSettings()) {
                return new Promise((resolve, reject) => {
                    return reject();
                });
            }

            const scopeChain = editor.scopeDescriptorForBufferPosition(bufferPosition).getScopeChain();

            if (scopeChain.length === 0) {
                return new Promise((resolve, reject) => {
                    return reject();
                });
            }

            // Skip whitespace and other noise
            if (scopeChain === '.text.html.php .meta.embedded.block.php .source.php') {
                return new Promise((resolve, reject) => {
                    return reject();
                });
            }

            const successHandler = tooltip => {
                if ((tooltip == null)) { return null; }

                return {
                    markedStrings : [{
                        type  : 'markdown',
                        value : tooltip.contents
                    }],

                    // FIXME: core doesn't generate ranges yet, otherwise we could use tooltip.range
                    range    : SymbolHelpers.getRangeForSymbolAtPosition(editor, bufferPosition),
                    pinnable : true
                };
            };

            const failureHandler = () => null;

            return this.service.tooltipAt(editor, bufferPosition).then(successHandler, failureHandler);
        }
    };
    DatatipProvider.initClass();
    return DatatipProvider;
})());
