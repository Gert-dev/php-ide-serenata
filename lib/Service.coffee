Popover         = require './Widgets/Popover'
AttachedPopover = require './Widgets/AttachedPopover'

module.exports =

##*
# The service that is exposed to other packages.
##
class Service
    ###*
     * The proxy to use to contact the PHP side.
    ###
    proxy: null

    ###*
     * The parser to use to query the source code.
    ###
    parser: null

    ###*
     * The emitter to use to emit indexing events.
    ###
    indexingEventEmitter: null

    ###*
     * Constructor.
     *
     * @param {CachingProxy} proxy
     * @param {Parser}       parser
     * @param {Emitter}      indexingEventEmitter
    ###
    constructor: (@proxy, @parser, @indexingEventEmitter) ->

    ###*
     * Clears the autocompletion cache. Most fetching operations such as fetching constants, autocompletion, fetching
     * members, etc. are cached when they are first retrieved. This clears the cache, forcing them to be retrieved
     # again. Clearing the cache is automatically performed, so this method is usually unnecessary.
    ###
    clearCache: () ->
        @proxy.clearCache()

    ###*
     * Retrieves a list of available classes.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    getClassList: (async = false) ->
        return @proxy.getClassList(async)

    ###*
     * Retrieves a list of available classes in the specified file.
     *
     * @param {string}  file
     * @param {boolean} async
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    getClassListForFile: (file, async = false) ->
        return @proxy.getClassListForFile(file, async)

    ###*
     * Retrieves a list of available global constants.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    getGlobalConstants: (async = false) ->
        return @proxy.getGlobalConstants(async)

    ###*
     * Retrieves a list of available global functions.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    getGlobalFunctions: (async = false) ->
        return @proxy.getGlobalFunctions(async)

    ###*
     * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
     *
     * @param {string}  className
     * @param {boolean} async
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    getClassInfo: (className, async = false) ->
        return @proxy.getClassInfo(className, async)

    ###*
     * Resolves a local type in the specified file, based on use statements and the namespace.
     *
     * @param {string}  file
     * @param {number}  line  The line the type is located at. The first line is 1, not 0.
     * @param {string}  type
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    resolveType: (file, line, type, async = false) ->
        return @proxy.resolveType(file, line, type, async)

    ###*
     * Performs a semantic lint of the specified file.
     *
     * @param {string}      file
     * @param {string|null} source The source code of the file to index. May be null if a directory is passed instead.
     * @param {boolean}     async
     *
     * @return {Promise|Object}
    ###
    semanticLint: (file, source, async = false) ->
        return @proxy.semanticLint(file, source, async)

    ###*
     * Fetches all available variables at a specific location.
     *
     * @param {string|null} file   The path to the file to examine. May be null if the source parameter is passed.
     * @param {string|null} source The source code to search. May be null if a file is passed instead.
     * @param {number}      offset The character offset into the file to examine.
     * @param {boolean}     async
     *
     * @return {Promise|Object}
    ###
    getAvailableVariablesByOffset: (file, source, offset, async = false) ->
        return @proxy.getAvailableVariables(file, source, offset, async)

    ###*
     * Fetches the type of the specified variable at the specified location.
     *
     * @param {string}      name   The variable to fetch, including its leading dollar sign.
     * @param {string}      file   The path to the file to examine. May be null if the source parameter is passed.
     * @param {string|null} source The source code to search. May be null if a file is passed instead.
     * @param {number}      offset The character offset into the file to examine.
     * @param {boolean}     async
     *
     * @return {Promise|Object}
    ###
    getVariableTypeByOffset: (name, file, source, offset, async = false) ->
        return @proxy.getVariableType(name, file, source, offset, async)

    ###*
     * Deduces the resulting type of an expression based on its parts.
     *
     * @param {array}       parts  One or more strings that are part of the expression, e.g. ['$this', 'foo()'].
     * @param {string|null} file   The path to the file to examine. May be null if the source parameter is passed.
     * @param {string|null} source The source code to search. May be null if a file is passed instead.
     * @param {number}      offset The character offset into the file to examine.
     * @param {boolean}     async
     *
     * @return {Promise|Object}
    ###
    deduceType: (parts, file, source, offset, async = false) ->
        return @proxy.deduceType(parts, file, source, offset, async)

    ###*
     * Refreshes the specified file or folder. This method is asynchronous and will return immediately.
     *
     * @param {string}      path                   The full path to the file  or folder to refresh.
     * @param {string|null} source                 The source code of the file to index. May be null if a directory is
     *                                             passed instead.
     * @param {Callback}    progressStreamCallback A method to invoke each time progress streaming data is received.
     *
     * @return {Promise}
    ###
    reindex: (path, source, progressStreamCallback) ->
        return new Promise (resolve, reject) =>
            successHandler = (output) =>
                @indexingEventEmitter.emit('php-integrator-base:indexing-finished', {
                    output : output
                    path   : path
                })

                resolve(output)

            failureHandler = (error) =>
                @indexingEventEmitter.emit('php-integrator-base:indexing-failed', {
                    error : error
                    path  : path
                })

                reject(error)

            return @proxy.reindex(path, source, progressStreamCallback).then(successHandler, failureHandler)

    ###*
     * Attaches a callback to indexing finished event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains an 'output' and a 'path' property.
     *
     * @return {Disposable}
    ###
    onDidFinishIndexing: (callback) ->
        @indexingEventEmitter.on('php-integrator-base:indexing-finished', callback)

    ###*
     * Attaches a callback to indexing failed event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains an 'error' and a 'path' property.
     *
     * @return {Disposable}
    ###
    onDidFailIndexing: (callback) ->
        @indexingEventEmitter.on('php-integrator-base:indexing-failed', callback)

    ###*
     * Determines the current class' FQCN based on the specified buffer position.
     *
     * @param {TextEditor} editor         The editor that contains the class (needed to resolve relative class names).
     * @param {Point}      bufferPosition
     * @param {boolean}    async
     *
     * @return {Promise|string|null}
    ###
    determineCurrentClassName: (editor, bufferPosition, async = false) ->
        path = editor.getPath()

        if not async
            classesInFile = @proxy.getClassListForFile(editor.getPath())

            for name,classInfo of classesInFile
                if bufferPosition.row >= classInfo.startLine and bufferPosition.row <= classInfo.endLine
                    return name

            return null

        return new Promise (resolve, reject) =>
            path = editor.getPath()

            if not path?
                reject()
                return

            return @proxy.getClassListForFile(path, true).then (classesInFile) =>
                for name,classInfo of classesInFile
                    if bufferPosition.row >= classInfo.startLine and bufferPosition.row <= classInfo.endLine
                        resolve(name)

                resolve(null)

    ###*
     * Convenience function that resolves types using {@see resolveType}, automatically determining the correct
     * parameters for the editor and buffer position.
     *
     * @param {TextEditor} editor         The editor.
     * @param {Point}      bufferPosition The location of the type.
     * @param {string}     type           The (local) type to resolve.
     * @param {boolean}    async
     *
     * @return {Promise|string|null}
     *
     * @example In a file with namespace A\B, determining C could lead to A\B\C.
    ###
    resolveTypeAt: (editor, bufferPosition, type, async = false) ->
        return @resolveType(editor.getPath(), bufferPosition.row + 1, type, async)

    ###*
     * Retrieves all variables that are available at the specified buffer position.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     * @param {bool}       async
     *
     * @return {Promise|Object}
    ###
    getAvailableVariables: (editor, bufferPosition, async = false) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        return @getAvailableVariablesByOffset(editor.getPath(), editor.getBuffer().getText(), offset, async)

    ###*
     * Retrieves the type of a variable, relative to the context at the specified buffer location. Class names will
     * be returned in their full form (full class name, but not necessarily with a leading slash).
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     * @param {string}     name
     * @param {boolean}    async
     *
     * @return {Promise|string|null}
    ###
    getVariableType: (editor, bufferPosition, name, async = false) ->
        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = null

        if async
            bufferText = editor.getBuffer().getText()

        result = @getVariableType(name, editor.getPath(), bufferText, offset, async)

        return null if not result
        return result

    ###*
     * Retrieves the class that is being used (called) at the specified location in the buffer. Note that this does not
     * guarantee that the returned class actually exists. You can use {@see getClassInfo} on the returned class name
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
     * @param {boolean}    async
     *
     * @throws an error if one of the elements in the call stack does not exist, which can happen if the user is writing
     *         invalid code.
     *
     * @return {Promise|string|null}
     *
     * @example Invoking it on MyMethod::foo()->bar() will ask what class 'bar' is invoked on, which will whatever type
     *          foo returns.
    ###
    getResultingTypeAt: (editor, bufferPosition, ignoreLastElement, async = false) ->
        callStack = @parser.retrieveSanitizedCallStackAt(editor, bufferPosition)

        if ignoreLastElement
            callStack.pop();

        return null if not callStack or callStack.length == 0

        offset = editor.getBuffer().characterIndexForPosition(bufferPosition)

        bufferText = null

        if async
            bufferText = editor.getBuffer().getText()

        return @deduceType(callStack, editor.getPath(), bufferText, offset, async)

    ###*
     * Retrieves the call stack of the function or method that is being invoked at the specified position. This can be
     * used to fetch information about the function or method call the cursor is in.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     * @param {boolean}    async
     *
     * @example "$this->test(1, function () {},| 2);" (where the vertical bar denotes the cursor position) will yield
     *          ['$this', 'test'].
     *
     * @return {Promise"Object|null} With elements 'callStack' (array) as well as 'argumentIndex' which denotes the
     *                               argument in the parameter list the position is located at. Returns 'null' if not
     *                               in a method or function call.
    ###
    getInvocationInfoAt: (editor, bufferPosition, async = false) ->
        result = @parser.getInvocationInfoAt(editor, bufferPosition)

        if not async
            return result

        return new Promise (resolve, reject) =>
            resolve(result)

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
     * Gets the correct selector for the class or namespace that is part of the specified event.
     *
     * @param {jQuery.Event} event A jQuery event.
     *
     * @return {object|null} A selector to be used with jQuery.
    ###
    getClassSelectorFromEvent: (event) ->
        selector = event.currentTarget

        $ = require 'jquery'

        if $(selector).parent().hasClass('function argument')
            return $(selector).parent().children('.namespace, .class:not(.operator):not(.constant)')

        if $(selector).prev().hasClass('namespace') && $(selector).hasClass('class')
            return $([$(selector).prev()[0], selector])

        if $(selector).next().hasClass('class') && $(selector).hasClass('namespace')
           return $([selector, $(selector).next()[0]])

        if $(selector).prev().hasClass('namespace') || $(selector).next().hasClass('inherited-class')
            return $(selector).parent().children('.namespace, .inherited-class')

        return selector

    ###*
     * Indicates if the specified type is a basic type (e.g. int, array, object, etc.).
     *
     * @param {string} type
     *
     * @return {boolean}
    ###
    isBasicType: (type) ->
        return /^(string|int|bool|float|object|mixed|array|resource|void|null|callable|false|true|self|static|parent|\$this)$/i.test(type)
