fs = require 'fs'
path = require 'path'
rimraf = require 'rimraf'
mkdirp = require 'mkdirp'

module.exports =

##*
# Handles management of the (PHP) core that is needed to handle the server side.
##
class CoreManager
    ###*
     * The commit to download from the Composer repository.
     *
     * Currently set to version 1.6.3.
     *
     * @see https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
     *
     * @var {String}
    ###
    COMPOSER_COMMIT: 'c1ad3667731e9c5c1a21e5835c7e6a7eedc2e1fe'

    ###*
     * @var {String}
    ###
    COMPOSER_PACKAGE_NAME: 'Serenata/Serenata'

    ###*
     * @var {ComposerService}
    ###
    composerService: null

    ###*
     * @var {String}
    ###
    versionSpecification: null

    ###*
     * @var {String}
    ###
    folder: null

    ###*
     * @param {ComposerService} composerService
     * @param {String}          versionSpecification
     * @param {String}          folder
    ###
    constructor: (@composerService, @versionSpecification, @folder) ->

    ###*
     * @return {Promise}
    ###
    install: () ->
        @removeExistingFolderIfPresent()

        mkdirp(@getCoreSourcePath())

        return @composerService.run([
            'create-project',
            @COMPOSER_PACKAGE_NAME,
            @composerService.phpInvoker.normalizePlatformAndRuntimePath(@getCoreSourcePath()),
            @versionSpecification,
            # https://github.com/php-integrator/atom-base/issues/303 - Unfortunately the dist involves using a ZIP on
            # Windows, which in turn causes temporary files to be created that exceed the maximum path limit. Hence
            # source installation is preferred.
            # '--prefer-dist',
            '--prefer-source',
            '--no-interaction',
            '--no-dev',
            '--no-progress'
        ], @folder)

    ###*
     * @return {Boolean}
    ###
    removeExistingFolderIfPresent: () ->
        if fs.existsSync(@getCoreSourcePath())
            rimraf.sync(@getCoreSourcePath())

    ###*
     * @return {Boolean}
    ###
    isInstalled: () ->
        return fs.existsSync(@getComposerLockFilePath())

    ###*
     * @return {String}
    ###
    getComposerLockFilePath: () ->
        return path.join(@getCoreSourcePath(), 'composer.lock')

    ###*
     * @return {String}
    ###
    getCoreSourcePath: () ->
        if @folder == null
            throw new Error('No folder configured for core installation')

        else if @versionSpecification == null
            throw new Error('No folder configured for core installation')

        coreSourcePath = path.join(@folder, @versionSpecification)

        if not coreSourcePath? or coreSourcePath.length == 0
            throw new Error('Failed producing a usable core source folder path')

        if coreSourcePath == '/'
            # Can never be too careful with dynamic path generation (and recursive deletes).
            throw new Error('Nope, I\'m not going to use your filesystem root')

        return coreSourcePath
