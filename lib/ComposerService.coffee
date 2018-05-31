fs = require 'fs'
path = require 'path'
download = require 'download'
child_process = require 'child_process'

module.exports =

##*
# Handles usage of Composer (PHP package manager).
##
class ComposerService
    ###*
     * The commit to download from the Composer repository.
     *
     * Currently set to version 1.6.4.
     *
     * @see https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
     *
     * @var {String}
    ###
    COMPOSER_COMMIT: '01a340a59c504c900251e3e189d0cb2008e888c6'

    ###*
     * @var {Object}
    ###
    phpInvoker: null

    ###*
     * @var {String}
    ###
    folder: null

    ###*
     * @param {Object} phpInvoker
     * @param {String} folder
    ###
    constructor: (@phpInvoker, @folder) ->

    ###*
     * @param {Array}       parameters
     * @param {String|null} workingDirectory
     *
     * @return {Promise}
    ###
    run: (parameters, workingDirectory = null) ->
        return @installIfNecessary().then () =>
            options = {}

            if workingDirectory?
                options.cwd = workingDirectory

            return new Promise (resolve, reject) =>
                process = @phpInvoker.invoke([@getPath()].concat(parameters), [], options)

                process.stdout.on 'data', (data) =>
                    console.info('Composer has something to say:', data.toString())

                process.stderr.on 'data', (data) =>
                    # Valid information is also sent via STDERR, see also
                    # https://github.com/composer/composer/issues/3787#issuecomment-76167739
                    console.info('Composer has something to say:', data.toString())

                process.on 'close', (code) =>
                    console.debug('Composer exited with status code:', code)

                    if code != 0
                        reject()

                    else
                        resolve()

    ###*
     * @return {Promise}
    ###
    installIfNecessary: () ->
        if @isInstalled()
            return new Promise (resolve, reject) ->
                resolve()

        return @install()

    ###*
     * @param {Boolean}
    ###
    isInstalled: () ->
        return true if fs.existsSync(@getPath())

    ###*
     * @return {Promise}
    ###
    install: () ->
        @download().then () =>
             parameters = [
                 @getInstallerFileFilePath(),
                 '--install-dir=' + @phpInvoker.normalizePlatformAndRuntimePath(@getInstallerFilePath()),
                 '--filename=' + @getFileName()
             ]

             return new Promise (resolve, reject) =>
                 process = @phpInvoker.invoke(parameters)

                 process.stdout.on 'data', (data) =>
                     console.debug('Composer installer has something to say:', data.toString())

                 process.stderr.on 'data', (data) =>
                     console.warn('Composer installer has errors to report:', data.toString())

                 process.on 'close', (code) =>
                     console.debug('Composer installer exited with status code:', code)

                     if code != 0
                         reject()

                     else
                         resolve()

    ###*
     * @return {Promise}
    ###
    download: () ->
        return download(
            'https://raw.githubusercontent.com/composer/getcomposer.org/' + @COMPOSER_COMMIT + '/web/installer',
            @getInstallerFilePath()
        )

    ###*
     * @return {String}
    ###
    getInstallerFilePath: () ->
        return @folder

    ###*
     * @return {String}
    ###
    getInstallerFileFileName: () ->
        return 'installer'

    ###*
     * @return {String}
    ###
    getInstallerFileFilePath: () ->
        return @phpInvoker.normalizePlatformAndRuntimePath(path.join(@getInstallerFilePath(), @getInstallerFileFileName()))

    ###*
     * @return {String}
    ###
    getPath: () ->
        return @phpInvoker.normalizePlatformAndRuntimePath(path.join(@getInstallerFilePath(), @getFileName()))

    ###*
     * @return {String}
    ###
    getFileName: () ->
        return 'composer.phar'
