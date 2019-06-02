const fs = require('fs');
const path = require('path');
const download = require('download');

module.exports =

/**
 * Handles usage of Composer (PHP package manager).
 */
class ComposerService
{
    /**
     * @param {Object} phpInvoker
     * @param {String} folder
    */
    constructor(phpInvoker, folder) {
        /**
         * The commit to download from the Composer repository.
         *
         * Currently set to version 1.8.5.
         *
         * @see https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
         */
        this.COMPOSER_COMMIT = 'ffdc3c7fcb7c0f2a806508a868a35d13177a5a51';

        this.phpInvoker = phpInvoker;
        this.folder = folder;
    }

    /**
     * @param {Array}       parameters
     * @param {String|null} workingDirectory
     *
     * @return {Promise}
     */
    async run(parameters, workingDirectory = null) {
        await this.installIfNecessary();

        const options = {};

        if (workingDirectory != null) {
            options.cwd = workingDirectory;
        }

        return new Promise((resolve, reject) => {
            const process = this.phpInvoker.invoke([this.getPath()].concat(parameters), [], options);

            process.stdout.on('data', data => {
                console.info('Composer has something to say:', data.toString());
            });

            process.stderr.on('data', data => {
                // Valid information is also sent via STDERR, see also
                // https://github.com/composer/composer/issues/3787#issuecomment-76167739
                console.info('Composer has something to say:', data.toString());
            });

            return process.on('close', code => {
                console.debug('Composer exited with status code:', code);

                if (code !== 0) {
                    reject();
                } else {
                    resolve();
                }
            });
        });
    }

    /**
     * @return {Promise}
     */
    async installIfNecessary() {
        if (this.isInstalled()) {
            return new Promise(function(resolve/*, reject*/) {
                resolve();
            });
        }

        await this.install();
    }

    /**
     * @param {Boolean}
     */
    isInstalled() {
        if (fs.existsSync(this.getPath())) {
            return true;
        }
    }

    /**
     * @return {Promise}
     */
    async install() {
        await this.download();

        const parameters = [
            this.getInstallerFileFilePath(),
            `--install-dir=${this.phpInvoker.normalizePlatformAndRuntimePath(this.getInstallerFilePath())}`,
            `--filename=${this.getFileName()}`
        ];

        return new Promise((resolve, reject) => {
            const process = this.phpInvoker.invoke(parameters);

            process.stdout.on('data', data => {
                console.debug('Composer installer has something to say:', data.toString());
            });

            process.stderr.on('data', data => {
                console.warn('Composer installer has errors to report:', data.toString());
            });

            return process.on('close', code => {
                console.debug('Composer installer exited with status code:', code);

                if (code !== 0) {
                    reject();
                } else {
                    resolve();
                }
            });
        });
    }

    /**
     * @return {Promise}
     */
    async download() {
        await download(
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
        return this.phpInvoker.normalizePlatformAndRuntimePath(
            path.join(this.getInstallerFilePath(), this.getInstallerFileFileName())
        );
    }

    /**
     * @return {String}
     */
    getPath() {
        return this.phpInvoker.normalizePlatformAndRuntimePath(
            path.join(this.getInstallerFilePath(), this.getFileName())
        );
    }

    /**
     * @return {String}
     */
    getFileName() {
        return 'composer.phar';
    }
};
