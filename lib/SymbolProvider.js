
const LanguageClientOutlineViewAdapter = require(
    '../node_modules/atom-languageclient/build/lib/adapters/outline-view-adapter.js'
);

/**
 * Symbol provider for atom-ide-ui.
 */
class SymbolProvider
{
    constructor() {
        this.service = null;
        this.grammarScopes = ['text.html.php'];
        this.updateOnEdit = false;
        this.pendingRequestPromise = null;
    }

    /**
     * Initializes this provider.
     *
     * @param {Object} service
     */
    activate(service) {
        this.service = service;
    }

    /**
     * Deactives the provider.
     */
    deactivate() {
    }

    /**
     * @param {TextEditor} editor
     *
     * @return {Promise}
     */
    getOutline(editor) {
        if (this.pendingRequestPromise !== null) {
            this.pendingRequestPromise.cancel();
            this.pendingRequestPromise = null;
        }

        const successHandler = (symbols) => {
            return {
                outlineTrees: LanguageClientOutlineViewAdapter.createOutlineTrees(symbols)
            };
        };

        const failureHandler = () => {
            return null;
        };

        this.pendingRequestPromise = this.service.getDocumentSymbols(editor.getPath());

        return this.pendingRequestPromise.then(successHandler, failureHandler);
    }
}

exports.default = SymbolProvider;
module.exports = exports['default'];
