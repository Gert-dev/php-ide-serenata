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
     * Cosntructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->

    ###*
     * Performs a request to the PHP side.
     *
     * @param {array}    args      The arguments to pass.
     * @param {boolean}  async     Whether to execute the method asynchronously or not.
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    performRequest: (args, async) ->
        parameters = [
            Utility.escapePath(__dirname + "/../php/src/Main.php"),
            Utility.escapePath(@config.get('packagePath') + '/indexes/' + @getIndexDatabaseName() + '.sqlite')
        ]

        for a in args
            parameters.push(Utility.escapeSeparators(a))

        if not async
            try
                response = child_process.spawnSync(@config.get('phpCommand'), parameters)

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

        else
            return new Promise (resolve, reject) =>
                # We are already above the default of 200 kB for methods such as getGlobalFunctions.
                options =
                    maxBuffer: 50000 * 1024

                child_process.exec(@config.get('phpCommand') + ' ' + parameters.join(' '), options, (error, stdout, stderr) =>
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
     * @param {string}  filename The full file path to the class to refresh.
     *
     * @return {Promise}
    ###
    reindex: (filename) ->
        if not filename
            filename = atom.project.getDirectories()[0]?.path

        # For Windows - Replace \ in class namespace to / because composer use / instead of \.
        filename = Utility.normalizeSeparators(filename)
        filename = Utility.escapePath(filename)

        return @performRequest(['--reindex', filename], true)

    ###*
     * Retrieves the name of the database to use.
     *
     * @return {string}
    ###
    getIndexDatabaseName: () ->
        pathStrings = ''

        for i,project of atom.project.getDirectories()
            pathStrings += project.path

        return md5(pathStrings)
