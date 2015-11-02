fs            = require 'fs'
child_process = require "child_process"

Utility = require "./Utility"

module.exports =

##*
# Proxy that handles communicating with the PHP side.
##
class Proxy
    ###*
     * The command to execute when a PHP process needs to be spawned.
    ###
    php: null

    ###*
     * Cosntructor.
     *
     * @param {string} php The command to execute when a PHP process needs to be spawned.
    ###
    constructor: (@php) ->

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
                response = child_process.spawnSync(@php, parameters)

                if response.error
                    throw response.error

                response = JSON.parse(response.output[1].toString('ascii'))

            catch err
                response = {
                    error : {
                        message : err
                    }
                }

            if !response
                return {}

            if response.error?
                console.error(response.error?.message)

            return response

        else
            return new Promise (resolve, reject) =>
                # We are already above the default of 200 kB for methods such as getGlobalFunctions.
                options =
                    maxBuffer: 50000 * 1024

                child_process.exec(@php + ' ' + parameters.join(' '), options, (error, stdout, stderr) =>
                    try
                        response = JSON.parse(stdout)

                    catch error
                        console.error(error)
                        reject({message: error})

                    if response?.error
                        message = response.error?.message

                        console.error(message)
                        reject({mesage: message})

                    resolve(response)
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
        return @performRequest(@getFirstProjectDirectory(), ['--autocomplete', className, name], async)

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
