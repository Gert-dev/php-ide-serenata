path = require 'path'
process = require 'process'
mkdirp = require 'mkdirp'

Config = require './Config'

module.exports =

##*
# Config that retrieves its settings from Atom's config.
##
class AtomConfig extends Config
    ###*
     * The name of the package to use when searching for settings.
    ###
    packageName: null

    ###*
     * @var {Array}
    ###
    configurableProperties: null

    ###*
     * @inheritdoc
    ###
    constructor: (@packageName) ->
        @configurableProperties = [
            'core.phpExecutionType'
            'core.phpCommand'
            'core.memoryLimit'
            'core.additionalDockerVolumes'
            'general.indexContinuously'
            'general.additionalIndexingDelay'
            'datatips.enable'
            'signatureHelp.enable'
            'gotoDefinition.enable'
            'autocompletion.enable'
            'annotations.enable'
            'refactoring.enable'
            'linting.enable'
            'linting.showUnknownClasses'
            'linting.showUnknownMembers'
            'linting.showUnknownGlobalFunctions'
            'linting.showUnknownGlobalConstants'
            'linting.showUnusedUseStatements'
            'linting.showMissingDocs'
            'linting.validateDocblockCorrectness'
        ]

        super()

        @attachListeners()

    ###*
     * @inheritdoc
    ###
    load: () ->
        @set('storagePath', @getPathToStorageFolderInRidiculousWay())

        for property in @configurableProperties
            @set(property, atom.config.get("#{@packageName}.#{property}"))

    ###*
     * Attaches listeners to listen to Atom configuration changes.
    ###
    attachListeners: () ->
        for property in @configurableProperties
            # Hmmm, I thought CoffeeScript automatically solved these variable copy bugs with function creation in
            # loops...
            callback = ((propertyCopy, data) ->
                @set(propertyCopy, data.newValue)
            ).bind(this, property)

            atom.config.onDidChange("#{@packageName}.#{property}", callback)

    ###*
     * @return {String}
    ###
    getPathToStorageFolderInRidiculousWay: () ->
        # NOTE: Apparently process.env.ATOM_HOME is not always set for whatever reason and this ridiculous workaround
        # is needed to fetch an OS-compliant location to store application data.
        baseFolder = null

        if process.env.APPDATA
            baseFolder = process.env.APPDATA

        else if process.platform == 'darwin'
            baseFolder = process.env.HOME + '/Library/Preferences'

        else
            baseFolder = process.env.HOME + '/.cache'

        packageFolder = baseFolder + path.sep + @packageName

        mkdirp.sync(packageFolder)

        return packageFolder
