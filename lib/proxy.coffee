exec = require "child_process"
process = require "process"
config = require "./config.coffee"
md5 = require 'md5'
fs = require 'fs'

data =
    methods: [],
    autocomplete: [],
    composer: null

currentProcesses = []

###*
 * Executes a command to PHP proxy
 * @param  {string}  command Command to exectue
 * @param  {boolean} async   Must be async or not
 * @return {array}           Json of the response
###
execute = (command, async) ->
    for directory in atom.project.getDirectories()
        if not async
            for c in command
                c.replace(/\\/g, '\\\\')

            try
                # avoid multiple processes of the same command
                if not currentProcesses[command]?
                    currentProcesses[command] = true

                    stdout = exec.spawnSync(config.config.php, [__dirname + "/../php/parser.php",  directory.path].concat(command)).output[1].toString('ascii')

                    delete currentProcesses[command]
                    res = JSON.parse(stdout)
            catch err
                console.log err
                res =
                    error: err

            if !res
                return []

            if res.error?
                printError(res.error)

            return res
        else
            command.replace(/\\/g, '\\\\')

            if not currentProcesses[command]?
                console.log 'Building index'
                currentProcesses[command] = exec.exec(config.config.php + " " + __dirname + "/../php/parser.php " + directory.path + " " +   command, (error, stdout, stderr) ->
                    delete currentProcesses[command]
                    console.log 'Build done'
                    return []
                )

###*
 * Reads an index by its name (file in indexes/index.[name].json)
 * @param {string} name Name of the index to read
###
readIndex = (name) ->
    for directory in atom.project.getDirectories()
        crypt = md5(directory.path)
        path = __dirname + "/../../indexes/" + crypt + "/index." + name + ".json"
        try
            fs.accessSync(path, fs.F_OK | fs.R_OK)
        catch err
            return []

        options =
            encoding: 'UTF-8'
        return JSON.parse(fs.readFileSync(path, options))

        break

###*
 * Open and read the composer.json file in the current folder
###
readComposer = () ->
    for directory in atom.project.getDirectories()
        path = "#{directory.path}/composer.json"

        try
            fs.accessSync(path, fs.F_OK | fs.R_OK)
        catch err
            continue

        options =
            encoding: 'UTF-8'
        data.composer = JSON.parse(fs.readFileSync(path, options))
        return data.composer

    console.log 'Unable to find composer.json file or to open it. The plugin will not work as expected. It only works on composer project'
    throw "Error"

###*
 * Throw a formatted error
 * @param {object} error Error to show
###
printError = (error) ->
    data.error = true
    message = error.message

    #if error.file? and error.line?
        #message = message + ' [from file ' + error.file + ' - Line ' + error.line + ']'

    #throw new Error(message)

module.exports =

    ###*
     * Clear all cache of the plugin
    ###
    clearCache: () ->
        data =
            error: false,
            autocomplete: [],
            methods: [],
            composer: null

    ###*
     * Autocomplete for classes name
     * @return {array}
    ###
    classes: () ->
        return readIndex('classes')

    ###*
     * Returns composer.json file
     * @return {Object}
    ###
    composer: () ->
        return readComposer()

    ###*
     * Autocomplete for internal PHP constants
     * @return {array}
    ###
    constants: () ->
        if not data.constants?
            res = execute(["--constants"], false)
            data.constants = res

        return data.constants

    ###*
     * Autocomplete for internal PHP functions
     * @return {array}
    ###
    functions: () ->
        if not data.functions?
            res = execute(["--functions"], false)
            data.functions = res

        return data.functions

    ###*
     * Autocomplete for methods & properties of a class
     * @param  {string} className Class complete name (with namespace)
     * @return {array}
    ###
    methods: (className) ->
        if not data.methods[className]?
            res = execute(["--methods","#{className}"], false)
            data.methods[className] = res

        return data.methods[className]

    ###*
     * Autocomplete for methods & properties of a class
     * @param  {string} className Class complete name (with namespace)
     * @return {array}
    ###
    autocomplete: (className, name) ->
        cacheKey = className + "." + name

        if not data.autocomplete[cacheKey]?
            res = execute(["--autocomplete", className, name], false)
            data.autocomplete[cacheKey] = res

        return data.autocomplete[cacheKey]

    ###*
     * Returns params from the documentation of the given function
     *
     * @param {string} className
     * @param {string} functionName
    ###
    docParams: (className, functionName) ->
        res = execute("--doc-params #{className} #{functionName}", false)
        return res

    ###*
     * Refresh the full index or only for the given classPath
     * @param  {string} classPath Full path (dir) of the class to refresh
    ###
    refresh: (classPath) ->
        if not classPath?
            execute("--refresh", true)
        else
            execute("--refresh #{classPath}", true)

    ###*
     * Method called on plugin activation
    ###
    init: () ->
        @refresh()
        atom.workspace.observeTextEditors (editor) =>
            editor.onDidSave((event) =>
              # Only .php file
              if event.path.substr(event.path.length - 4) == ".php"
                  @clearCache()

                  # For Windows - Replace \ in class namespace to / because
                  # composer use / instead of \
                  path = event.path
                  for directory in atom.project.getDirectories()
                      if path.indexOf(directory.path) == 0
                          classPath = path.substr(0, directory.path.length+1)
                          path = path.substr(directory.path.length+1)
                          break

                  @refresh(classPath + path.replace(/\\/g, '/'))
            )

        atom.config.onDidChange 'php-integrator-base.phpCommand', () =>
            @clearCache()

        atom.config.onDidChange 'php-integrator-base.composerCommand', () =>
            @clearCache()

        atom.config.onDidChange 'php-integrator-base.autoloadScripts', () =>
            @clearCache()

        atom.config.onDidChange 'php-integrator-base.classMapScripts', () =>
            @clearCache()
