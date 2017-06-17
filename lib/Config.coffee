fs = require 'fs'

module.exports =

##*
# Abstract base class for managing configurations.
##
class Config
    ###*
     * Raw configuration object.
    ###
    data: null

    ###*
     * Array of change listeners.
    ###
    listeners: null

    ###*
     * Constructor.
    ###
    constructor: () ->
        @listeners = {}

        @data =
            core:
                phpCommand  : null
                memoryLimit : 512

            general:
                indexContinuously              : true
                additionalIndexingDelay        : 200
                insertNewlinesForUseStatements : false

            tooltips:
                enable : true

            signatureHelp:
                enable : true

            linting:
                enable                      : true
                showUnknownClasses          : true
                showUnknownMembers          : true
                showUnknownGlobalFunctions  : true
                showUnknownGlobalConstants  : true
                showUnusedUseStatements     : true
                showMissingDocs             : true
                validateDocblockCorrectness : true

            packagePath : null

            # See also http://www.phpdoc.org/docs/latest/index.html .
            phpdoc_base_url : {
                prefix: 'http://www.phpdoc.org/docs/latest/references/phpdoc/tags/'
                suffix: '.html'
            }

            # See also https://secure.php.net/urlhowto.php .
            php_documentation_base_urls : {
                root      : 'https://secure.php.net/'
                classes   : 'https://secure.php.net/class.'
                functions : 'https://secure.php.net/function.'
            }

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
     * @param {String} name
     * @param {mixed}  value
    ###
    set: (name, value) ->
        @data[name] = value

        if name of @listeners
            for listener in @listeners[name]
                listener(value, name)
