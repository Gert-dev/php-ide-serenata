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
        @set('packagePath', atom.packages.resolvePackagePath("#{@packageName}"))

    ###*
     * Attaches listeners to listen to Atom configuration changes.
    ###
    attachListeners: () ->
        atom.config.onDidChange "#{@packageName}.phpCommand", () =>
            @set('phpCommand', atom.config.get("#{@packageName}.phpCommand"))
