{Disposable, CompositeDisposable} = require 'atom'

SymbolHelpers = require './SymbolHelpers'

module.exports =

##*
# Provides datatips (tooltips).
##
class DatatipProvider
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
     * @var {String}
    ###
    providerName: 'php-integrator'

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
     * @param {Point}      bufferPosition
     *
     * @return {Promise|null}
    ###
    datatip: (editor, bufferPosition, heldKeys) ->
        if not @service.getCurrentProjectSettings()
            return new Promise (resolve, reject) =>
                reject()

        scopeChain = editor.scopeDescriptorForBufferPosition(bufferPosition).getScopeChain()

        if scopeChain.length == 0
            return new Promise (resolve, reject) =>
                reject()

        # Skip whitespace and other noise
        if scopeChain == '.text.html.php .meta.embedded.block.php .source.php'
            return new Promise (resolve, reject) =>
                reject()

        successHandler = (tooltip) =>
            return null if not tooltip?

            return {
                markedStrings : [{
                    type  : 'markdown'
                    value : tooltip.contents
                }]

                # FIXME: core doesn't generate ranges yet, otherwise we could use tooltip.range
                range    : SymbolHelpers.getRangeForSymbolAtPosition(editor, bufferPosition)
                pinnable : true
            }

        failureHandler = () ->
            return null

        return @service.tooltipAt(editor, bufferPosition).then(successHandler, failureHandler)
