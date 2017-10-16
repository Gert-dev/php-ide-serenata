{Range} = require 'atom'

LanguageClientUtils = require '../node_modules/atom-languageclient/build/lib/utils'

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
     * @param {Object} service
    ###
    activate: (service) ->
        @service = service

    ###*
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
    ###
    getSuggestion: (editor, bufferPosition) ->
        return null if not @service?

        successHandler = (result) =>
            return null if not result?

            return {
                range : @getRangeForSymbolAtPosition(editor, bufferPosition)

                callback : () =>
                    atom.workspace.open(result.uri, {
                        initialLine    : (result.line - 1),
                        searchAllPanes: true
                    })
            }

        failureHandler = () =>
            return null

        return @service.gotoDefinitionAt(editor, bufferPosition).then(successHandler, failureHandler)

    ###*
     * Slightly modified variant of the LanguageClientUtils getWordAtPosition.
     *
     * This variant does not treat the backslash as a non-word character, so it is not treated as boundary and the range
     * does not stop when it is found. This is important for qualified names in PHP (i.e. imports, qualified function
     * calls, ...).
     *
     * @param {TextEditor} editor
     * @param {Point}      position
     *
     * @see https://github.com/atom/atom-languageclient/blob/master/lib/utils.js
    ###
    getRangeForSymbolAtPosition: (editor, position) ->
        scopeDescriptor = editor.scopeDescriptorForBufferPosition(position)
        nonWordCharacters = editor.getNonWordCharacters(scopeDescriptor)
        nonWordCharacters = nonWordCharacters.replace("\\", '')
        nonWordCharacters = LanguageClientUtils.escapeRegExp(nonWordCharacters)

        range = LanguageClientUtils._getRegexpRangeAtPosition(
            editor.getBuffer(),
            position,
            ///^[\t ]*$|[^\s#{nonWordCharacters}]+///g
        )

        if range == null
            return new Range(position, position);

        return range
