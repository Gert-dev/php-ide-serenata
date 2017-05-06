{CompositeDisposable} = require 'atom'

module.exports =

##*
# Provider of linter messages to the (indie) linter service.
##
class LinterProvider
    ###*
     * @var {String}
    ###
    scope: 'file'

    ###*
     * @var {Boolean}
    ###
    lintsOnChange: true

    ###*
     * @var {Array}
    ###
    grammarScopes: ['source.php']

    ###*
     * @var {Object}
    ###
    service: null

    ###*
     * @var {Object}
    ###
    config: null

    ###*
     * @var {CompositeDisposable}
    ###
    disposables: null

    ###*
     * @var {Object}
    ###
    indieLinter: null

    ###*
     * Constructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->

    ###*
     * @param {Object} indieLinter
    ###
    setIndieLinter: (@indieLinter) ->
        @messages = []

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
        successHandler = (response) =>
            return @processSuccess(editor, response)

        failureHandler = (response) =>
            return @processFailure(editor)

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

    ###*
     * @param {TextEditor} editor
     * @param {Object}     response
     *
     * @return {Array}
    ###
    processSuccess: (editor, response) ->
        messages = []

        for item in response.errors
            messages.push @createLinterErrorMessageForOutputItem(editor, item)

        for item in response.warnings
            messages.push @createLinterWarningMessageForOutputItem(editor, item)

        @indieLinter.setMessages(editor.getPath(), messages)

    ###*
     * @param {TextEditor} editor
     *
     * @return {Array}
    ###
    processFailure: (editor) ->
        @indieLinter.setMessages(editor.getPath(), [])

    ###*
     * @param {TextEditor} editor
     * @param {Object}     item
     *
     * @return {Object}
    ###
    createLinterErrorMessageForOutputItem: (editor, item) ->
        return @createLinterMessageForOutputItem(editor, item, 'error')

    ###*
     * @param {TextEditor} editor
     * @param {Object}     item
     *
     * @return {Object}
    ###
    createLinterWarningMessageForOutputItem: (editor, item) ->
        return @createLinterMessageForOutputItem(editor, item, 'warning')

    ###*
     * @param {TextEditor} editor
     * @param {Object}     item
     * @param {String}     severity
     *
     * @return {Object}
    ###
    createLinterMessageForOutputItem: (editor, item, severity) ->
        text =  editor.getBuffer().getText()

        startCharacterOffset = @service.getCharacterOffsetFromByteOffset(item.start, text)
        endCharacterOffset   = @service.getCharacterOffsetFromByteOffset(item.end, text)

        startPoint = editor.getBuffer().positionForCharacterIndex(startCharacterOffset)
        endPoint   = editor.getBuffer().positionForCharacterIndex(endCharacterOffset)

        return {
            excerpt  : item.message
            severity : severity

            location:
                file     : editor.getPath()
                position : [startPoint, endPoint]
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
            if textEditor and textEditor.getPath() == path
                return textEditor

        return null
