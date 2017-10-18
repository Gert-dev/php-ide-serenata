{Disposable, CompositeDisposable} = require 'atom'

module.exports =

##*
# Provides signature help.
##
class SignatureHelpProvider
    ###*
     * The service (that can be used to query the source code and contains utility methods).
     *
     * @var {Object|null}
    ###
    service: null

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
            @removeSignatureHelp()

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

        @removeSignatureHelp()

    ###*
     * Registers the necessary event handlers.
     *
     * @param {TextEditor} editor TextEditor to register events to.
    ###
    registerEvents: (editor) ->
        @disposables.add editor.onDidChangeCursorPosition (event) =>
            # Only execute for the first cursor.
            cursors = editor.getCursors()

            return if event.cursor != cursors[0]

            @onChangeCursorPosition(editor, event.newBufferPosition)


    ###*
     * @param {TextEditor} editor
     * @param {Point}      newBufferPosition
    ###
    onChangeCursorPosition: (editor, newBufferPosition) ->
        @removeSignatureHelp()

        if @timeoutHandle?
            clearTimeout(@timeoutHandle)
            @timeoutHandle = null

        @timeoutHandle = setTimeout ( =>
            @timeoutHandle = null
            @showSignatureHelpAt(editor, newBufferPosition)
        ), 500


    ###*
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
    ###
    showSignatureHelpAt: (editor, bufferPosition) ->
        successHandler = (signatureHelp) =>
            return if not signatureHelp?

            @removeSignatureHelp()
            @showSignatureHelpForObject(editor, bufferPosition, signatureHelp)

        failureHandler = () =>
            @removeSignatureHelp()

        return @service.signatureHelpAt(editor, bufferPosition).then(successHandler, failureHandler)


    ###*
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     * @param {Object}     signatureHelp
    ###
    showSignatureHelpForObject: (editor, bufferPosition, signatureHelp) ->
        signature = signatureHelp.signatures[signatureHelp.activeSignature]

        text = signature.label

        parameterItems = []
        documentation = null

        for parameter,i in signature.parameters
            parameterText = ''

            if signatureHelp.activeParameter == i
                parameterText += '<span class="php-integrator-signature-help-active-argument">'

            else
                parameterText += '<span class="php-integrator-signature-help-inactive-argument">'

            parameterText += @prettifyLabel(parameter.label)
            parameterText += '</span>'

            parameterItems.push(parameterText)

        text += '(' + parameterItems.join(', ') + ')'

        if signature.parameters.length > 0 and signature.parameters[signatureHelp.activeParameter].documentation?
            text += '<br><br><p>' + signature.parameters[signatureHelp.activeParameter].documentation + '</p>'

        @showSignatureHelp(editor, bufferPosition, text)

    ###*
     * @param {String} text
     *
     * @return {String}
    ###
    prettifyLabel: (text) ->
        text = @prettifyType(text)
        text = @prettifyDefaultValue(text)

        return text

    ###*
     * @param {String} text
     *
     * @return {String}
    ###
    prettifyType: (text) ->
        return text.replace(/(.+?)( .+(?: = .+)?)/, '<span class="php-integrator-signature-help-type">$1</span>$2')

    ###*
     * @param {String} text
     *
     * @return {String}
    ###
    prettifyDefaultValue: (text) ->
        return text.replace(/ = (.+)/, '&nbsp;=&nbsp;<span class="keystroke php-integrator-signature-help-default-value">$1</span>')

    ###*
     * Shows the call tip at the specified location and editor with the specified text.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     * @param {String}     text
    ###
    showSignatureHelp: (editor, bufferPosition, text) ->
        @callTipMarker = editor.markBufferPosition(bufferPosition, {
            invalidate : 'touch'
        })

        rootDiv = document.createElement('div')
        rootDiv.className = 'tooltip bottom fade'
        rootDiv.style.opacity = 100
        rootDiv.style.fontSize = '1.0621em'

        innerDiv = document.createElement('div')
        innerDiv.className = 'tooltip-inner php-integrator-signature-help-wrapper'

        textDiv = document.createElement('div')
        textDiv.innerHTML = text

        innerDiv.appendChild(textDiv)
        rootDiv.appendChild(innerDiv)

        editor.decorateMarker(@callTipMarker, {
            type: 'overlay'
            class: 'php-integrator-signature-help'
            item: rootDiv
        })

    ###*
     * Removes the popover, if it is displayed.
    ###
    removeSignatureHelp: () ->
        if @callTipMarker
            @callTipMarker.destroy()
            @callTipMarker = null
