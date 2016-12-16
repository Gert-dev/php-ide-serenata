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
    phpBinary: null

    ###*
     * @var {String}
    ###
    folder: null

    ###*
     * @param {String} phpBinary
     * @param {String} folder
    ###
    constructor: (@phpBinary, @folder) ->

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
                process = child_process.spawn(@phpBinary, [@getPath()].concat(parameters), options)

                process.stdout.on 'data', (data) =>
                    console.debug('Composer has something to say:', data.toString())

                process.stderr.on 'data', (data) =>
                    console.warn('Composer has errors to report:', data.toString())

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
                 '--install-dir=' + @folder + '',
                 '--filename=' + @getFileName()
             ]

             return new Promise (resolve, reject) =>
                 process = child_process.spawn(@phpBinary, parameters)

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
        return path.join(@getInstallerFilePath(), @getInstallerFileFileName())

    ###*
     * @return {String}
    ###
    getPath: () ->
        return path.join(@folder, @getFileName())

    ###*
     * @return {String}
    ###
    getFileName: () ->
        return 'composer.phar'
