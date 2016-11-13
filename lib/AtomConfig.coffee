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
     * @inheritdoc
    ###
    constructor: (@packageName) ->
        super()

        @attachListeners()

    ###*
     * @inheritdoc
    ###
    load: () ->
        @set('phpCommand', atom.config.get("#{@packageName}.phpCommand"))
        @set('additionalIndexingDelay', atom.config.get("#{@packageName}.additionalIndexingDelay"))
        @set('memoryLimit', atom.config.get("#{@packageName}.memoryLimit"))
        @set('insertNewlinesForUseStatements', atom.config.get("#{@packageName}.insertNewlinesForUseStatements"))
        @set('packagePath', atom.packages.resolvePackagePath("#{@packageName}"))

    ###*
     * Attaches listeners to listen to Atom configuration changes.
    ###
    attachListeners: () ->
        atom.config.onDidChange "#{@packageName}.phpCommand", () =>
            @set('phpCommand', atom.config.get("#{@packageName}.phpCommand"))

        atom.config.onDidChange "#{@packageName}.additionalIndexingDelay", () =>
            @set('additionalIndexingDelay', atom.config.get("#{@packageName}.additionalIndexingDelay"))

        atom.config.onDidChange "#{@packageName}.memoryLimit", () =>
            @set('memoryLimit', atom.config.get("#{@packageName}.memoryLimit"))

        atom.config.onDidChange "#{@packageName}.insertNewlinesForUseStatements", () =>
            @set('insertNewlinesForUseStatements', atom.config.get("#{@packageName}.insertNewlinesForUseStatements"))
