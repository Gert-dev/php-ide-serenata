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
            'core.phpCommand'
            'core.memoryLimit'
            'core.socketHost'
            'general.indexContinuously'
            'general.additionalIndexingDelay'
            'datatips.enable'
            'signatureHelp.enable'
            'gotoDefinition.enable'
            'autocompletion.enable'
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
        @set('packagePath', atom.packages.resolvePackagePath("#{@packageName}"))

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
