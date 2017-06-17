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

            @lint(editor, response.source)

        @disposables.add service.onDidFailIndexing (response) =>
            editor = @findTextEditorByPath(response.path)

            return if not editor?
            return if not @indieLinter?

            @lint(editor, response.source)

    ###*
     * @param {TextEditor} editor
     * @param {String}     source
     *
     * @return {Promise}
    ###
    lint: (editor, source) ->
        successHandler = (response) =>
            return @processSuccess(editor, response, source)

        failureHandler = (response) =>
            return @processFailure(editor)

        return @invokeLint(editor.getPath(), source).then(
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
            noUnknownClasses         : not @config.get('linting.showUnknownClasses')
            noUnknownMembers         : not @config.get('linting.showUnknownMembers')
            noUnknownGlobalFunctions : not @config.get('linting.showUnknownGlobalFunctions')
            noUnknownGlobalConstants : not @config.get('linting.showUnknownGlobalConstants')
            noUnusedUseStatements    : not @config.get('linting.showUnusedUseStatements')
            noDocblockCorrectness    : not @config.get('linting.validateDocblockCorrectness')
            noMissingDocumentation   : not @config.get('linting.showMissingDocs')
        }

        return @service.lint(path, source, options)

    ###*
     * @param {TextEditor} editor
     * @param {Object}     response
     * @param {String}     source
     *
     * @return {Array}
    ###
    processSuccess: (editor, response, source) ->
        messages = []

        for item in response.errors
            messages.push @createLinterErrorMessageForOutputItem(editor, item, source)

        for item in response.warnings
            messages.push @createLinterWarningMessageForOutputItem(editor, item, source)

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
     * @param {String}     source
     *
     * @return {Object}
    ###
    createLinterErrorMessageForOutputItem: (editor, item, source) ->
        return @createLinterMessageForOutputItem(editor, item, source, 'error')

    ###*
     * @param {TextEditor} editor
     * @param {Object}     item
     * @param {String}     source
     *
     * @return {Object}
    ###
    createLinterWarningMessageForOutputItem: (editor, item, source) ->
        return @createLinterMessageForOutputItem(editor, item, source, 'warning')

    ###*
     * @param {TextEditor} editor
     * @param {Object}     item
     * @param {String}     source
     * @param {String}     severity
     *
     * @return {Object}
    ###
    createLinterMessageForOutputItem: (editor, item, source, severity) ->
        startCharacterOffset = @service.getCharacterOffsetFromByteOffset(item.start, source)
        endCharacterOffset   = @service.getCharacterOffsetFromByteOffset(item.end, source)

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
