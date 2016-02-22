fs            = require 'fs'
stream        = require 'stream'
child_process = require 'child_process'

Utility = require "./Utility"

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
     * Constructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->

    ###*
     * Prepares parameters for execution by escaping them.
     *
     * @param {array} parameters
     *
     * @return {array}
    ###
    prepareParameters: (args) ->
        parameters = [
            '-d memory_limit=-1',
            Utility.escapeShellParameter(__dirname + "/../php/src/Main.php")
        ]

        for a in args
            parameters.push(Utility.escapeShellParameter(a))

        return parameters

    ###*
     * Performs a synchronous request to the PHP side.
     *
     * @param {string} command    The command to execute.
     * @param {array}  parameters The arguments to pass.
     *
     * @return {Object}
    ###
    performRequestSync: (command, parameters) ->
        try
            response = child_process.spawnSync(command, parameters)

            rawOutput = response.output[1].toString('ascii')

            try
                response = JSON.parse(rawOutput)

            catch error
                @showUnexpectedOutputError(rawOutput)
                throw new Error('Unexpected JSON output encountered!')

            if not response or not response.success
                throw new Error('An unsuccessful status code was returned by the PHP side!')

        catch error
            throw (if error.message then error.message else error)

        return response?.result


    ###*
     * Performs an asynchronous request to the PHP side.
     *
     * @param {string}   command        The command to execute.
     * @param {array}    parameters     The arguments to pass.
     * @param {Callback} streamCallback A method to invoke each time streaming data is received.
     * @param {string}   stdinData      The data to pass to STDIN.
     *
     * @return {Promise}
    ###
    performRequestAsync: (command, parameters, streamCallback = null, stdinData = null) ->
        return new Promise (resolve, reject) =>
            proc = child_process.spawn(command, parameters)

            buffer = ''

            proc.stdout.on 'data', (data) =>
                buffer += data

            proc.on 'close', (code) =>
                if not buffer or buffer.length == 0
                    reject({rawOutput: buffer, message: "No output received from the PHP side!"})
                    return

                try
                    response = JSON.parse(buffer)

                catch error
                    @showUnexpectedOutputError(buffer)

                if not response or not response.success
                    reject({rawOutput: buffer, message: 'An unsuccessful status code was returned by the PHP side!'})
                    return

                resolve(response.result)

            if streamCallback
                proc.stderr.on 'data', (data) =>
                    streamCallback(data)

            if stdinData?
                proc.stdin.write(stdinData, 'utf-8')
                proc.stdin.end()

    ###*
     * @param {string} rawOutput
    ###
    showUnexpectedOutputError: (rawOutput) ->
        atom.notifications.addError('php-integrator - Oops, something went wrong!', {
            dismissable : true
            detail      :
                "PHP sent back something unexpected. This is most likely an issue with your setup. If you're sure " +
                "this is a bug, feel free to report it on the bug tracker.\n \n→ " + rawOutput
        })

    ###*
     * Performs a request to the PHP side.
     *
     * @param {array}    args           The arguments to pass.
     * @param {boolean}  async          Whether to execute the method asynchronously or not.
     * @param {Callback} streamCallback A method to invoke each time streaming data is received.
     * @param {string}   stdinData      The data to pass to STDIN.
     *
     * @todo Support stdinData for synchronous requests as well.
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    performRequest: (args, async, streamCallback = null, stdinData = null) ->
        php = @config.get('phpCommand')
        parameters = @prepareParameters(args)

        if not async
            return @performRequestSync(php, parameters)

        else
            return @performRequestAsync(php, parameters, streamCallback, stdinData)

    ###*
     * Retrieves a list of available classes.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getClassList: (async = false) ->
        return @performRequest(['--class-list', '--database=' + @getIndexDatabasePath()], async)

    ###*
     * Retrieves a list of available classes in the specified file.
     *
     * @param {string}  file
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getClassListForFile: (file, async = false) ->
        if not file
            throw new Error('No file passed!')

        return @performRequest(['--class-list', '--database=' + @getIndexDatabasePath(), '--file=' + file], async)

    ###*
     * Retrieves a list of available global constants.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getGlobalConstants: (async = false) ->
        return @performRequest(['--constants', '--database=' + @getIndexDatabasePath()], async)

    ###*
     * Retrieves a list of available global functions.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getGlobalFunctions: (async = false) ->
        return @performRequest(['--functions', '--database=' + @getIndexDatabasePath()], async)

    ###*
     * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
     *
     * @param {string} className
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getClassInfo: (className, async = false) ->
        if not className
            throw new Error('No class name passed!')

        return @performRequest(
            ['--class-info', '--database=' + @getIndexDatabasePath(), '--name=' + className],
            async
        )

    ###*
     * Resolves a local type in the specified file, based on use statements and the namespace.
     *
     * @param {string}  file
     * @param {number}  line   The line the type is located at. The first line is 1, not 0.
     * @param {string}  type
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    resolveType: (file, line, type, async = false) ->
        throw new Error('No file passed!') if not file
        throw new Error('No line passed!') if not line
        throw new Error('No type passed!') if not type

        return @performRequest(
            ['--resolve-type', '--database=' + @getIndexDatabasePath(), '--file=' + file, '--line=' + line, '--type=' + type],
            async
        )

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
        if not path
            throw new Error('No class name passed!')

        progressStreamCallbackWrapper = (output) =>
            # Sometimes we receive multiple lines in bulk, so we must ensure it remains split correctly.
            percentages = output.toString('ascii').split("\n")
            percentages.pop() # Ditch the empty value.

            for percentage in percentages
                progressStreamCallback(percentage)

        return @performRequest(
            ['--reindex', '--database=' + @getIndexDatabasePath(), '--source=' + path, '--stream-progress', '--stdin'],
            true,
            progressStreamCallbackWrapper,
            source
        )

    ###*
     * Sets the name (without path or extension) of the database file to use.
     *
     * @param {string} name
    ###
    setIndexDatabaseName: (name) ->
        @indexDatabaseName = name

    ###*
     * Retrieves the full path to the database file to use.
     *
     * @return {string}
    ###
    getIndexDatabasePath: () ->
        return @config.get('packagePath') + '/indexes/' + @indexDatabaseName + '.sqlite'
