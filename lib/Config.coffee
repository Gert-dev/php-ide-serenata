fs = require 'fs'

module.exports =

##*
# Abstract base class for managing configurations.
##
class Config
    ###*
     * Raw configuration object.
    ###
    data:
        php                            : null
        composer                       : null
        autoload                       : []
        classmap                       : []
        insertNewlinesForUseStatements : false

        packagePath                    : null

        # See also https://secure.php.net/urlhowto.php .
        php_documentation_base_url     : {
            functions: 'https://secure.php.net/function.'
        }

    ###*
     * Array of change listeners.
    ###
    listeners: {}

    ###*
     * Constructor.
    ###
    constructor: () ->
        @load()

    ###*
     * Loads the configuration.
    ###
    load: () ->
        throw new Error("This method is abstract and must be implemented!")

    ###*
     * Registers a listener that is invoked when the specified property is changed.
    ###
    onDidChange: (name, callback) ->
        if name not of @listeners
            @listeners[name] = []

        @listeners[name].push(callback)

    ###*
     * Retrieves the config setting with the specified name.
     *
     * @return {mixed}
    ###
    get: (name) ->
        return @data[name]

    ###*
     * Retrieves the config setting with the specified name.
     *
     * @param {string} name
     * @param {mixed}  value
    ###
    set: (name, value) ->
        @data[name] = value

        if name in @listeners
            for listener in @listeners[name]
                listener(value, name)

    ###*
     * Synchronizes the active relevant settings to a temporary file that can be used by the PHP side.
    ###
    synchronizeToPhpConfig: () ->
        files = ""
        classmaps = ""

        for file in @data.autoload
            files += "'#{file}',"

        for classmap in @data.classmap
            classmaps += "'#{classmap}',"

        text = "<?php
            $config = [
                'php'      => '#{@data.php}',
                'composer' => '#{@data.composer}',
                'autoload' => [#{files}],
                'classmap' => [(#{classmaps}]
            ];
        "

        fs.writeFileSync(@data.packagePath + '/php/tmp.php', text)
