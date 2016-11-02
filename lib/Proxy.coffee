fs            = require 'fs'
net           = require 'net'
stream        = require 'stream'
child_process = require 'child_process'

module.exports =

##*
# Proxy that handles communicating with the PHP side.
##
class Proxy
    ###*
     * The config to use.
    ###
    config: null

    ###*
     * The name (without path or extension) of the database file to use.
    ###
    indexDatabaseName: null

    ###*
     * The name of the project.
    ###
    projectName: null

    ###*
     * @var {String}
    ###
    HEADER_DELIMITER: "\r\n"

    ###*
     * Constructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->
        @requestQueue = {}
        @resetResponseState()

    ###*
     * Prepares parameters for execution.
     *
     * @param {Array} parameters
     *
     * @return {Array}
    ###
    # prepareParameters: (args) ->
    #     parameters = [
    #         # '-d memory_limit=-1',
    #         # @getCorePackagePath() + "/src/Main.php"
    #     ]
    #
    #     for a in args
    #         parameters.push(a)
    #
    #     return parameters

    # performRequest: (method, params, streamCallback = null, stdinData = null) ->
    #     php = @config.get('phpCommand')
    #
    #     params.unshift(@projectName)
    #
    #     parameters = @prepareParameters(params)
    #
    #     if not @projectName
    #         return new Promise (resolve, reject) ->
    #             reject('Request aborted as there is no project active (yet)')
    #
    #     return @performRequestAsync(php, parameters, streamCallback, stdinData)

    ###*
     * @return {String}
    ###
    getCorePackagePath: () ->
        return atom.packages.resolvePackagePath("php-integrator-core")








    client: null
    requestQueue: null
    nextRequestId: 1


    response: null


    getSocketConnection: () ->
        return new Promise (resolve, reject) =>
            if not @client?
                # TODO: Catch ECONNREFUSED if server hasn't started yet.

                @client = net.createConnection {port: 9999}, () =>
                    resolve(@client)

                @client.setNoDelay(true)
                @client.on('data', @processData.bind(this))
                @client.on('close', @onConnectionClosed.bind(this))
                @client.on('end', @onConnectionEnded.bind(this))

            resolve(@client)


    onDataReceived: (data) ->
        @processData(data)

    onConnectionClosed: (data) ->
        # TODO: Argh, the connection dropped.
        debugger

    onConnectionEnded: (data) ->
        # TODO
        debugger




    processData: (data) ->
        dataBuffer = new Buffer(data)

        @processDataBuffer(dataBuffer)



    processDataBuffer: (dataBuffer) ->
        if not @response.length?
            contentLengthHeader = @readRawHeader(dataBuffer)
            @response.length = @getLengthFromContentLengthHeader(contentLengthHeader)

            bytesRead = contentLengthHeader.length + @HEADER_DELIMITER.length

        else if not @response.wasBoundaryFound
            header = @readRawHeader(dataBuffer)

            if header.length == 0
                @response.wasBoundaryFound = true

            bytesRead = header.length + @HEADER_DELIMITER.length

        else
            bytesRead = Math.min(dataBuffer.length, @response.length - @response.bytesRead)

            @response.content = Buffer.concat([@response.content, dataBuffer.slice(0, bytesRead)])
            @response.bytesRead += bytesRead

            if @response.bytesRead == @response.length
                jsonRpcResponse = @getJsonRpcResponseFromResponseBuffer(@response.content)

                if jsonRpcResponse?
                    request = @requestQueue[jsonRpcResponse.id]

                    if not jsonRpcResponse or jsonRpcResponse.error?
                        request.promise.reject({
                            request  : request
                            response : jsonRpcResponse
                            error    : jsonRpcResponse.error
                        })

                        # Server error
                        if jsonRpcResponse.error.code == -32000
                            @showUnexpectedSocketResponseError(jsonRpcResponse.error.message)

                    else
                        request.promise.resolve(jsonRpcResponse.result)

                    delete @requestQueue[jsonRpcResponse.id]

                @resetResponseState()

        dataBuffer = dataBuffer.slice(bytesRead)

        if dataBuffer.length > 0
            @processDataBuffer(dataBuffer)

    ###*
     * @param {Buffer} dataBuffer
     *
     * @return {Object}
    ###
    getJsonRpcResponseFromResponseBuffer: (dataBuffer) ->
        jsonRpcResponseString = dataBuffer.toString()

        try
            return @getJsonRpcResponseFromResponseContent(jsonRpcResponseString)

        catch error
            @showUnexpectedSocketResponseError(jsonRpcResponseString)

        return null # Never reached

    ###*
     * @param {String} content
     *
     * @return {Object}
    ###
    getJsonRpcResponseFromResponseContent: (content) ->
        return JSON.parse(content)

    ###*
     * @param {Buffer} dataBuffer
     *
     * @throws {Error}
     *
     * @return {String}
    ###
    readRawHeader: (dataBuffer) ->
        end = dataBuffer.indexOf(@HEADER_DELIMITER)

        if end == -1
          throw new Error('Header delimiter not found');

        return dataBuffer.slice(0, end).toString()

    ###*
     * @param {String} rawHeader
     *
     * @throws {Error}
     *
     * @return {Number}
    ###
    getLengthFromContentLengthHeader: (rawHeader) ->
        parts = rawHeader.split(':')

        if parts.length != 2
            throw new Error('Unexpected amount of header parts found')

        contentLength = parseInt(parts[1])

        if not contentLength?
            throw new Error('Content length header does not have an integer as value')

        return contentLength

    ###*
     * Resets the current response's state.
    ###
    resetResponseState: () ->
        @response =
            length           : null
            wasBoundaryFound : false
            bytesRead        : 0
            content          : new Buffer([])

    ###*
     * Performs an asynchronous request to the PHP side.
     *
     * @param {Number}   id
     * @param {String}   method
     * @param {Object}   parameters
     * @param {Callback} streamCallback
     *
     * @return {Promise}
    ###
    performJsonRpcRequest: (id, method, parameters, streamCallback = null) ->
        return new Promise (resolve, reject) =>
            JsonRpcRequest =
                jsonrpc : 2.0
                id      : id
                method  : method
                params  : parameters

            @requestQueue[id] = {
                id             : id
                streamCallback : streamCallback
                request        : JsonRpcRequest

                promise: {
                    resolve : resolve
                    reject  : reject
                }
            }

            # TODO: Refactor.
            # TODO: See if we can also used named pipes instead of TCP, the former should be faster. This should
            # however also work on Windows transparantly. Does React support this?
            # TODO: Spawn the server socket process ourselves. Check if it automatically closes if Atom closes.
            # TODO: The server process may be showing errors, but we can catch those from the server's STDOUT/STDERR when we spawn it ourselves later.
            # TODO: Find another way to implement streamCallback, will probably need additional (pushed by server
            # side) responses for this.

            # if streamCallback
            #     proc.stderr.on 'data', (data) =>
            #         streamCallback(data)

            console.log("Sending request ", JsonRpcRequest)

            content = @getContentForJsonRpcRequest(JsonRpcRequest)

            @writeRawRequest(content)

    ###*
     * @param {Object} request
     *
     * @return {String}
    ###
    getContentForJsonRpcRequest: (request) ->
        return JSON.stringify(request)

    ###*
     * Writes a raw request to the connection.
     *
     * This may not happen immediately if the connection is not available yet. In that case, the request will be
     * dispatched as soon as the connection becomes available.
     *
     * @param {String} content The content (body) of the request.
    ###
    writeRawRequest: (content) ->
        @getSocketConnection().then (connection) =>
            lengthInBytes = (new TextEncoder('utf-8').encode(content)).length

            connection.write("Content-Length: " + lengthInBytes + @HEADER_DELIMITER)
            connection.write(@HEADER_DELIMITER);
            connection.write(content)

    ###*
     * @param {String}     rawOutput
     * @param {Array|null} parameters
    ###
    showUnexpectedSocketResponseError: (rawOutput, parameters = null) ->
        detail =
            "The socket server sent back something unexpected. This could be a bug, but it could also be a problem " +
            "with your setup. If you're sure it is a bug, feel free to report it on the bug tracker."

        if parameters?
            detail += "\n \nCommand\n  → " + parameters.join(' ')

        detail += "\n \nOutput\n  → " + rawOutput

        atom.notifications.addError('PHP Integrator - Oops, something went wrong!', {
            dismissable : true
            detail      : detail
        })

    ###*
     * @param {String}      method
     * @param {Object}      parameters
     * @param {Callback}    streamCallback A method to invoke each time streaming data is received.
     * @param {String|null} stdinData      The data to pass to STDIN.
     *
     * @return {Promise}
    ###
    performRequest: (method, parameters, streamCallback = null, stdinData = null) ->
        if not @getCorePackagePath()?
            return new Promise (resolve, reject) ->
                reject('''
                    The core package was not found, it is currently being installed. This only needs to happen once at
                    initialization, but the service is not available yet in the meantime.
                ''')
                return

        if not @projectName?
            return new Promise (resolve, reject) ->
                reject('Request aborted as there is no project active (yet)')

        parameters.projectName = @projectName

        if stdinData?
            parameters.stdin = true
            parameters.stdinData = stdinData

        requestId = @nextRequestId++

        return @performJsonRpcRequest(requestId, method, parameters, streamCallback)

    ###*
     * Retrieves a list of available classes.
     *
     * @return {Promise}
    ###
    getClassList: () ->
        parameters = {
            database : @getIndexDatabasePath()
        }

        return @performRequest('classList', parameters)

    ###*
     * Retrieves a list of available classes in the specified file.
     *
     * @param {String} file
     *
     * @return {Promise}
    ###
    getClassListForFile: (file) ->
        if not file
            return new Promise (resolve, reject) ->
                reject('No file passed!')

        parameters = {
            database : @getIndexDatabasePath()
            file     : file
        }

        return @performRequest('classList', parameters)

    ###*
     * Retrieves a list of namespaces.
     *
     * @return {Promise}
    ###
    getNamespaceList: () ->
        parameters = {
            database : @getIndexDatabasePath()
        }

        return @performRequest('namespaceList', parameters)

    ###*
     * Retrieves a list of namespaces in the specified file.
     *
     * @param {String} file
     *
     * @return {Promise}
    ###
    getNamespaceListForFile: (file) ->
        if not file
            return new Promise (resolve, reject) ->
                reject('No file passed!')

        parameters = {
            database : @getIndexDatabasePath()
            file     : file
        }

        return @performRequest('namespaceList', parameters)

    ###*
     * Retrieves a list of available global constants.
     *
     * @return {Promise}
    ###
    getGlobalConstants: () ->
        parameters = {
            database : @getIndexDatabasePath()
        }

        return @performRequest('globalConstants', parameters)

    ###*
     * Retrieves a list of available global functions.
     *
     * @return {Promise}
    ###
    getGlobalFunctions: () ->
        parameters = {
            database : @getIndexDatabasePath()
        }

        return @performRequest('globalFunctions', parameters)

    ###*
     * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
     *
     * @param {String} className
     *
     * @return {Promise}
    ###
    getClassInfo: (className) ->
        if not className
            return new Promise (resolve, reject) ->
                reject('No class name passed!')

        parameters = {
            database : @getIndexDatabasePath()
            name     : className
        }

        return @performRequest('classInfo', parameters)

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
    resolveType: (file, line, type, kind = 'classlike') ->
        if not file
            return new Promise (resolve, reject) ->
                reject('No file passed!')

        if not line
            return new Promise (resolve, reject) ->
                reject('No line passed!')

        if not type
            return new Promise (resolve, reject) ->
                reject('No type passed!')

        if not kind
            return new Promise (resolve, reject) ->
                reject('No kind passed!')

        parameters = {
            database : @getIndexDatabasePath()
            file     : file
            line     : line
            type     : type
            kind     : kind
        }

        return @performRequest('resolveType', parameters)

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
    localizeType: (file, line, type, kind = 'classlike') ->
        if not file
            return new Promise (resolve, reject) ->
                reject('No file passed!')

        if not line
            return new Promise (resolve, reject) ->
                reject('No line passed!')

        if not type
            return new Promise (resolve, reject) ->
                reject('No type passed!')

        if not kind
            return new Promise (resolve, reject) ->
                reject('No kind passed!')

        parameters = {
            database : @getIndexDatabasePath()
            file     : file
            line     : line
            type     : type
            kind     : kind
        }

        return @performRequest('localizeType', parameters)

    ###*
     * Performs a semantic lint of the specified file.
     *
     * @param {String}      file
     * @param {String|null} source  The source code of the file to index. May be null if a directory is passed instead.
     * @param {Object}      options Additional options to set. Boolean properties noUnknownClasses, noUnknownMembers,
     *                              noUnknownGlobalFunctions, noUnknownGlobalConstants, noDocblockCorrectness and
     *                              noUnusedUseStatements are supported.
     *
     * @return {Promise}
    ###
    semanticLint: (file, source, options = {}) ->
        if not file
            return new Promise (resolve, reject) ->
                reject('No file passed!')

        parameters = {
            database : @getIndexDatabasePath()
            file     : file
            stdin    : true
        }

        for key, value of options
            parameters[key] = value

        return @performRequest('semanticLint', parameters, null, source)

    ###*
     * Fetches all available variables at a specific location.
     *
     * @param {String|null} file   The path to the file to examine. May be null if the source parameter is passed.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {Promise}
    ###
    getAvailableVariables: (file, source, offset) ->
        if not file? and not source?
            return new Promise (resolve, reject) ->
                reject('Either a path to a file or source code must be passed!')

        parameters = {
            database   : @getIndexDatabasePath()
            offset     : offset
            charoffset : true
        }

        if file?
            parameters.file = file

        return @performRequest('availableVariables', parameters, null, source)

    ###*
     * Fetches the types of the specified variable at the specified location.
     *
     * @deprecated Use deduceTypes instead.
     *
     * @param {String}      name   The variable to fetch, including its leading dollar sign.
     * @param {String}      file   The path to the file to examine.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {Promise}
    ###
    getVariableTypes: (name, file, source, offset) ->
        return @deduceTypes([name], file, source, offset)

    ###*
     * Deduces the resulting types of an expression based on its parts.
     *
     * @param {Array|null}  parts             One or more strings that are part of the expression, e.g.
     *                                        ['$this', 'foo()']. If null, the expression will automatically be deduced
     *                                        based on the offset.
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
    deduceTypes: (parts, file, source, offset, ignoreLastElement) ->
        if not file?
            return new Promise (resolve, reject) ->
                reject('A path to a file must be passed!')

        parameters = {
            database   : @getIndexDatabasePath()
            offset     : offset
            charoffset : true
        }

        if file?
            parameters.file = file

        if ignoreLastElement
            parameters.ignoreLastElement = true

        if parts?
            parameters.part = parts

        return @performRequest('deduceTypes', parameters, null, source)

    ###*
     * Fetches invocation information of a method or function call.
     *
     * @param {String|null} file   The path to the file to examine. May be null if the source parameter is passed.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {Promise}
    ###
    getInvocationInfo: (file, source, offset) ->
        if not file? and not source?
            return new Promise (resolve, reject) ->
                reject('Either a path to a file or source code must be passed!')

        parameters = {
            database   : @getIndexDatabasePath()
            offset     : offset
            charoffset : true
        }

        if file?
            parameters.file = file

        return @performRequest('invocationInfo', parameters, null, source)

    ###*
     * Truncates the database.
     *
     * @return {Promise}
    ###
    truncate: () ->
        parameters = {
            database : @getIndexDatabasePath()
        }

        return @performRequest('truncate', parameters, null, null)

    ###*
     * Initializes a project.
     *
     * @return {Promise}
    ###
    initialize: () ->
        parameters = {
            database : @getIndexDatabasePath()
        }

        return @performRequest('initialize', parameters, null, null)

    ###*
     * Vacuums a project, cleaning up the index database (e.g. pruning files that no longer exist).
     *
     * @return {Promise}
    ###
    vacuum: () ->
        parameters = {
            database : @getIndexDatabasePath()
        }

        return @performRequest('vacuum', parameters, null, null)

    ###*
     * Refreshes the specified file or folder. This method is asynchronous and will return immediately.
     *
     * @param {String|Array}  path                   The full path to the file  or folder to refresh. Alternatively,
     *                                              this can be a list of items to index at the same time.
     * @param {String|null}   source                 The source code of the file to index. May be null if a directory is
     *                                              passed instead.
     * @param {Callback|null} progressStreamCallback A method to invoke each time progress streaming data is received.
     * @param {Array}         excludedPaths          A list of paths to exclude from indexing.
     * @param {Array}         fileExtensionsToIndex  A list of file extensions (without leading dot) to index.
     *
     * @return {Promise}
    ###
    reindex: (path, source, progressStreamCallback, excludedPaths, fileExtensionsToIndex) ->
        if typeof path == "string"
            pathsToIndex = []

            if path
                pathsToIndex.push(path)

        else
            pathsToIndex = path

        if path.length == 0
            return new Promise (resolve, reject) ->
                reject('No filename passed!')

        progressStreamCallbackWrapper = null

        parameters = {
            database : @getIndexDatabasePath()
        }

        if progressStreamCallback?
            parameters.streamProgress = true

            progressStreamCallbackWrapper = (output) =>
                # Sometimes we receive multiple lines in bulk, so we must ensure it remains split correctly.
                percentages = output.toString('ascii').split("\n")
                percentages.pop() # Ditch the empty value.

                for percentage in percentages
                    progressStreamCallback(percentage)

        parameters.source = pathsToIndex
        parameters.exclude = excludedPaths
        parameters.extension = fileExtensionsToIndex

        return @performRequest('reindex', parameters, progressStreamCallbackWrapper, source)

    ###*
     * Sets the name (without path or extension) of the database file to use.
     *
     * @param {String} name
    ###
    setIndexDatabaseName: (name) ->
        @indexDatabaseName = name

    ###*
     * Sets the project name to pass.
     *
     * @param {String} name
    ###
    setProjectName: (name) ->
        @projectName = name

    ###*
     * Retrieves the full path to the database file to use.
     *
     * @return {String}
    ###
    getIndexDatabasePath: () ->
        return @config.get('packagePath') + '/indexes/' + @indexDatabaseName + '.sqlite'
