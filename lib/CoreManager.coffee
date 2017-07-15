fs = require 'fs'
path = require 'path'

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
        return @composerService.run([
            'create-project',
            @COMPOSER_PACKAGE_NAME,
            @getCoreSourcePath(),
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
        return path.join(@folder, @versionSpecification)
