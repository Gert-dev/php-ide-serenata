fs = require 'fs'
namespace = require './namespace.coffee'

module.exports =
    ###*
     * Contains the configuration
    ###
    config: {}

    ###*
     * Retrieves the plugin's configuration.
    ###
    getConfig: () ->
        @config['php'] = atom.config.get('php-integrator-base.phpCommand')
        @config['composer'] = atom.config.get('php-integrator-base.composerCommand')
        @config['autoload'] = atom.config.get('php-integrator-base.autoloadScripts')
        @config['classmap'] = atom.config.get('php-integrator-base.classMapScripts')
        @config['insertNewlinesForUseStatements'] = atom.config.get('php-integrator-base.insertNewlinesForUseStatements')

        # See also https://secure.php.net/urlhowto.php .
        @config['php_documentation_base_url'] = {
            functions: 'https://secure.php.net/function.'
        }

        @config['packagePath'] = atom.packages.resolvePackagePath('php-integrator-base')

    ###*
     * Writes configuration in "php lib" folder
    ###
    writeConfig: () ->
        @getConfig()

        files = ""
        for file in @config.autoload
            files += "'#{file}',"

        classmaps = ""
        for classmap in @config.classmap
            classmaps += "'#{classmap}',"

        text = "<?php
          $config = array(
            'composer' => '#{@config.composer}',
            'php' => '#{@config.php}',
            'autoload' => array(#{files}),
            'classmap' => array(#{classmaps})
          );
        "

        fs.writeFileSync(@config.packagePath + '/php/tmp.php', text)

    ###*
     * Tests the user's PHP and Composer configuration.
     * @return {bool}
    ###
    testConfig: (interactive) ->
        @getConfig()

        exec = require "child_process"
        testResult = exec.spawnSync(@config.php, ["-v"])

        errorTitle = 'php-integrator-base - Incorrect setup!'
        errorMessage = 'Either PHP or Composer is not correctly set up and as a result PHP autocompletion will not work. ' +
          'Please visit the settings screen to correct this error. If you are not specifying an absolute path for PHP or ' +
          'Composer, make sure they are in your PATH.'

        if testResult.status = null or testResult.status != 0
            atom.notifications.addError(errorTitle, {'detail': errorMessage})
            return false

        # Test Composer.
        testResult = exec.spawnSync(@config.php, [@config.composer, "--version"])

        if testResult.status = null or testResult.status != 0
            testResult = exec.spawnSync(@config.composer, ["--version"])

            # Try executing Composer directly.
            if testResult.status = null or testResult.status != 0
                atom.notifications.addError(errorTitle, {'detail': errorMessage})
                return false

        if interactive
            atom.notifications.addSuccess('php-integrator-base - Success', {'detail': 'Configuration OK !'})

        return true

    ###*
     * Init function called on package activation
     * Register config events and write the first config
    ###
    init: () ->
        # Command for namespaces
        atom.commands.add 'atom-workspace', 'php-integrator-base:namespace': =>
            namespace.createNamespace(atom.workspace.getActivePaneItem())

        @getConfig()

        # Command to test configuration
        atom.commands.add 'atom-workspace', 'php-integrator-base:configuration': =>
            @testConfig(true)

        atom.config.onDidChange 'php-integrator-base.phpCommand', () =>
            @writeConfig()

        atom.config.onDidChange 'php-integrator-base.composerCommand', () =>
            @writeConfig()

        atom.config.onDidChange 'php-integrator-base.autoloadScripts', () =>
            @writeConfig()

        atom.config.onDidChange 'php-integrator-base.classMapScripts', () =>
            @writeConfig()

        atom.config.onDidChange 'php-integrator-base.insertNewlinesForUseStatements', () =>
            @writeConfig()
