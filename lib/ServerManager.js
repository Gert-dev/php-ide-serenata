/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let ServerManager;
const fs = require('fs');
const path = require('path');
const rimraf = require('rimraf');
const mkdirp = require('mkdirp');

module.exports =

//#*
// Handles management of the (PHP) server that is needed to handle the server side.
//#
(ServerManager = (function() {
    ServerManager = class ServerManager {
        static initClass() {
        /**
         * The commit to download from the Composer repository.
         *
         * Currently set to version 1.6.3.
         *
         * @see https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
         *
         * @var {String}
        */
            this.prototype.COMPOSER_COMMIT = 'c1ad3667731e9c5c1a21e5835c7e6a7eedc2e1fe';

            /**
         * @var {String}
        */
            this.prototype.COMPOSER_PACKAGE_NAME = 'Serenata/Serenata';

            /**
         * @var {ComposerService}
        */
            this.prototype.composerService = null;

            /**
         * @var {String}
        */
            this.prototype.versionSpecification = null;

            /**
         * @var {String}
        */
            this.prototype.folder = null;
        }

        /**
       * @param {ComposerService} composerService
       * @param {String}          versionSpecification
       * @param {String}          folder
      */
        constructor(composerService, versionSpecification, folder) {
            this.composerService = composerService;
            this.versionSpecification = versionSpecification;
            this.folder = folder;
        }

        /**
       * @return {Promise}
      */
        async install() {
            this.removeExistingFolderIfPresent();

            mkdirp(this.getServerSourcePath());

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
                        reject();
                    } else {
                        resolve();
                    }
                });
            });

        }

        /**
       * @return {Boolean}
      */
        removeExistingFolderIfPresent() {
            if (fs.existsSync(this.getServerSourcePath())) {
                return rimraf.sync(this.getServerSourcePath());
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

            if ((serverSourcePath == null) || (serverSourcePath.length === 0)) {
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
    ServerManager.initClass();
    return ServerManager;
})());
