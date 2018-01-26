{Disposable, CompositeDisposable} = require 'atom'

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
     * @var {CompositeDisposable}
    ###
    disposables: null

    ###*
     * @var {CancellablePromise}
    ###
    pendingRequestPromise: null

    ###*
     * Initializes this provider.
     *
     * @param {mixed} service
    ###
    activate: (@service) ->
        dependentPackage = 'language-php'

        @disposables = new CompositeDisposable()

        # It could be that the dependent package is already active, in that case we can continue immediately. If not,
        # we'll need to wait for the listener to be invoked
        if atom.packages.isPackageActive(dependentPackage)
            @doActualInitialization()

        @disposables.add atom.packages.onDidActivatePackage (packageData) =>
            return if packageData.name != dependentPackage

            @doActualInitialization()

        @disposables.add atom.packages.onDidDeactivatePackage (packageData) =>
            return if packageData.name != dependentPackage

            @deactivate()

    ###*
     * Does the actual initialization.
    ###
    doActualInitialization: () ->
        @disposables.add atom.workspace.observeTextEditors (editor) =>
            if /text.html.php$/.test(editor.getGrammar().scopeName)
                @registerEvents(editor)

        # When you go back to only have one pane the events are lost, so need to re-register.
        @disposables.add atom.workspace.onDidDestroyPane (pane) =>
            panes = atom.workspace.getPanes()

            if panes.length == 1
                @registerEventsForPane(panes[0])

        # Having to re-register events as when a new pane is created the old panes lose the events.
        @disposables.add atom.workspace.onDidAddPane (observedPane) =>
            panes = atom.workspace.getPanes()

            for pane in panes
                if pane != observedPane
                    @registerEventsForPane(pane)

        @disposables.add atom.workspace.onDidStopChangingActivePaneItem (item) =>
            @stopPendingRequests()

        @disposables.add atom.workspace.onDidChangeActiveTextEditor (item) =>
            @stopPendingRequests()

    ###*
     * Registers the necessary event handlers for the editors in the specified pane.
     *
     * @param {Pane} pane
    ###
    registerEventsForPane: (pane) ->
        for paneItem in pane.items
            if atom.workspace.isTextEditor(paneItem)
                if /text.html.php$/.test(paneItem.getGrammar().scopeName)
                    @registerEvents(paneItem)

    ###*
     * Registers the necessary event handlers.
     *
     * @param {TextEditor} editor TextEditor to register events to.
    ###
    registerEvents: (editor) ->
        @disposables.add editor.onDidChangeCursorPosition (event) =>
            # Don't trigger whilst actually typing, just when moving.
            return if event.textChanged == true

            @onChangeCursorPosition(editor)

    ###*
     * @param {TextEditor} editor
    ###
    onChangeCursorPosition: (editor) ->
        @stopPendingRequests()

    ###*
     * Deactives the provider.
    ###
    deactivate: () ->
        @disposables.dispose()

        @stopPendingRequests()

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
        @stopPendingRequests()

        return [] if not @service

        successHandler = (suggestions) =>
            return suggestions.map (suggestion) =>
                return @getAdaptedSuggestion(suggestion)

        failureHandler = () =>
            return [] # Just return no suggestions.

        @pendingRequestPromise = @service.autocompleteAt(editor, bufferPosition)

        return @pendingRequestPromise.then(successHandler, failureHandler)

    ###*
     * @param {Object} suggestion
     *
     * @return {Array}
    ###
    getAdaptedSuggestion: (suggestion) ->
        adaptedSuggestion = {
            text               : suggestion.filterText
            snippet            : suggestion.insertText.replace(/\\/g, '\\\\')
            type               : suggestion.kind
            displayText        : suggestion.label
            leftLabelHTML      : @getSuggestionLeftLabel(suggestion)
            rightLabelHTML     : @getSuggestionRightLabel(suggestion)
            description        : suggestion.documentation
            className          : 'php-integrator-autocompletion-suggestion' + if suggestion.isDeprecated then ' php-integrator-autocompletion-strike' else ''

            extraData:
                additionalTextEdits: suggestion.additionalTextEdits
        }

        # TODO: Better would be to support the textEdit property sent brck by the core's suggestions via
        # onDidInsertSuggestion.
        if suggestion.extraData?.prefix?
            adaptedSuggestion.replacementPrefix = suggestion.extraData.prefix

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

        if suggestion.extraData.declaringStructure and suggestion.extraData.declaringStructure.fqcn
            return @getClassShortName(suggestion.extraData.declaringStructure.fqcn)

        return declaringStructureShortName

    ###*
     * @param {Array} typeArray
     *
     * @return {String}
    ###
    getTypeSpecificationFromTypeArray: (typeArray) ->
        typeNames = typeArray.map (type) =>
            return @getClassShortName(type)

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

    ###*
     * Called when the user confirms an autocompletion suggestion.
     *
     * @param {TextEditor} editor
     * @param {Position}   triggerPosition
     * @param {Object}     suggestion
    ###
    onDidInsertSuggestion: ({editor, triggerPosition, suggestion}) ->
        return unless suggestion.extraData.additionalTextEdits?.length > 0

        editor.transact () =>
            for additionalTextEdit in suggestion.extraData.additionalTextEdits
                editor.setTextInBufferRange([
                    [additionalTextEdit.range.start.line, additionalTextEdit.range.start.character],
                    [additionalTextEdit.range.end.line, additionalTextEdit.range.end.character]],
                    additionalTextEdit.newText
                )

    ###*
     * Stops any pending requests.
    ###
    stopPendingRequests: () ->
        if @pendingRequestPromise?
            @pendingRequestPromise.cancel()
            @pendingRequestPromise = null
