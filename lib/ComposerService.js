/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let ComposerService;
const fs = require('fs');
const path = require('path');
const download = require('download');
const child_process = require('child_process');

module.exports =

//#*
// Handles usage of Composer (PHP package manager).
//#
(ComposerService = (function() {
    ComposerService = class ComposerService {
        static initClass() {
            /**
             * The commit to download from the Composer repository.
             *
             * Currently set to version 1.6.4.
             *
             * @see https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
             *
             * @var {String}
            */
            this.prototype.COMPOSER_COMMIT = '01a340a59c504c900251e3e189d0cb2008e888c6';
    
            /**
             * @var {Object}
            */
            this.prototype.phpInvoker = null;
    
            /**
             * @var {String}
            */
            this.prototype.folder = null;
        }

        /**
         * @param {Object} phpInvoker
         * @param {String} folder
        */
        constructor(phpInvoker, folder) {
            this.phpInvoker = phpInvoker;
            this.folder = folder;
        }

        /**
         * @param {Array}       parameters
         * @param {String|null} workingDirectory
         *
         * @return {Promise}
        */
        run(parameters, workingDirectory = null) {
            return this.installIfNecessary().then(() => {
                const options = {};

                if (workingDirectory != null) {
                    options.cwd = workingDirectory;
                }

                return new Promise((resolve, reject) => {
                    const process = this.phpInvoker.invoke([this.getPath()].concat(parameters), [], options);

                    process.stdout.on('data', data => {
                        return console.info('Composer has something to say:', data.toString());
                    });

                    process.stderr.on('data', data => {
                        // Valid information is also sent via STDERR, see also
                        // https://github.com/composer/composer/issues/3787#issuecomment-76167739
                        return console.info('Composer has something to say:', data.toString());
                    });

                    return process.on('close', code => {
                        console.debug('Composer exited with status code:', code);

                        if (code !== 0) {
                            return reject();

                        } else {
                            return resolve();
                        }
                    });
                });
            });
        }

        /**
         * @return {Promise}
        */
        installIfNecessary() {
            if (this.isInstalled()) {
                return new Promise(function(resolve, reject) {
                    return resolve();
                });
            }

            return this.install();
        }

        /**
         * @param {Boolean}
        */
        isInstalled() {
            if (fs.existsSync(this.getPath())) { return true; }
        }

        /**
         * @return {Promise}
        */
        install() {
            return this.download().then(() => {
                const parameters = [
                    this.getInstallerFileFilePath(),
                    `--install-dir=${this.phpInvoker.normalizePlatformAndRuntimePath(this.getInstallerFilePath())}`,
                    `--filename=${this.getFileName()}`
                ];

                return new Promise((resolve, reject) => {
                    const process = this.phpInvoker.invoke(parameters);

                    process.stdout.on('data', data => {
                        return console.debug('Composer installer has something to say:', data.toString());
                    });

                    process.stderr.on('data', data => {
                        return console.warn('Composer installer has errors to report:', data.toString());
                    });

                    return process.on('close', code => {
                        console.debug('Composer installer exited with status code:', code);

                        if (code !== 0) {
                            return reject();

                        } else {
                            return resolve();
                        }
                    });
                });
            });
        }

        /**
         * @return {Promise}
        */
        download() {
            return download(
                `https://raw.githubusercontent.com/composer/getcomposer.org/${this.COMPOSER_COMMIT}/web/installer`,
                this.getInstallerFilePath()
            );
        }

        /**
         * @return {String}
        */
        getInstallerFilePath() {
            return this.folder;
        }

        /**
         * @return {String}
        */
        getInstallerFileFileName() {
            return 'installer';
        }

        /**
         * @return {String}
        */
        getInstallerFileFilePath() {
            return this.phpInvoker.normalizePlatformAndRuntimePath(path.join(this.getInstallerFilePath(), this.getInstallerFileFileName()));
        }

        /**
         * @return {String}
        */
        getPath() {
            return this.phpInvoker.normalizePlatformAndRuntimePath(path.join(this.getInstallerFilePath(), this.getFileName()));
        }

        /**
         * @return {String}
        */
        getFileName() {
            return 'composer.phar';
        }
    };
    ComposerService.initClass();
    return ComposerService;
})());
