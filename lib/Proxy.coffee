fs            = require 'fs'
md5           = require 'md5'
child_process = require "child_process"

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
            Utility.escapeSpaces(__dirname + "/../php/src/Main.php"),
            Utility.escapeSpaces(@getIndexDatabasePath())
        ]

        for a in args
            a = Utility.escapeSeparators(a)
            a = Utility.escapeSpaces(a)

            parameters.push(a)

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

            if response.error
                throw response.error

            response = JSON.parse(response.output[1].toString('ascii'))

            if not response or response.error?
                throw response.error

            if not response.success
                throw "An unsuccessful status code was returned by the PHP side!"

        catch error
            throw (if error.message then error.message else error)

        return response?.result


    ###*
     * Performs an asynchronous request to the PHP side.
     *
     * @param {string}   command        The command to execute.
     * @param {array}    parameters     The arguments to pass.
     * @param {Callback} streamCallback A method to invoke each time streaming data is received.
     *
     * @return {Promise}
    ###
    performRequestAsync: (command, parameters, streamCallback = null) ->
        return new Promise (resolve, reject) =>
            # We are already above the default of 200 kB for methods such as getGlobalFunctions.
            options =
                maxBuffer: 50000 * 1024

            proc = child_process.exec(@config.get('phpCommand') + ' ' + parameters.join(' '), options, (error, stdout, stderr) =>
                if not stdout or stdout.length == 0
                    reject({message: "No output received from the PHP side!"})
                    return

                try
                    response = JSON.parse(stdout)

                catch error
                    #console.error(error)
                    reject({message: error})
                    return

                if response?.error
                    #console.error(message)
                    message = response.error?.message
                    reject({message: message})
                    return

                if not response.success
                    reject({message: 'An unsuccessful status code was returned by the PHP side!'})
                    return

                resolve(response.result)
            )

            if streamCallback
                proc.stderr.on 'data', (data) =>
                    streamCallback(data)

    ###*
     * Performs a request to the PHP side.
     *
     * @param {array}    args           The arguments to pass.
     * @param {boolean}  async          Whether to execute the method asynchronously or not.
     * @param {Callback} streamCallback A method to invoke each time streaming data is received.
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    performRequest: (args, async, streamCallback) ->
        php = @config.get('phpCommand')
        parameters = @prepareParameters(args)

        if not async
            return @performRequestSync(php, parameters)

        else
            return @performRequestAsync(php, parameters, streamCallback)

    ###*
     * Retrieves a list of available classes.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getClassList: (async = false) ->
        return @performRequest(['--class-list'], async)

    ###*
     * Retrieves a list of available global constants.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getGlobalConstants: (async = false) ->
        return @performRequest(['--constants'], async)

    ###*
     * Retrieves a list of available global functions.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getGlobalFunctions: (async = false) ->
        return @performRequest(['--functions'], async)

    ###*
     * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
     *
     * @param {string} className
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getClassInfo: (className, async = false) ->
        return @performRequest(['--class-info', className], async)

    ###*
     * Refreshes the specified file. If no file is specified, all files are refreshed (which can take a while for large
     * projects!). This method is asynchronous and will return immediately.
     *
     * @param {string}   filename               The full file path to the class to refresh.
     * @param {Callback} progressStreamCallback A method to invoke each time progress streaming data is received.
     *
     * @return {Promise}
    ###
    reindex: (filename, progressStreamCallback) ->
        if not filename
            filename = atom.project.getDirectories()[0]?.path

        # For Windows - Replace \ in class namespace to / because composer uses / instead of \.
        filename = Utility.normalizeSeparators(filename)

        progressStreamCallbackWrapper = (output) =>
            # Sometimes we receive multiple lines in bulk, so we must ensure it remains split correctly.
            percentages = output.split("\n")
            percentages.pop() # Ditch the empty value.

            for percentage in percentages
                progressStreamCallback(percentage)

        return @performRequest(['--reindex', filename, '--stream-progress'], true, progressStreamCallbackWrapper)

    ###*
     * Retrieves the name of the database file to use.
     *
     * @return {string}
    ###
    getIndexDatabaseName: () ->
        pathStrings = ''

        for i,project of atom.project.getDirectories()
            pathStrings += project.path

        return md5(pathStrings)

    ###*
     * Retrieves the full path to the database file to use.
     *
     * @return {string}
    ###
    getIndexDatabasePath: () ->
        id = @getIndexDatabaseName()

        return @config.get('packagePath') + '/indexes/' + id + '.sqlite'
