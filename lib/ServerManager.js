'use strict';

const fs = require('fs');
const path = require('path');
const rimraf = require('rimraf');
const mkdirp = require('mkdirp');

module.exports =

/**
 * Handles management of the (PHP) server that is needed to handle the server side.
 */
class ServerManager
{
    /**
     * @param {ComposerService} composerService
     * @param {String}          versionSpecification
     * @param {String}          folder
     */
    constructor(composerService, versionSpecification, folder) {
        this.COMPOSER_PACKAGE_NAME = 'Serenata/Serenata';

        this.composerService = composerService;
        this.versionSpecification = versionSpecification;
        this.folder = folder;
    }

    async install() {
        await this.removeExistingFolderIfPresent();

        mkdirp.sync(this.getServerSourcePath());

        await this.composerService.run([
            'create-project',
            this.COMPOSER_PACKAGE_NAME,
            this.composerService.phpInvoker.normalizePlatformAndRuntimePath(this.getServerSourcePath()),
            this.versionSpecification,
            // https://github.com/php-integrator/atom-base/issues/303 - Unfortunately the dist involves using a ZIP
            // on Windows, which in turn causes temporary files to be created that exceed the maximum path limit.
            // Hence source installation is preferred.
            // '--prefer-dist',
            '--prefer-source',
            '--no-interaction',
            '--no-dev',
            '--no-progress'
        ], this.folder);

        return new Promise((resolve, reject) => {
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
        if (fs.existsSync(this.getServerSourcePath())) {
            await rimraf(this.getServerSourcePath());
        }
    }

    /**
     * @return {Boolean}
     */
    isInstalled() {
        return fs.existsSync(this.getVersionSpecificationFilePath());
    }

    /**
     * @return {String}
     */
    getServerSourcePath() {
        if (this.folder === null) {
            throw new Error('No folder configured for server installation');
        } else if (this.versionSpecification === null) {
            throw new Error('No folder configured for server installation');
        }

        const serverSourcePath = path.join(this.folder, 'files');

        if (serverSourcePath == null || serverSourcePath.length === 0) {
            throw new Error('Failed producing a usable server source folder path');
        }

        if (serverSourcePath === '/') {
            // Can never be too careful with dynamic path generation (and recursive deletes).
            throw new Error('Nope, I\'m not going to use your filesystem root');
        }

        return serverSourcePath;
    }

    /**
     * @return {String}
     */
    getVersionSpecificationFilePath() {
        return path.join(this.folder, this.versionSpecification);
    }
};
