{Range} = require 'atom'

AbstractProvider = require './AbstractProvider'

module.exports =

##*
# Provides method extraction capabilities.
##
class ExtractMethodProvider extends AbstractProvider
    ###*
     * View that the user interacts with when extracting code.
     *
     * @type {Object}
    ###
    view: null

    ###*
     * Builder used to generate the new method.
     *
     * @type {Object}
    ###
    builder: null

    ###*
     * @param {Object} builder
    ###
    constructor: (@builder) ->

    ###*
     * @inheritdoc
    ###
    activate: (service) ->
        super(service)

        atom.commands.add 'atom-text-editor', "php-ide-serenata:extract-method": =>
            @executeCommand()

    ###*
     * @inheritdoc
    ###
    deactivate: () ->
        super()

        if @view
            @view.destroy()
            @view = null

    ###*
     * @inheritdoc
    ###
    getIntentionProviders: () ->
        return [{
            grammarScopes: ['source.php']
            getIntentions: ({textEditor, bufferPosition}) =>
                activeTextEditor = atom.workspace.getActiveTextEditor()

                return [] if not activeTextEditor
                return [] if not @getCurrentProjectPhpVersion()?

                selection = activeTextEditor.getSelectedBufferRange()

                # Checking if a selection has been made
                if selection.start.row == selection.end.row and selection.start.column == selection.end.column
                    return []

                return [
                    {
                        priority : 200
                        icon     : 'git-branch'
                        title    : 'Extract Method'

                        selected : () =>
                            @executeCommand()
                    }
                ]
        }]

    ###*
     * Executes the extraction.
    ###
    executeCommand: () ->
        activeTextEditor = atom.workspace.getActiveTextEditor()

        return if not activeTextEditor

        tabText = activeTextEditor.getTabText()

        selection = activeTextEditor.getSelectedBufferRange()

        # Checking if a selection has been made
        if selection.start.row == selection.end.row and selection.start.column == selection.end.column
            atom.notifications.addInfo('Serenata', {
                detail: 'Please select the code to extract and try again.'
            })

            return

        line = activeTextEditor.lineTextForBufferRow(selection.start.row)

        findSingleTab = new RegExp("(#{tabText})", "g")

        matches = (line.match(findSingleTab) || []).length

        # If the first line doesn't have any tabs then add one.
        highlightedText = activeTextEditor.getTextInBufferRange(selection)
        selectedBufferFirstLine = highlightedText.split("\n")[0]

        if (selectedBufferFirstLine.match(findSingleTab) || []).length == 0
            highlightedText = "#{tabText}" + highlightedText

        # Replacing double indents with one, so it can be shown in the preview area of panel.
        multipleTabTexts = Array(matches).fill("#{tabText}")
        findMultipleTab = new RegExp("^" + multipleTabTexts.join(''), "mg")
        reducedHighlightedText = highlightedText.replace(findMultipleTab, "#{tabText}")

        @builder.setEditor(activeTextEditor)
        @builder.setMethodBody(reducedHighlightedText)

        @getView().storeFocusedElement()
        @getView().present()

    ###*
     * Called when the user has cancel the extraction in the modal.
    ###
    onCancel: ->
        @builder.cleanUp()

    ###*
     * Called when the user has confirmed the extraction in the modal.
     *
     * @param  {Object} settings
     *
     * @see ParameterParser.buildMethod for structure of settings
    ###
    onConfirm: (settings) ->
        successHandler = (methodCall) =>
            activeTextEditor = atom.workspace.getActiveTextEditor()

            selectedBufferRange = activeTextEditor.getSelectedBufferRange()

            highlightedBufferPosition = selectedBufferRange.end

            row = 0

            loop
                row++
                descriptions = activeTextEditor.scopeDescriptorForBufferPosition(
                    [highlightedBufferPosition.row + row, activeTextEditor.getTabLength()]
                )
                indexOfDescriptor = descriptions.scopes.indexOf('punctuation.section.scope.end.php')
                break if indexOfDescriptor > -1 || row == activeTextEditor.getLineCount()

            row = highlightedBufferPosition.row + row

            line = activeTextEditor.lineTextForBufferRow row

            endOfLine = line?.length

            replaceRange = [
                [row, 0],
                [row, endOfLine]
            ]

            previousText = activeTextEditor.getTextInBufferRange replaceRange

            settings.tabs = true

            nestedSuccessHandler = (newMethodBody) =>
                settings.tabs = false

                @builder.cleanUp()

                activeTextEditor.transact () =>
                    # Matching current indentation
                    selectedText = activeTextEditor.getSelectedText()
                    spacing = selectedText.match /^\s*/
                    if spacing != null
                        spacing = spacing[0]

                    activeTextEditor.insertText(spacing + methodCall)

                    # Remove any extra new lines between functions
                    nextLine = activeTextEditor.lineTextForBufferRow row + 1
                    if nextLine == ''
                        activeTextEditor.setSelectedBufferRange(
                            [
                                [row + 1, 0],
                                [row + 1, 1]
                            ]
                        )
                        activeTextEditor.deleteLine()


                    # Re working out range as inserting method call will delete some
                    # lines and thus offsetting this
                    row -= selectedBufferRange.end.row - selectedBufferRange.start.row

                    if @snippetManager?
                        activeTextEditor.setCursorBufferPosition [row + 1, 0]

                        body = "\n#{newMethodBody}"

                        result = @getTabStopsForBody body

                        snippet = {
                            body: body,
                            lineCount: result.lineCount,
                            tabStops: result.tabStops
                        }

                        @snippetManager.insertSnippet(
                            snippet,
                            activeTextEditor
                        )
                    else
                        # Re working out range as inserting method call will delete some
                        # lines and thus offsetting this
                        row -= selectedBufferRange.end.row - selectedBufferRange.start.row

                        replaceRange = [
                            [row, 0],
                            [row, line?.length]
                        ]

                        activeTextEditor.setTextInBufferRange(
                            replaceRange,
                            "#{previousText}\n\n#{newMethodBody}"
                        )

            nestedFailureHandler = () =>
                settings.tabs = false

            @builder.buildMethod(settings).then(nestedSuccessHandler, nestedFailureHandler)

        failureHandler = () =>
            # Do nothing.

        @builder.buildMethodCall(settings.methodName).then(successHandler, failureHandler)

    ###*
     * Gets all the tab stops and line count for the body given
     *
     * @param  {String} body
     *
     * @return {Object}
    ###
    getTabStopsForBody: (body) ->
        lines = body.split "\n"
        row = 0
        lineCount = 0
        tabStops = []
        tabStopIndex = {}

        for line in lines
            regex = /(\[[\w ]*?\])(\s*\$[a-zA-Z0-9_]+)?/g
            # Get tab stops by looping through all matches
            while (match = regex.exec(line)) != null
                key = match[2] # 2nd capturing group (variable name)
                replace = match[1] # 1st capturing group ([type])
                range = new Range(
                    [row, match.index],
                    [row, match.index + match[1].length]
                )

                if key != undefined
                    key = key.trim()
                    if tabStopIndex[key] != undefined
                        tabStopIndex[key].push range
                    else
                        tabStopIndex[key] = [range]
                else
                    tabStops.push [range]

            row++
            lineCount++

        for objectKey in Object.keys(tabStopIndex)
            tabStops.push tabStopIndex[objectKey]

        tabStops = tabStops.sort @sortTabStops

        return {
            tabStops: tabStops,
            lineCount: lineCount
        }

    ###*
     * Sorts the tab stops by their row and column
     *
     * @param  {Array} a
     * @param  {Array} b
     *
     * @return {Integer}
    ###
    sortTabStops: (a, b) ->
        # Grabbing first range in the array
        a = a[0]
        b = b[0]

        # b is before a in the rows
        if a.start.row > b.start.row
            return 1

        # a is before b in the rows
        if a.start.row < b.start.row
            return -1

        # On same line but b is before a
        if a.start.column > b.start.column
            return 1

        # On same line but a is before b
        if a.start.column < b.start.column
            return -1

        # Same position
        return 0

    ###*
     * @return {View}
    ###
    getView: () ->
        if not @view?
            View = require './ExtractMethodProvider/View'

            @view = new View(@onConfirm.bind(this), @onCancel.bind(this))
            @view.setBuilder(@builder)

        return @view
