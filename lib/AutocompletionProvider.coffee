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
     * Let base autocomplete-plus handle the actual filtering, that way we don't need to manually filter (e.g. using
     * fuzzaldrin) ourselves and the user can configure filtering settings on the base package.
     *
     * @var {Boolean}
    ###
    filterSuggestions: true

    ###*
     * The class selectors autocompletion is explicitly disabled for (overrules the {@see scopeSelector}).
     *
     * @var {String}
    ###
    disableForScopeSelector: '.source.php .comment, .source.php .string'

    ###*
     * The service (that can be used to query the source code and contains utility methods).
     *
     * @var {Object}
    ###
    service: null

    ###*
     * Contains global package settings.
     *
     * @var {Object}
    ###
    config: null

    ###*
     * Constructor.
     *
     * @param {Config}  config
     * @param {Service} service
    ###
    constructor: (@config, @service) ->
        # @excludeLowerPriority = @config.get('disableBuiltinAutocompletion')
        #
        # @config.onDidChange 'disableBuiltinAutocompletion', (newValue) =>
        #     @excludeLowerPriority = newValue

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
        return {
            text : suggestion.filterText
        }
