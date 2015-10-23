Config = require './Config.coffee'

module.exports =

##*
# Config that retrieves its settings from Atom's config.
##
class AtomConfig extends Config
    ###*
     * The name of the package to use when searching for settings.
    ###
    packageName: {}

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
        @set('php', atom.config.get('php-integrator-base.phpCommand'))
        @set('composer', atom.config.get('php-integrator-base.composerCommand'))
        @set('autoload', atom.config.get('php-integrator-base.autoloadScripts'))
        @set('classmap', atom.config.get('php-integrator-base.classMapScripts'))
        @set('insertNewlinesForUseStatements', atom.config.get('php-integrator-base.insertNewlinesForUseStatements'))

        @set('packagePath', atom.packages.resolvePackagePath('php-integrator-base'))

    ###*
     * Attaches listeners to listen to Atom configuration changes.
    ###
    attachListeners: () ->
        atom.config.onDidChange 'php-integrator-base.phpCommand', () =>
            @set('php', atom.config.get('php-integrator-base.phpCommand'))
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.composerCommand', () =>
            @set('composer', atom.config.get('php-integrator-base.composerCommand'))
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.autoloadScripts', () =>
            @set('autoload', atom.config.get('php-integrator-base.autoloadScripts'))
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.classMapScripts', () =>
            @set('classmap', atom.config.get('php-integrator-base.classMapScripts'))
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.insertNewlinesForUseStatements', () =>
            @set('insertNewlinesForUseStatements', atom.config.get('php-integrator-base.insertNewlinesForUseStatements'))
            @synchronizeToPhpConfig()
