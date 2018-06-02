/*
 * decaffeinate suggestions:
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let SignatureHelpProvider;
module.exports =

//#*
// Provides signature help.
//#
(SignatureHelpProvider = (function() {
    SignatureHelpProvider = class SignatureHelpProvider {
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
         * @var {Set|null}
        */
            this.prototype.triggerCharacters = null;
  
            /**
         * @var {CancellablePromise}
        */
            this.prototype.pendingRequestPromise = null;
        }

        /**
       * Constructor.
      */
        constructor() {
            this.triggerCharacters = new Set(['(', ',']);
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
       * @param {Point}      point
       *
       * @return {Promise}
      */
        getSignatureHelp(editor, point) {
            if (this.pendingRequestPromise != null) {
                this.pendingRequestPromise.cancel();
                this.pendingRequestPromise = null;
            }

            const successHandler = signatureHelp => {
                return signatureHelp;
            };

            const failureHandler = () => {
                return null;
            };

            this.pendingRequestPromise = this.service.signatureHelpAt(editor, point);

            return this.pendingRequestPromise.then(successHandler, failureHandler);
        }
    };
    SignatureHelpProvider.initClass();
    return SignatureHelpProvider;
})());
