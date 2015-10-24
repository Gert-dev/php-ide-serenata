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
     * @param {boolean}  async     Whether the request must be asynchronous or not.
     * @param {callback} callback  The callback to execute when the asynchronous operation finishes.
     *
     * @return {Object} The decoded response JSON, or an object containing error information, or nothing when the
     *                  result is asynchronous. Returns false if there is no directory set.
    ###
    performRequest: (directory, args, async, callback) ->
        return false unless directory

        parameters = [__dirname + "/../php/Main.php", directory]

        for a in args
            parameters.push(Utility.escapeSeparators(a))

        if not async
            try
                response = child_process.spawnSync(@php, parameters)
                response = JSON.parse(response.output[1].toString('ascii'))

            catch err
                response = {
                    error : {
                        message : err
                    }
                }

            if !response
                return []

            if response.error?
                console.error(response.error?.message)

            return response

        else
            child_process.exec(@php + ' ' + parameters.join(' '), callback)

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
     * @return {Object}
    ###
    getClassList: () ->
        hash = md5(@getFirstProjectDirectory())

        path = __dirname + "/../indexes/" + hash + "/index.classes.json"

        try
            fs.accessSync(path, fs.F_OK | fs.R_OK)

        catch err
            return []

        options =
            encoding: 'UTF-8'

        return JSON.parse(fs.readFileSync(path, options))

    ###*
     * Retrieves a list of available global constants.
     *
     * @return {Object}
    ###
    getConstants: () ->
        return @performRequest(@getFirstProjectDirectory(), ['--constants'], false)

    ###*
     * Retrieves a list of available global functions.
     *
     * @return {Object}
    ###
    getGlobalFunctions: () ->
        return @performRequest(@getFirstProjectDirectory(), ['--functions'], false)

    ###*
     * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
     *
     * @param {string} className
     *
     * @return {Object}
    ###
    getClassInfo: (className) ->
        return @performRequest(@getFirstProjectDirectory(), ['--methods', className], false)

    ###*
     * Retrieves the members of the type that is returned by the member with the specified name in the specified class.
     * This is essentially the same as determining the return type of the method (or type of the member variable) with
     * the given name in the given class, and then calling {@see getMembers} for that type, hence autocompleting the
     * 'name' in 'className'.
     *
     * @param {string} className
     * @param {string} name
     *
     * @return {Object}
    ###
    autocomplete: (className, name) ->
        return @performRequest(@getFirstProjectDirectory(), ['--autocomplete', className, name], false)

    ###*
     * Returns information about parameters described in the docblock for the given method in the given class.
     *
     * @param {string} className
     * @param {string} name
     *
     * @return {Object}
    ###
    getDocParams: (className, name) ->
        return @performRequest(@getFirstProjectDirectory(), ['--doc-params', className, name], false)

    ###*
     * Refreshes the specified file. If no file is specified, all files are refreshed (which can take a while for large
     * projects!). This method is asynchronous and will return immediately.
     *
     * @param {string}   filename The full file path to the class to refresh.
     * @param {callback} callback The callback to invoke when the indexing process is finished.
    ###
    reindex: (filename, callback) ->
        if not filename
            filename = ''

        @performRequest(@getFirstProjectDirectory(), ['--refresh', filename], true, callback)
