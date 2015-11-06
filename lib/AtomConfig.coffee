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
        @synchronizeToPhpConfig()

    ###*
     * @inheritdoc
    ###
    load: () ->
        @set('phpCommand', atom.config.get("#{@packageName}.phpCommand"))
        @set('composerCommand', atom.config.get("#{@packageName}.composerCommand"))
        @set('autoloadScripts', atom.config.get("#{@packageName}.autoloadScripts"))
        @set('classMapScripts', atom.config.get("#{@packageName}.classMapScripts"))
        @set('additionalScripts', atom.config.get("#{@packageName}.additionalScripts"))

        @set('packagePath', atom.packages.resolvePackagePath("#{@packageName}"))

    ###*
     * Attaches listeners to listen to Atom configuration changes.
    ###
    attachListeners: () ->
        atom.config.onDidChange "#{@packageName}.phpCommand", () =>
            @set('phpCommand', atom.config.get("#{@packageName}.phpCommand"))
            @synchronizeToPhpConfig()

        atom.config.onDidChange "#{@packageName}.composerCommand", () =>
            @set('composerCommand', atom.config.get("#{@packageName}.composerCommand"))
            @synchronizeToPhpConfig()

        atom.config.onDidChange "#{@packageName}.autoloadScripts", () =>
            @set('autoloadScripts', atom.config.get("#{@packageName}.autoloadScripts"))
            @synchronizeToPhpConfig()

        atom.config.onDidChange "#{@packageName}.classMapScripts", () =>
            @set('classMapScripts', atom.config.get("#{@packageName}.classMapScripts"))
            @synchronizeToPhpConfig()

        atom.config.onDidChange "#{@packageName}.additionalScripts", () =>
            @set('additionalScripts', atom.config.get("#{@packageName}.additionalScripts"))
            @synchronizeToPhpConfig()
