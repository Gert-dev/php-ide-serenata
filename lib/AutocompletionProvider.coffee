module.exports =

##*
# Base class for providers.
##
class AbstractProvider
    ###*
     * The class selectors for which autocompletion triggers.
     *
     * @var {String}
    ###
    scopeSelector: '.source.php'

    ###*
     * The inclusion priority of the provider.
     *
     * @var {Number}
    ###
    inclusionPriority: 1

    ###*
     * Whether to let autocomplete-plus handle the actual filtering, that way we don't need to manually filter (e.g.
     * using fuzzaldrin) ourselves and the user can configure filtering settings on the base package.
     *
     * Set to false as the core does the filtering to avoid sending a large amount of suggestions back over the socket.
     *
     * @var {Boolean}
    ###
    filterSuggestions: false

    ###*
     * The class selectors autocompletion is explicitly disabled for (overrules the {@see scopeSelector}).
     *
     * @var {String}
    ###
    disableForScopeSelector: null

    ###*
     * Whether to exclude providers with a lower priority.
     *
     * This ensures the default, built-in suggestions from the language-php package do not show up.
     *
     * @var {Boolean}
    ###
    excludeLowerPriority: true

    ###*
     * The service (that can be used to query the source code and contains utility methods).
     *
     * @var {Object}
    ###
    service: null

    ###*
     * @param {Service} service
    ###
    activate: (@service) ->
        # No op.

    ###*
     *
    ###
    deactivate: () ->
        # No op.

    ###*
     * Entry point for all requests from autocomplete-plus.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     * @param {String}     scopeDescriptor
     * @param {String}     prefix
     *
     * @return {Promise|Array}
    ###
    getSuggestions: ({editor, bufferPosition, scopeDescriptor, prefix}) ->
        successHandler = (suggestions) =>
            return suggestions.map (suggestion) =>
                return @getAdaptedSuggestion(suggestion)

        failureHandler = () =>
            return [] # Just return no suggestions.

        return @service.autocompleteAt(editor, bufferPosition).then(successHandler, failureHandler)

    ###*
     * @param {Object} suggestion
     *
     * @return {Array}
    ###
    getAdaptedSuggestion: (suggestion) ->
        adaptedSuggestion = {
            text               : suggestion.filterText
            # replacementPrefix  : prefix
            snippet            : suggestion.insertText
            type               : suggestion.kind
            displayText        : suggestion.label
            leftlabelHTML      : @getSuggestionLeftLabel(suggestion)
            rightLabelHTML     : @getSuggestionRightLabel(suggestion)
            description        : suggestion.documentation
            descriptionMoreURL : suggestion.extraData.url
            className          : 'php-integrator-autocomplete-plus-suggestion' + if suggestion.isDeprecated then ' php-integrator-autocomplete-plus-strike' else ''
        }

        if suggestion.extraData?.placeCursorBetweenParentheses == true
            adaptedSuggestion.snippet = adaptedSuggestion.snippet.replace('()', '($0)')

        else
            adaptedSuggestion.snippet += '$0' # Place cursor after suggestion.

        return adaptedSuggestion

    ###*
     * Builds the right label for a PHP function or method.
     *
     * @param {Object} suggestion Information about the function or method.
     *
     * @return {String}
    ###
    getSuggestionLeftLabel: (suggestion) ->
        leftLabel = ''

        if suggestion.extraData?.protectionLevel == 'public'
           leftLabel += '<span class="icon icon-globe import">&nbsp;</span>'

        else if suggestion.extraData?.protectionLevel == 'protecetd'
           leftLabel += '<span class="icon icon-shield">&nbsp;</span>'

        else if suggestion.extraData?.protectionLevel == 'private'
           leftLabel += '<span class="icon icon-lock selector">&nbsp;</span>'

        if suggestion.extraData?.returnTypes?
            leftLabel += @getTypeSpecificationFromTypeArray(suggestion.extraData.returnTypes.split('|'))

        return leftLabel

    ###*
     * Builds the right label for a PHP function or method.
     *
     * @param {Object} suggestion Information about the function or method.
     *
     * @return {String}
    ###
    getSuggestionRightLabel: (suggestion) ->
        return null if not suggestion.extraData?.declaringStructure?

        # Determine the short name of the location where this item is defined.
        declaringStructureShortName = ''

        if suggestion.extraData.declaringStructure and suggestion.extraData.declaringStructure.name
            return @getClassShortName(suggestion.extraData.declaringStructure.name)

        return declaringStructureShortName

    ###*
     * @param {Array} typeArray
     *
     * @return {String}
    ###
    getTypeSpecificationFromTypeArray: (typeArray) ->
        typeNames = typeArray.map (type) =>
            return @getClassShortName(type.type)

        return typeNames.join('|')

    ###*
     * Retrieves the short name for the specified class name (i.e. the last segment, without the class namespace).
     *
     * @param {String} className
     *
     * @return {String}
    ###
    getClassShortName: (className) ->
        return null if not className

        parts = className.split('\\')
        return parts.pop()
