Config = require './Config'

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
        @set('phpCommand', atom.config.get('php-integrator-base.phpCommand'))
        @set('composerCommand', atom.config.get('php-integrator-base.composerCommand'))
        @set('autoloadScripts', atom.config.get('php-integrator-base.autoloadScripts'))
        @set('classMapScripts', atom.config.get('php-integrator-base.classMapScripts'))
        @set('insertNewlinesForUseStatements', atom.config.get('php-integrator-base.insertNewlinesForUseStatements'))

        @set('packagePath', atom.packages.resolvePackagePath('php-integrator-base'))

    ###*
     * Attaches listeners to listen to Atom configuration changes.
    ###
    attachListeners: () ->
        atom.config.onDidChange 'php-integrator-base.phpCommand', () =>
            @set('phpCommand', atom.config.get('php-integrator-base.phpCommand'))
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.composerCommand', () =>
            @set('composerCommand', atom.config.get('php-integrator-base.composerCommand'))
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.autoloadScripts', () =>
            @set('autoloadScripts', atom.config.get('php-integrator-base.autoloadScripts'))
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.classMapScripts', () =>
            @set('classMapScripts', atom.config.get('php-integrator-base.classMapScripts'))
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.insertNewlinesForUseStatements', () =>
            @set('insertNewlinesForUseStatements', atom.config.get('php-integrator-base.insertNewlinesForUseStatements'))
            @synchronizeToPhpConfig()
