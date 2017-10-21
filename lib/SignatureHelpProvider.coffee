module.exports =

##*
# Provides signature help.
##
class SignatureHelpProvider
    ###*
     * The service (that can be used to query the source code and contains utility methods).
     *
     * @var {Object|null}
    ###
    service: null

    ###*
     * @var {Array}
    ###
    grammarScopes: ['text.html.php']

    ###*
     * @var {Number}
    ###
    priority: 1

    ###*
     * @var {Set|null}
    ###
    triggerCharacters: null

    ###*
     * Constructor.
    ###
    constructor: () ->
        @triggerCharacters = new Set(['('])

    ###*
     * Initializes this provider.
     *
     * @param {mixed} service
    ###
    activate: (@service) ->

    ###*
     * Deactives the provider.
    ###
    deactivate: () ->

    ###*
     * @param {TextEditor} editor
     * @param {Point}      point
     *
     * @return {Promise}
    ###
    getSignatureHelp: (editor, point) ->
        successHandler = (signatureHelp) =>
            return signatureHelp

        failureHandler = () =>
            return null

        return @service.signatureHelpAt(editor, point).then(successHandler, failureHandler)
