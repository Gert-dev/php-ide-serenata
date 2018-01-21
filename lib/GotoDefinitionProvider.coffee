SymbolHelpers = require './SymbolHelpers'

module.exports =

##*
# Handles goto definition (code navigation).
##
class GotoDefinitionProvider
    ###*
     * @var {Object}
    ###
    service: null

    ###*
     * @var {CancellablePromise}
    ###
    pendingRequestPromise: null

    ###*
     * @param {Object} service
    ###
    activate: (service) ->
        @service = service

    ###*
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
    ###
    getSuggestion: (editor, bufferPosition) ->
        if @pendingRequestPromise?
            @pendingRequestPromise.cancel()
            @pendingRequestPromise = null

        return null if not @service?

        successHandler = (result) =>
            return null if not result?

            return {
                range : SymbolHelpers.getRangeForSymbolAtPosition(editor, bufferPosition)

                callback : () =>
                    atom.workspace.open(result.uri, {
                        initialLine    : (result.line - 1),
                        searchAllPanes: true
                    })
            }

        failureHandler = () =>
            return null

        @pendingRequestPromise = @service.gotoDefinitionAt(editor, bufferPosition)

        return @pendingRequestPromise.then(successHandler, failureHandler)
