{Disposable, CompositeDisposable} = require 'atom'

marked = require 'marked'

CancelablePromise = require './CancelablePromise'

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
     * @var {CancelablePromise|null}
    ###
    pendingCancelablePromise: null

    ###*
     * @var {Number|null}
    ###
    timeoutHandle: null

    ###*
     * @var {CompositeDisposable}
    ###
    disposables: null

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
        @disposables.dispose()

        @removeTooltip()

    ###*
     * Registers the necessary event handlers.
     *
     * @param {TextEditor} editor TextEditor to register events to.
    ###
    registerEvents: (editor) ->
        textEditorElement = atom.views.getView(editor)
        scrollViewElement = textEditorElement.querySelector('.scroll-view')

        removeTooltipListener = @removeTooltip.bind(this)

        if scrollViewElement?
            mouseOverListener = @onMouseOver.bind(this, editor)

            scrollViewElement.addEventListener('mouseover', mouseOverListener)
            scrollViewElement.addEventListener('mouseout',  removeTooltipListener)

            @disposables.add new Disposable () =>
                scrollViewElement.removeEventListener('mouseover', mouseOverListener)
                scrollViewElement.removeEventListener('mouseout', removeTooltipListener)

        horizontalScrollbar = textEditorElement.querySelector('.horizontal-scrollbar')

        if horizontalScrollbar?
            horizontalScrollbar.addEventListener('scroll',  removeTooltipListener)

            @disposables.add new Disposable () =>
                horizontalScrollbar.removeEventListener('scroll', removeTooltipListener)

        verticalScrollbar = textEditorElement.querySelector('.vertical-scrollbar')

        if verticalScrollbar?
            verticalScrollbar.addEventListener('scroll',  removeTooltipListener)

            @disposables.add new Disposable () =>
                verticalScrollbar.removeEventListener('scroll', removeTooltipListener)

        # Ticket #107 - Mouseout isn't generated until the mouse moves, even when scrolling (with the keyboard or
        # mouse). If the element goes out of the view in the meantime, its HTML element disappears, never removing
        # it.
        @disposables.add editor.onDidDestroy(@removeTooltip.bind(this))
        @disposables.add editor.onDidStopChanging(@removeTooltip.bind(this))

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

        if @pendingCancelablePromise?
            console.log("Force rejecting")
            @pendingCancelablePromise.cancel()
            @pendingCancelablePromise = null

        # Needs to be bound outside of the callback, event.currentTarget may no longer be the same value once the
        # callback is run.
        target = event.target

        return if not target?
        return if 'syntax--php' not in target.classList # Skip whitespace and other noise

        cursorPosition = editorViewComponent.screenPositionForMouseEvent(event)

        @timeoutHandle = setTimeout ( =>
            console.debug("Showing tooltip ", cursorPosition, target)

            @showTooltipAt(editor, cursorPosition, target)
            @timeoutHandle = null
        ), 500

    ###*
     * @param {TextEditor} editor
     * @param {Point}      cursorPosition
     * @param {Object}     showOverElement
     *
     * @return {Promise}
    ###
    showTooltipAt: (editor, cursorPosition, showOverElement) ->
        successHandler = (tooltip) =>
            @pendingCancelablePromise = null

            return if not tooltip?

            @removeTooltip()

            @attachedPopover = @service.createAttachedPopover(showOverElement)
            @attachedPopover.setText('<div class="php-integrator-tooltip-popover">' + marked(tooltip.contents) + '</div>')
            @attachedPopover.show()

        failureHandler = () =>
            @pendingCancelablePromise = null

            @removeTooltip()

        promise = @service.tooltipAt(editor, cursorPosition)

        @pendingCancelablePromise = new CancelablePromise(promise)

        return @pendingCancelablePromise.then(successHandler, failureHandler).catch(failureHandler)

    ###*
     * Removes the popover, if it is displayed.
    ###
    removeTooltip: () ->
        if @pendingCancelablePromise?
            @pendingCancelablePromise.cancel()
            @pendingCancelablePromise = null

        if @attachedPopover
            @attachedPopover.dispose()
            @attachedPopover = null
