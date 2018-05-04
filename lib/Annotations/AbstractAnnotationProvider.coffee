module.exports =

##*
# Base class for annotation providers.
##
class AbstractProvider
    ###*
     * List of markers that are present for each file.
     *
     * @var {Object}
    ###
    markers: null

    ###*
     * A mapping of file names to a list of annotations that are inside the gutter.
     *
     * @var {Object}
    ###
    annotations: null

    ###*
     * The service (that can be used to query the source code and contains utility methods).
    ###
    service: null

    constructor: () ->
        # Constructer here because otherwise the object is shared between instances.
        @markers  = {}
        @annotations = {}

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
                # Allow the active project to settle before registering for the first time.
                setTimeout(() =>
                    @registerAnnotations(editor)
                    @registerEvents(editor)
                , 100)

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

        # Ensure annotations are updated.
        @service.onDidFinishIndexing (data) =>
            editor = @findTextEditorByPath(data.path)

            if editor?
                @rescan(editor)

    ###*
     * Retrieves the text editor that is managing the file with the specified path.
     *
     * @param {String} path
     *
     * @return {TextEditor|null}
    ###
    findTextEditorByPath: (path) ->
        for textEditor in atom.workspace.getTextEditors()
            if textEditor.getPath() == path
                return textEditor

        return null

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
        @removeAnnotations()

    ###*
     * Registers the necessary event handlers.
     *
     * @param {TextEditor} editor TextEditor to register events to.
    ###
    registerEvents: (editor) ->
        # Ticket #107 - Mouseout isn't generated until the mouse moves, even when scrolling (with the keyboard or
        # mouse). If the element goes out of the view in the meantime, its HTML element disappears, never removing
        # it.
        editor.onDidDestroy () =>
            @removePopover()

        editor.onDidStopChanging () =>
            @removePopover()

        textEditorElement = atom.views.getView(editor)

        textEditorElement.querySelector('.horizontal-scrollbar')?.addEventListener 'scroll', (event) =>
            @removePopover()

        textEditorElement.querySelector('.vertical-scrollbar')?.addEventListener 'scroll', (event) =>
            @removePopover()

        gutterContainerElement = textEditorElement.querySelector('.gutter-container')

        mouseOverHandler = (event) =>
            annotation = @getRelevantAnnotationForEvent(editor, event)

            return if not annotation?

            @handleMouseOver(event, editor, annotation.annotationInfo)

        mouseOutHandler = (event) =>
            annotation = @getRelevantAnnotationForEvent(editor, event)

            return if not annotation?

            @handleMouseOut(event, editor, annotation.annotationInfo)

        mouseDownHandler = (event) =>
            annotation = @getRelevantAnnotationForEvent(editor, event)

            return if not annotation?

            # Don't collapse or expand the fold in the gutter, if there is any.
            event.stopPropagation()

            @handleMouseClick(event, editor, annotation.annotationInfo)

        gutterContainerElement?.addEventListener('mouseover', mouseOverHandler)
        gutterContainerElement?.addEventListener('mouseout', mouseOutHandler)
        gutterContainerElement?.addEventListener('mousedown', mouseDownHandler)


    ###*
     * @param {TextEditor} editor
     * @param {Object} event
     *
     * @return {Object|null}
    ###
    getRelevantAnnotationForEvent: (editor, event) ->
        if event.target.className.indexOf('icon-right') != -1
            longTitle = editor.getLongTitle()

            lineEventOccurredOn = parseInt(event.target.parentElement.dataset.bufferRow)

            if longTitle of @annotations
                for annotation in @annotations[longTitle]
                    if annotation.line == lineEventOccurredOn
                        return annotation

        return null

    ###*
     * Registers the annotations.
     *
     * @param {TextEditor} editor The editor to search through.
     *
     * @return {Promise|null}
    ###
    registerAnnotations: (editor) ->
        throw new Error("This method is abstract and must be implemented!")

    ###*
     * Places an annotation at the specified line and row text.
     *
     * @param {TextEditor} editor
     * @param {Range}      range
     * @param {Object}     annotationInfo
    ###
    placeAnnotation: (editor, range, annotationInfo) ->
        marker = editor.markBufferRange(range, {
            invalidate : 'touch'
        })

        decoration = editor.decorateMarker(marker, {
            type: 'line-number',
            class: annotationInfo.lineNumberClass
        })

        longTitle = editor.getLongTitle()

        if longTitle not of @markers
            @markers[longTitle] = []

        @markers[longTitle].push(marker)

        @registerAnnotationEventHandlers(editor, range.start.row, annotationInfo)

    ###*
     * Registers annotation event handlers for the specified row.
     *
     * @param {TextEditor} editor
     * @param {Number}     row
     * @param {Object}     annotationInfo
    ###
    registerAnnotationEventHandlers: (editor, row, annotationInfo) ->
        textEditorElement = atom.views.getView(editor)
        gutterContainerElement = textEditorElement.querySelector('.gutter-container')

        do (editor, gutterContainerElement, annotationInfo) =>
            longTitle = editor.getLongTitle()

            if longTitle not of @annotations
                @annotations[longTitle] = []

            @annotations[longTitle].push({
                line           : row
                annotationInfo : annotationInfo
            })

    ###*
     * Handles the mouse over event on an annotation.
     *
     * @param {Object}     event
     * @param {TextEditor} editor
     * @param {Object}     annotationInfo
    ###
    handleMouseOver: (event, editor, annotationInfo) ->
        if annotationInfo.tooltipText
            @removePopover()

            @attachedPopover = @service.createAttachedPopover(event.target)
            @attachedPopover.setText(annotationInfo.tooltipText)
            @attachedPopover.show()

    ###*
     * Handles the mouse out event on an annotation.
     *
     * @param {Object}     event
     * @param {TextEditor} editor
     * @param {Object}     annotationInfo
    ###
    handleMouseOut: (event, editor, annotationInfo) ->
        @removePopover()

    ###*
     * Handles the mouse click event on an annotation.
     *
     * @param {Object}     event
     * @param {TextEditor} editor
     * @param {Object}     annotationInfo
    ###
    handleMouseClick: (event, editor, annotationInfo) ->

    ###*
     * Removes the existing popover, if any.
    ###
    removePopover: () ->
        if @attachedPopover
            @attachedPopover.dispose()
            @attachedPopover = null

    ###*
     * Removes any annotations that were created with the specified key.
     *
     * @param {String} key
    ###
    removeAnnotationsByKey: (key) ->
        for i,marker of @markers[key]
            marker.destroy()

        @markers[key] = []
        @annotations[key] = []

    ###*
     * Removes any annotations (across all editors).
    ###
    removeAnnotations: () ->
        for key,markers of @markers
            @removeAnnotationsByKey(key)

        @markers = {}
        @annotations = {}

    ###*
     * Rescans the editor, updating all annotations.
     *
     * @param {TextEditor} editor The editor to search through.
    ###
    rescan: (editor) ->
        key = editor.getLongTitle()
        renamedKey = 'tmp_' + key

        # We rename the markers and remove them afterwards to prevent flicker if the location of the marker does not
        # change.
        if key of @annotations
            @annotations[renamedKey] = @annotations[key]
            @annotations[key] = []

        if key of @markers
            @markers[renamedKey] = @markers[key]
            @markers[key] = []

        result = @registerAnnotations(editor)

        if result?
            result.then () =>
                @removeAnnotationsByKey(renamedKey)
