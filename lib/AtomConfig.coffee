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
        @data.php                            = atom.config.get('php-integrator-base.phpCommand')
        @data.composer                       = atom.config.get('php-integrator-base.composerCommand')
        @data.autoload                       = atom.config.get('php-integrator-base.autoloadScripts')
        @data.classmap                       = atom.config.get('php-integrator-base.classMapScripts')
        @data.insertNewlinesForUseStatements = atom.config.get('php-integrator-base.insertNewlinesForUseStatements')

        @data.packagePath                    = atom.packages.resolvePackagePath('php-integrator-base')

    ###*
     * Attaches listeners to listen to Atom configuration changes.
    ###
    attachListeners: () ->
        atom.config.onDidChange 'php-integrator-base.phpCommand', () =>
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.composerCommand', () =>
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.autoloadScripts', () =>
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.classMapScripts', () =>
            @synchronizeToPhpConfig()

        atom.config.onDidChange 'php-integrator-base.insertNewlinesForUseStatements', () =>
            @synchronizeToPhpConfig()
