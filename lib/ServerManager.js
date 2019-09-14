'use strict';

module.exports =

/**
 * Handles management of the (PHP) server that is needed to handle the server side.
 */
class ServerManager
{
    /**
     * @param {Object} phpInvoker
     * @param {String} folder
     */
    constructor(phpInvoker, folder) {
        this.phpInvoker = phpInvoker;
        this.folder = folder;

        this.distributionUploadHash = 'ef95a5799210ee8043ec3b832c458d96';
    }

    /**
     * @return Promise
     */
    async install() {
        // TODO: Should delete files in server folder, not entire server folder.
        // await this.removeExistingFolderIfPresent();

        const download = require('download');

        // TODO: Serenata offers PHARs for each PHP version it supports, but for now we can get away with using the
        // lowest PHP version, as newer versions are backwards compatible enough.
        await download(
            `https://gitlab.com/Serenata/Serenata/uploads/${this.distributionUploadHash}/7.1.zip`,
            this.phpInvoker.normalizePlatformAndRuntimePath(this.getServerSourcePath()),
            {
                filename: 'distribution.phar',
            }
        );

        return new Promise((resolve, reject) => {
            const fs = require('fs');

            fs.writeFile(this.getVersionSpecificationFilePath(), this.versionSpecification, (error) => {
                if (error) {
                    reject(new Error(error.message));
                } else {
                    resolve();
                }
            });
        });
    }

    /**
     * @return {Boolean}
     */
    async removeExistingFolderIfPresent() {
        const fs = require('fs');

        if (fs.existsSync(this.getServerSourcePath())) {
            const rimraf = require('rimraf');

            await rimraf(this.getServerSourcePath());
        }

        return new Promise((resolve) => {
            resolve();
        });
    }

    /**
     * @return {Boolean}
     */
    isInstalled() {
        const fs = require('fs');

        return fs.existsSync(this.getVersionSpecificationFilePath());
    }

    /**
     * @return {String}
     */
    getServerSourcePath() {
        if (this.folder === null || this.folder.length === 0) {
            throw new Error('Failed producing a usable server source folder path');
        } else if (this.folder === '/') {
            // Can never be too careful with dynamic path generation (and recursive deletes).
            throw new Error('Nope, I\'m not going to use your filesystem root');
        }

        return this.folder;
    }

    /**
     * @return {String}
     */
    getServerExecutablePath() {
        const path = require('path');

        return path.join(this.getServerSourcePath(), 'distribution.phar');
    }

    /**
     * @return {String}
     */
    getVersionSpecificationFilePath() {
        const path = require('path');

        return path.join(this.folder, this.distributionUploadHash);
    }
};
