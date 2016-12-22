fs = require 'fs'
path = require 'path'
semver = require 'semver'

module.exports =

##*
# Handles management of the (PHP) core that is needed to handle the server side.
##
class CoreManager
    ###*
     * The commit to download from the Composer repository.
     *
     * Currently set to version 1.2.4.
     *
     * @see https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
     *
     * @var {String}
    ###
    COMPOSER_COMMIT: 'd0310b646229c3dc57b71bfea2f14ed6c560a5bd'

    ###*
     * @var {String}
    ###
    COMPOSER_PACKAGE_NAME: 'php-integrator/core'

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
        requirePromise = @composerService.run([
            'require',
            @COMPOSER_PACKAGE_NAME,
            @versionSpecification,
            '--no-update'
        ], @folder)

        return requirePromise.then () =>
            # Don't install development dependencies.
            return @composerService.run([
                'update',
                '--prefer-dist',
                '--no-dev'
            ], @folder)

    ###*
     * @return {Boolean}
    ###
    isInstalled: () ->
        return fs.existsSync(@getComposerLockFilePath())

    ###*
     * @return {Boolean}
    ###
    isOutdated: () ->
        data = @getInstalledComposerPackageData(@COMPOSER_PACKAGE_NAME)

        return false if semver.satisfies(data.version, @versionSpecification)
        return true

    ###*
     * @return {Object}
    ###
    getInstalledComposerPackagesData: () ->
        lockFileData = JSON.parse(fs.readFileSync(@getComposerLockFilePath()))

        if not lockFileData.packages?
            throw new Error('Not a valid active Composer project')

        return lockFileData.packages

    ###*
     * @param {String} packageName
     *
     * @return {Object}
    ###
    getInstalledComposerPackageData: (packageName) ->
        packagesData = @getInstalledComposerPackagesData()

        for packageData in packagesData
            if packageData.name == packageName
                return packageData

        throw new Error('Package with name ' + packageName + ' not found')

    ###*
     * @return {String}
    ###
    getComposerLockFilePath: () ->
        return path.join(@folder, 'composer.lock')
