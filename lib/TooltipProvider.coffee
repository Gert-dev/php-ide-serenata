module.exports =

##*
# Provides tooltips.
##
class TooltipProvider
    ###*
     * The service (that can be used to query the source code and contains utility methods).
     *
     * @var {Object|null}
    ###
    service: null

    ###*
     * Keeps track of the currently pending promise.
     *
     * @var {Promise|null}
    ###
    pendingPromise: null

    ###*
     * @var {Number|null}
    ###
    timeoutHandle: null

    ###*
     * Initializes this provider.
     *
     * @param {mixed} service
    ###
    activate: (@service) ->
        dependentPackage = 'language-php'

        # It could be that the dependent package is already active, in that case we can continue immediately. If not,
        # we'll need to wait for the listener to be invoked
        if atom.packages.isPackageActive(dependentPackage)
            @doActualInitialization()

        atom.packages.onDidActivatePackage (packageData) =>
            return if packageData.name != dependentPackage

            @doActualInitialization()

        atom.packages.onDidDeactivatePackage (packageData) =>
            return if packageData.name != dependentPackage

            @deactivate()

    ###*
     * Does the actual initialization.
    ###
    doActualInitialization: () ->
        atom.workspace.observeTextEditors (editor) =>
            if /text.html.php$/.test(editor.getGrammar().scopeName)
                @registerEvents(editor)

        # When you go back to only have one pane the events are lost, so need to re-register.
        atom.workspace.onDidDestroyPane (pane) =>
            panes = atom.workspace.getPanes()

            if panes.length == 1
                @registerEventsForPane(panes[0])

        # Having to re-register events as when a new pane is created the old panes lose the events.
        atom.workspace.onDidAddPane (observedPane) =>
            panes = atom.workspace.getPanes()

            for pane in panes
                if pane != observedPane
                    @registerEventsForPane(pane)

        atom.workspace.onDidStopChangingActivePaneItem (item) =>
            @removeTooltip()

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
     * Deactives the provider.
    ###
    deactivate: () ->
        @removeTooltip()

    ###*
     * Registers the necessary event handlers.
     *
     * @param {TextEditor} editor TextEditor to register events to.
    ###
    registerEvents: (editor) ->
        textEditorElement = atom.views.getView(editor)
        scrollViewElement = textEditorElement.querySelector('.scroll-view')

        if scrollViewElement?
            scrollViewElement.addEventListener('mouseover', @onMouseOver.bind(this, editor))
            scrollViewElement.addEventListener('mouseout',  @removeTooltip.bind(this))

        horizontalScrollbar = textEditorElement.querySelector('.horizontal-scrollbar')

        if horizontalScrollbar?
            horizontalScrollbar.addEventListener('scroll',  @removeTooltip.bind(this))

        verticalScrollbar = textEditorElement.querySelector('.vertical-scrollbar')

        if verticalScrollbar?
            verticalScrollbar.addEventListener('scroll',  @removeTooltip.bind(this))

        # Ticket #107 - Mouseout isn't generated until the mouse moves, even when scrolling (with the keyboard or
        # mouse). If the element goes out of the view in the meantime, its HTML element disappears, never removing
        # it.
        editor.onDidDestroy(@removeTooltip.bind(this))
        editor.onDidStopChanging(@removeTooltip.bind(this))

    ###*
     * @param {TextEditor} editor
     * @param {Object}     event
    ###
    onMouseOver: (editor, event) ->
        @removeTooltip()

        editorViewComponent = atom.views.getView(editor).component

        # Ticket #140 - In rare cases the component is null.
        return if not editorViewComponent?

        if @timeoutHandle?
            clearTimeout(@timeoutHandle)
            @timeoutHandle = null

        # Needs to be bound outside of the callback, event.currentTarget may no longer be the same value once the
        # callback is run.
        testTarget = event.currentTarget

        @timeoutHandle = setTimeout ( =>
            cursorPosition = editorViewComponent.screenPositionForMouseEvent(event)

            @showTooltipAt(editor, cursorPosition, testTarget)
            @timeoutHandle = null
        ), 500

    ###*
     * @param {TextEditor} editor
     * @param {Point}      cursorPosition
     * @param {Object}     showOverElement
    ###
    showTooltipAt: (editor, cursorPosition, showOverElement) ->
        successHandler = (tooltip) =>
            return if not tooltip?

            @removeTooltip()

            marked = require 'marked'

            @attachedPopover = @service.createAttachedPopover(showOverElement)
            @attachedPopover.setText('<div class="php-integrator-tooltips-popover">' + marked(tooltip.contents) + '</div>')
            @attachedPopover.showAfter(0, 100)

        failureHandler = () =>
            @removeTooltip()

        return @service.tooltipAt(editor, cursorPosition).then(successHandler, failureHandler)

    ###*
     * Removes the popover, if it is displayed.
    ###
    removeTooltip: () ->
        if @pendingPromise?
            @pendingPromise.reject()
            @pendingPromise = null

        if @attachedPopover
            @attachedPopover.dispose()
            @attachedPopover = null
