{CompositeDisposable} = require 'atom'

marked = require 'marked'

module.exports =

##*
# Provider of linter messages to the (indie) linter service.
##
class LinterProvider
    ###*
     * @var {Object}
    ###
    service: null

    ###*
     * @var {Object|null}
    ###
    indieLinter: null

    ###*
     * @var {Object}
    ###
    config: null

    ###*
     * Keeps track of whether a linting operation is currently running.
     *
     * @var {Boolean}
    ###
    isLintingInProgress: false

    ###*
     * Whether to ignore the next linting result.
     *
     * @var {Boolean}
    ###
    ignoreLintingResult: false

    ###*
     * The next editor to start a linting task for.
     *
     * @var {Object|null}
    ###
    nextEditor: null

    ###*
     * @var {CompositeDisposable}
    ###
    disposables: null

    ###*
     * Constructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->

    ###*
     * Initializes this provider.
     *
     * @param {Object} service
    ###
    activate: (@service) ->
        @disposables = new CompositeDisposable()

        @attachListeners(@service)

    ###*
     * Deactives the provider.
    ###
    deactivate: () ->
        @disposables.dispose()

    ###*
     * Sets the indie linter to use.
     *
     * @param {mixed} indieLinter
    ###
    setIndieLinter: (@indieLinter) ->
        @messages = []

    ###*
     * Attaches listeners for the specified base service.
     *
     * @param {Object} service
    ###
    attachListeners: (service) ->
        @disposables.add service.onDidFinishIndexing (response) =>
            editor = @findTextEditorByPath(response.path)

            return if not editor?
            return if not @indieLinter?

            @lint(editor)

        @disposables.add service.onDidFailIndexing (response) =>
            editor = @findTextEditorByPath(response.path)

            return if not editor?
            return if not @indieLinter?

            @lint(editor)

    ###*
     * @param {TextEditor} editor
     *
     * @return {Promise}
    ###
    lint: (editor) ->
        if @isLintingInProgress
            # This file is already being linted, but by the time it finishes, the results will be out of date and we
            # will then need to perform a new lint (we don't do it now to avoid spawning an excessive amount of
            # linting processes).
            @ignoreLintingResult = true
            @nextEditor = editor
            return

        @isLintingInProgress = true

        doneHandler = () =>
            ignoreResult = @ignoreLintingResult

            @isLintingInProgress = false
            @ignoreLintingResult = false

            if ignoreResult
                # The result was ignored because there is more recent data, run again.
                @lint(@nextEditor)

            return ignoreResult

        successHandler = (response) =>
            return if doneHandler()

            @processSuccess(editor, response)

        failureHandler = (response) =>
            return if doneHandler()

            @processFailure()

        return @invokeLint(editor.getPath(), editor.getBuffer().getText()).then(
            successHandler,
            failureHandler
        )

    ###*
     * @param {String} path
     * @param {String} source
     *
     * @return {Promise}
    ###
    invokeLint: (path, source) ->
        options = {
            noUnknownClasses         : not @config.get('showUnknownClasses')
            noUnknownMembers         : not @config.get('showUnknownMembers')
            noUnknownGlobalFunctions : not @config.get('showUnknownGlobalFunctions')
            noUnknownGlobalConstants : not @config.get('showUnknownGlobalConstants')
            noUnusedUseStatements    : not @config.get('showUnusedUseStatements')
            noDocblockCorrectness    : not @config.get('validateDocblockCorrectness')
            noMissingDocumentation   : not @config.get('showMissingDocs')
        }

        return @service.lint(path, source, options)

    ###*base/chang
     * @param {TextEditor} editor
     * @param {Object}     response
    ###
    processSuccess: (editor, response) ->
        return if not @indieLinter

        messages = []

        for item in response.errors
            messages.push @createLinterErrorMessageForOutputItem(editor, item)

        for item in response.warnings
            messages.push @createLinterWarningMessageForOutputItem(editor, item)

        @indieLinter.setMessages(messages)

    ###*
     *
    ###
    processFailure: () ->
        return if not @indieLinter

        @indieLinter.setMessages([])

    ###*
     * @param {TextEditor} editor
     * @param {Object}     item
     *
     * @return {Object}
    ###
    createLinterErrorMessageForOutputItem: (editor, item) ->
        return @createLinterMessageForOutputItem(editor, item, 'Error')

    ###*
     * @param {TextEditor} editor
     * @param {Object}     item
     *
     * @return {Object}
    ###
    createLinterWarningMessageForOutputItem: (editor, item) ->
        return @createLinterMessageForOutputItem(editor, item, 'Warning')

    ###*
     * @param {TextEditor} editor
     * @param {Object}     item
     * @param {String}     type
     *
     * @return {Object}
    ###
    createLinterMessageForOutputItem: (editor, item, type) ->
        text =  editor.getBuffer().getText()

        startCharacterOffset = @service.getCharacterOffsetFromByteOffset(item.start, text)
        endCharacterOffset   = @service.getCharacterOffsetFromByteOffset(item.end, text)

        startPoint = editor.getBuffer().positionForCharacterIndex(startCharacterOffset)
        endPoint   = editor.getBuffer().positionForCharacterIndex(endCharacterOffset)

        html = marked(item.message)

        if html?
            # Strip wrapping paragraph, linter doesn't seem to like it.
            html = html.substring('<p>'.length, html.length - '</p>'.length - 1)

        return {
            type     : type
            html     : html
            range    : [startPoint, endPoint]
            filePath : editor.getPath()
        }

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
