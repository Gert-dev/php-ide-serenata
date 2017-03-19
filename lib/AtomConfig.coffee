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
        @set('packagePath', atom.packages.resolvePackagePath("#{@packageName}"))
        @set('phpCommand', atom.config.get("#{@packageName}.phpCommand"))
        @set('additionalIndexingDelay', atom.config.get("#{@packageName}.additionalIndexingDelay"))
        @set('memoryLimit', atom.config.get("#{@packageName}.memoryLimit"))
        @set('insertNewlinesForUseStatements', atom.config.get("#{@packageName}.insertNewlinesForUseStatements"))
        @set('enableTooltips', atom.config.get("#{@packageName}.enableTooltips"))
        @set('enableSignatureHelp', atom.config.get("#{@packageName}.enableSignatureHelp"))
        @set('enableLinting', atom.config.get("#{@packageName}.enableLinting"))
        @set('showUnknownClasses', atom.config.get("#{@packageName}.showUnknownClasses"))
        @set('showUnknownMembers', atom.config.get("#{@packageName}.showUnknownMembers"))
        @set('showUnknownGlobalFunctions', atom.config.get("#{@packageName}.showUnknownGlobalFunctions"))
        @set('showUnknownGlobalConstants', atom.config.get("#{@packageName}.showUnknownGlobalConstants"))
        @set('showUnusedUseStatements', atom.config.get("#{@packageName}.showUnusedUseStatements"))
        @set('showMissingDocs', atom.config.get("#{@packageName}.showMissingDocs"))
        @set('validateDocblockCorrectness', atom.config.get("#{@packageName}.validateDocblockCorrectness"))

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

        atom.config.onDidChange "#{@packageName}.enableSignatureHelp", () =>
            @set('enableSignatureHelp', atom.config.get("#{@packageName}.enableSignatureHelp"))

        atom.config.onDidChange "#{@packageName}.enableTooltips", () =>
            @set('enableTooltips', atom.config.get("#{@packageName}.enableTooltips"))

        atom.config.onDidChange "#{@packageName}.enableLinting", () =>
            @set('enableLinting', atom.config.get("#{@packageName}.enableLinting"))

        atom.config.onDidChange "#{@packageName}.showUnknownClasses", () =>
            @set('showUnknownClasses', atom.config.get("#{@packageName}.showUnknownClasses"))

        atom.config.onDidChange "#{@packageName}.showUnknownMembers", () =>
            @set('showUnknownMembers', atom.config.get("#{@packageName}.showUnknownMembers"))

        atom.config.onDidChange "#{@packageName}.showUnknownGlobalFunctions", () =>
            @set('showUnknownGlobalFunctions', atom.config.get("#{@packageName}.showUnknownGlobalFunctions"))

        atom.config.onDidChange "#{@packageName}.showUnknownGlobalConstants", () =>
            @set('showUnknownGlobalConstants', atom.config.get("#{@packageName}.showUnknownGlobalConstants"))

        atom.config.onDidChange "#{@packageName}.showUnusedUseStatements", () =>
            @set('showUnusedUseStatements', atom.config.get("#{@packageName}.showUnusedUseStatements"))

        atom.config.onDidChange "#{@packageName}.showMissingDocs", () =>
            @set('showMissingDocs', atom.config.get("#{@packageName}.showMissingDocs"))

        atom.config.onDidChange "#{@packageName}.validateDocblockCorrectness", () =>
            @set('validateDocblockCorrectness', atom.config.get("#{@packageName}.validateDocblockCorrectness"))
