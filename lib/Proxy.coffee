fs            = require 'fs'
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
     * @param {string}   directory The directory in which to execute the PHP side (e.g. the project folder).
     * @param {array}    args      The arguments to pass.
     * @param {boolean}  async     Whether to execute the method asynchronously or not.
     *
     * @return {Promise|Object} If the operation is asynchronous, a Promise, otherwise the result as object.
    ###
    performRequest: (directory, args, async) ->
        return false unless directory

        parameters = [__dirname + "/../php/Main.php", directory]

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
     * Returns the first available project directory.
     *
     * @return {string|null}
    ###
    getFirstProjectDirectory: () ->
        return atom.project.getDirectories()[0]?.path

    ###*
     * Retrieves a list of available classes.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getClassList: (async = false) ->
        return @performRequest(@getFirstProjectDirectory(), ['--class-list'], async)

    ###*
     * Retrieves a list of available global constants.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getGlobalConstants: (async = false) ->
        return @performRequest(@getFirstProjectDirectory(), ['--constants'], async)

    ###*
     * Retrieves a list of available global functions.
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getGlobalFunctions: (async = false) ->
        return @performRequest(@getFirstProjectDirectory(), ['--functions'], async)

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
        return @performRequest(@getFirstProjectDirectory(), ['--class-info', className], async)

    ###*
     * Retrieves the members of the type that is returned by the member with the specified name in the specified class.
     * This is essentially the same as determining the return type of the method (or type of the member variable) with
     * the given name in the given class, and then calling {@see getMembers} for that type, hence autocompleting the
     * 'name' in 'className'.
     *
     * @param {string} className
     * @param {string} name
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    autocomplete: (className, name, async = false) ->
        if not async
            info = @getClassInfo(className, async)

            if name.indexOf('()') != -1
                name = name.replace('()', '')

                if name of info.methods
                    return @getClassInfo(info.methods[name].args.return.resolvedType, async)

            else if name of info.properties
                return @getClassInfo(info.properties[name].args.return.resolvedType, async)

            else if name of info.constants
                return @getClassInfo(info.constants[name].args.return.resolvedType, async)

            return null

        return @getClassInfo(className, async).then (info) =>
            if name.indexOf('()') != -1
                name = name.replace('()', '')

                if name of info.methods
                    return @getClassInfo(info.methods[name].args.return.resolvedType, async)

            else if name of info.properties
                return @getClassInfo(info.properties[name].args.return.resolvedType, async)

            else if name of info.constants
                return @getClassInfo(info.constants[name].args.return.resolvedType, async)

            return new Promise (resolve, reject) ->
                resolve(null)

    ###*
     * Returns information about parameters described in the docblock for the given method in the given class.
     *
     * @param {string} className
     * @param {string} name
     *
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    getDocParams: (className, name, async = false) ->
        return @performRequest(@getFirstProjectDirectory(), ['--doc-params', className, name], async)

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
            filename = ''

        return @performRequest(@getFirstProjectDirectory(), ['--reindex', filename], true)
