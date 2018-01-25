{Buffer} = require 'buffer'

Popover         = require './Widgets/Popover'
AttachedPopover = require './Widgets/AttachedPopover'

module.exports =

##*
# The service that is exposed to other packages.
##
class Service
    ###*
     * @var {Object}
    ###
    config: null

    ###*
     * @var {Object}
    ###
    proxy: null

    ###*
     * @var {Object}
    ###
    projectManager: null

    ###*
     * @var {Object}
    ###
    indexingMediator: null

    ###*
     * @var {Object}
    ###
    useStatementHelper: null

    ###*
     * Constructor.
     *
     * @param {AtomConfig}   config
     * @param {CachingProxy} proxy
     * @param {Object}       projectManager
     * @param {Object}       indexingMediator
     * @param {Object}       useStatementHelper
    ###
    constructor: (@config, @proxy, @projectManager, @indexingMediator, @useStatementHelper) ->

    ###*
     * Retrieves the use statement helper, which contains utility methods for dealing with use statements.
     *
     * @return {Object}
    ###
    getUseStatementHelper: () ->
        return @useStatementHelper

    ###*
     * Retrieves the settings (that are specific to this package) for the currently active project. If there is no
     * active project or the project does not have any settings, null is returned.
     *
     * @return {Object|null}
    ###
    getCurrentProjectSettings: () ->
        return @projectManager.getCurrentProjectSettings()

    ###*
     * Retrieves a list of available classes.
     *
     * @return {Promise}
    ###
    getClassList: () ->
        return @proxy.getClassList()

    ###*
     * Retrieves a list of available classes in the specified file.
     *
     * @param {String} file
     *
     * @return {Promise}
    ###
    getClassListForFile: (file) ->
        return @proxy.getClassListForFile(file)

    ###*
     * Retrieves a list of namespaces.
     *
     * @return {Promise}
    ###
    getNamespaceList: () ->
        return @proxy.getNamespaceList()

    ###*
     * Retrieves a list of namespaces in the specified file.
     *
     * @param {String} file
     *
     * @return {Promise}
    ###
    getNamespaceListForFile: (file) ->
        return @proxy.getNamespaceListForFile(file)

    ###*
     * Retrieves a list of available global constants.
     *
     * @return {Promise}
    ###
    getGlobalConstants: () ->
        return @proxy.getGlobalConstants()

    ###*
     * Retrieves a list of available global functions.
     *
     * @return {Promise}
    ###
    getGlobalFunctions: () ->
        return @proxy.getGlobalFunctions()

    ###*
     * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
     *
     * @param {String} className
     *
     * @return {Promise}
    ###
    getClassInfo: (className) ->
        return @proxy.getClassInfo(className)

    ###*
     * Resolves a local type in the specified file, based on use statements and the namespace.
     *
     * @param {String}  file
     * @param {Number}  line The line the type is located at. The first line is 1, not 0.
     * @param {String}  type
     * @param {String}  kind The kind of element. Either 'classlike', 'constant' or 'function'.
     *
     * @return {Promise}
    ###
    resolveType: (file, line, type, kind) ->
        return @proxy.resolveType(file, line, type, kind)

    ###*
     * Localizes a type to the specified file, making it relative to local use statements, if possible. If not possible,
     * null is returned.
     *
     * @param {String}  file
     * @param {Number}  line The line the type is located at. The first line is 1, not 0.
     * @param {String}  type
     * @param {String}  kind The kind of element. Either 'classlike', 'constant' or 'function'.
     *
     * @return {Promise}
    ###
    localizeType: (file, line, type, kind) ->
        return @proxy.localizeType(file, line, type, kind)

    ###*
     * Lints the specified file.
     *
     * @param {String}      file
     * @param {String|null} source  The source code of the file to index. May be null if a directory is passed instead.
     * @param {Object}      options Additional options to set. Boolean properties noUnknownClasses, noUnknownMembers,
     *                              noUnknownGlobalFunctions, noUnknownGlobalConstants, noDocblockCorrectness,
     *                              noUnusedUseStatements and noMissingDocumentation are supported.
     *
     * @return {CancellablePromise}
    ###
    lint: (file, source, options = {}) ->
        return @proxy.lint(file, source, options)

    ###*
     * Fetches all available variables at a specific location.
     *
     * @param {String}      file   The path to the file to examine. May be null if the source parameter is passed.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {Promise}
    ###
    getAvailableVariablesByOffset: (file, source, offset) ->
        return @proxy.getAvailableVariables(file, source, offset)

    ###*
     * Deduces the resulting types of an expression.
     *
     * @param {String|null} expression        The expression to deduce the type of, e.g. '$this->foo()'. If null, the
     *                                        expression just before the specified offset will be used.
     * @param {String}      file              The path to the file to examine.
     * @param {String|null} source            The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset            The character offset into the file to examine.
     * @param {bool}        ignoreLastElement Whether to remove the last element or not, this is useful when the user
     *                                        is still writing code, e.g. "$this->foo()->b" would normally return the
     *                                        type (class) of 'b', as it is the last element, but as the user is still
     *                                        writing code, you may instead be interested in the type of 'foo()'
     *                                        instead.
     *
     * @return {Promise}
    ###
    deduceTypes: (expression, file, source, offset, ignoreLastElement) ->
        return @proxy.deduceTypes(expression, file, source, offset, ignoreLastElement)

    ###*
     * Retrieves autocompletion suggestions for a specific location.
     *
     * @param {Number}      offset            The character offset into the file to examine.
     * @param {String}      file              The path to the file to examine.
     * @param {String|null} source            The source code to search. May be null if a file is passed instead.
     *
     * @return {CancellablePromise}
    ###
    autocomplete: (offset, file, source) ->
        return @proxy.autocomplete(offset, file, source)

    ###*
     * Fetches the contents of the tooltip to display at the specified offset.
     *
     * @param {String}     file   The path to the file to examine.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {CancellablePromise}
    ###
    tooltip: (file, source, offset) ->
        return @proxy.tooltip(file, source, offset)

    ###*
     * Fetches signature help for a method or function call.
     *
     * @param {String}      file   The path to the file to examine.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {CancellablePromise}
    ###
    signatureHelp: (file, source, offset) ->
        return @proxy.signatureHelp(file, source, offset)

    ###*
     * Fetches definition information for code navigation purposes of the structural element at the specified location.
     *
     * @param {String}      file   The path to the file to examine.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {CancellablePromise}
    ###
    gotoDefinition: (file, source, offset) ->
        return @proxy.gotoDefinition(file, source, offset)

    ###*
     * Convenience alias for {@see deduceTypes}.
     *
     * @param {String}     expression
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     *
     * @return {Promise}
    ###
    deduceTypesAt: (expression, editor, bufferPosition) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = editor.getBuffer().getText()

        return @deduceTypes(expression, editor.getPath(), bufferText, offset)

    ###*
     * Convenience alias for {@see autocomplete}.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     *
     * @return {CancellablePromise}
    ###
    autocompleteAt: (editor, bufferPosition) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = editor.getBuffer().getText()

        return @autocomplete(offset, editor.getPath(), bufferText)

    ###*
     * Convenience alias for {@see tooltip}.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     *
     * @return {Promise}
    ###
    tooltipAt: (editor, bufferPosition) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = editor.getBuffer().getText()

        return @tooltip(editor.getPath(), bufferText, offset)

    ###*
     * Convenience alias for {@see signatureHelp}.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     *
     * @return {CancellablePromise}
    ###
    signatureHelpAt: (editor, bufferPosition) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = editor.getBuffer().getText()

        return @signatureHelp(editor.getPath(), bufferText, offset)

    ###*
     * Convenience alias for {@see gotoDefinition}.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     *
     * @return {Promise}
    ###
    gotoDefinitionAt: (editor, bufferPosition) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = editor.getBuffer().getText()

        return @gotoDefinition(editor.getPath(), bufferText, offset)

    ###*
     * Refreshes the specified file or folder. This method is asynchronous and will return immediately.
     *
     * @param {String|Array}  path                  The full path to the file  or folder to refresh. Alternatively,
     *                                              this can be a list of items to index at the same time.
     * @param {String|null}   source                The source code of the file to index. May be null if a directory is
     *                                              passed instead.
     * @param {Array}         excludedPaths         A list of paths to exclude from indexing.
     * @param {Array}         fileExtensionsToIndex A list of file extensions (without leading dot) to index.
     *
     * @return {Promise}
    ###
    reindex: (path, source, excludedPaths, fileExtensionsToIndex) ->
        return @indexingMediator.reindex(path, source, excludedPaths, fileExtensionsToIndex)

    ###*
     * Initializes a project.
     *
     * @return {Promise}
    ###
    initialize: () ->
        return @indexingMediator.initialize()

    ###*
     * Vacuums a project, cleaning up the index database (e.g. pruning files that no longer exist).
     *
     * @return {Promise}
    ###
    vacuum: () ->
        return @indexingMediator.vacuum()

    ###*
     * Attaches a callback to indexing started event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains a 'path' property.
     *
     * @return {Disposable}
    ###
    onDidStartIndexing: (callback) ->
        return @indexingMediator.onDidStartIndexing(callback)

    ###*
     * Attaches a callback to indexing progress event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains a 'path' and a 'percentage'
     *                            property.
     *
     * @return {Disposable}
    ###
    onDidIndexingProgress: (callback) ->
        return @indexingMediator.onDidIndexingProgress(callback)

    ###*
     * Attaches a callback to indexing finished event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains an 'output' and a 'path' property.
     *
     * @return {Disposable}
    ###
    onDidFinishIndexing: (callback) ->
        return @indexingMediator.onDidFinishIndexing(callback)

    ###*
     * Attaches a callback to indexing failed event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains an 'error' and a 'path' property.
     *
     * @return {Disposable}
    ###
    onDidFailIndexing: (callback) ->
        return @indexingMediator.onDidFailIndexing(callback)

    ###*
     * Determines the current class' FQCN based on the specified buffer position.
     *
     * @param {TextEditor} editor         The editor that contains the class (needed to resolve relative class names).
     * @param {Point}      bufferPosition
     *
     * @return {Promise}
    ###
    determineCurrentClassName: (editor, bufferPosition) ->
        return new Promise (resolve, reject) =>
            path = editor.getPath()

            if not path?
                reject()
                return

            successHandler = (classesInFile) =>
                for name,classInfo of classesInFile
                    if bufferPosition.row >= classInfo.startLine and bufferPosition.row <= classInfo.endLine
                        resolve(name)

                resolve(null)

            failureHandler = () =>
                reject()

            return @getClassListForFile(path).then(successHandler, failureHandler)

    ###*
     * Determines the current namespace on the specified buffer position.
     *
     * @param {TextEditor} editor         The editor that contains the class (needed to resolve relative class names).
     * @param {Point}      bufferPosition
     *
     * @return {Promise}
    ###
    determineCurrentNamespaceName: (editor, bufferPosition) ->
        return new Promise (resolve, reject) =>
            path = editor.getPath()

            if not path?
                reject()
                return

            successHandler = (namespacesInFile) =>
                for id,namespace of namespacesInFile
                    if bufferPosition.row >= namespace.startLine and bufferPosition.row <= namespace.endLine
                        resolve(namespace.name)
                        return

                resolve(null)

            failureHandler = () =>
                reject()

            return @getNamespaceListForFile(path).then(successHandler, failureHandler)

    ###*
     * Convenience function that resolves types using {@see resolveType}, automatically determining the correct
     * parameters for the editor and buffer position.
     *
     * @param {TextEditor} editor         The editor.
     * @param {Point}      bufferPosition The location of the type.
     * @param {String}     type           The (local) type to resolve.
     * @param {String}  kind The kind of element. Either 'classlike', 'constant' or 'function'.
     *
     * @return {Promise}
     *
     * @example In a file with namespace A\B, determining C could lead to A\B\C.
    ###
    resolveTypeAt: (editor, bufferPosition, type, kind) ->
        return @resolveType(editor.getPath(), bufferPosition.row + 1, type, kind)

    ###*
     * Retrieves all variables that are available at the specified buffer position.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     *
     * @return {Promise}
    ###
    getAvailableVariables: (editor, bufferPosition) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        return @getAvailableVariablesByOffset(editor.getPath(), editor.getBuffer().getText(), offset)

    ###*
     * Retrieves the types that are being used (called) at the specified location in the buffer. Note that this does not
     * guarantee that the returned types actually exist. You can use {@see getClassInfo} on the returned class name
     * to check for this instead.
     *
     * @param {TextEditor} editor            The text editor to use.
     * @param {Point}      bufferPosition    The cursor location of the item, such as the class member. Note that this
     *                                       should always be at the end of the actual member (i.e. just after it).
     *                                       If you want to ignore the element at the buffer position itself, see
     *                                       'ignoreLastElement'.
     * @param {boolean}    ignoreLastElement Whether to remove the last element or not, this is useful when the user
     *                                       is still writing code, e.g. "$this->foo()->b" would normally return the
     *                                       type (class) of 'b', as it is the last element, but as the user is still
     *                                       writing code, you may instead be interested in the type of 'foo()' instead.
     *
     * @return {Promise}
     *
     * @example Invoking it on MyMethod::foo()->bar() will ask what class 'bar' is invoked on, which will whatever types
     *          foo returns.
    ###
    getResultingTypesAt: (editor, bufferPosition, ignoreLastElement) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = editor.getBuffer().getText()

        return @deduceTypes(null, editor.getPath(), bufferText, offset, true)

    ###*
     * Retrieves the call stack of the function or method that is being invoked at the specified position. This can be
     * used to fetch information about the function or method call the cursor is in.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     *
     * @return {Promise} With elements 'callStack' (array) as well as 'argumentIndex' which denotes the argument in the
     *                   parameter list the position is located at. Returns 'null' if not in a method or function call.
     *
     * @example "$this->test(1, function () {},| 2);" (where the vertical bar denotes the cursor position) will yield
     *          ['$this', 'test'].
    ###
    getInvocationInfoAt: (editor, bufferPosition) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = editor.getBuffer().getText()

        return @getInvocationInfo(editor.getPath(), bufferText, offset)

    ###*
     * Creates a popover with the specified constructor arguments.
    ###
    createPopover: () ->
        return new Popover(arguments...)

    ###*
     * Creates an attached popover with the specified constructor arguments.
    ###
    createAttachedPopover: () ->
        return new AttachedPopover(arguments...)

    ###*
     * Indicates if the specified type is a basic type (e.g. int, array, object, etc.).
     *
     * @param {String} type
     *
     * @return {boolean}
    ###
    isBasicType: (type) ->
        return /^(string|int|bool|float|object|mixed|array|resource|void|null|callable|false|true|self|static|parent|\$this)$/i.test(type)

    ###*
     * Utility function to convert byte offsets returned by the service into character offsets.
     *
     * @param {Number} byteOffset
     * @param {String} string
     *
     * @return {Number}
    ###
    getCharacterOffsetFromByteOffset: (byteOffset, string) ->
        buffer = new Buffer(string)

        return buffer.slice(0, byteOffset).toString().length

    ###*
     * @param {String} fqcn
     *
     * @return {String}
    ###
    getDocumentationUrlForClass: (fqcn) ->
        return @config.get('php_documentation_base_urls').classes + @getNormalizedFqcnDocumentationUrl(fqcn)

    ###*
     * @param {String} fqcn
     *
     * @return {String}
    ###
    getDocumentationUrlForFunction: (fqcn) ->
        return @config.get('php_documentation_base_urls').functions + @getNormalizedFqcnDocumentationUrl(fqcn)

    ###*
     * @param {String} classlikeFqcn
     * @param {String} name
     *
     * @return {String}
    ###
    getDocumentationUrlForClassMethod: (classlikeFqcn, name) ->
        return @config.get('php_documentation_base_urls').root + @getNormalizedFqcnDocumentationUrl(classlikeFqcn) + '.' + @getNormalizeMethodDocumentationUrl(name)

    ###*
     * @param {String} name
     *
     * @return {String}
    ###
    getNormalizedFqcnDocumentationUrl: (name) ->
        if name.length > 0 and name[0] == '\\'
            name = name.substr(1)

        return name.replace(/\\/g, '-').toLowerCase()

    ###*
     * @param {String} name
     *
     * @return {String}
    ###
    getNormalizeMethodDocumentationUrl: (name) ->
        return name.replace(/^__/, '')
