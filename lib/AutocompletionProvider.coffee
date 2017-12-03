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
        return {
            text : suggestion.filterText
        }
