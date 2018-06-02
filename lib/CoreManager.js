/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let CoreManager;
const fs = require('fs');
const path = require('path');
const rimraf = require('rimraf');
const mkdirp = require('mkdirp');

module.exports =

//#*
// Handles management of the (PHP) core that is needed to handle the server side.
//#
(CoreManager = (function() {
    CoreManager = class CoreManager {
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
        install() {
            this.removeExistingFolderIfPresent();

            mkdirp(this.getCoreSourcePath());

            return this.composerService.run([
                'create-project',
                this.COMPOSER_PACKAGE_NAME,
                this.composerService.phpInvoker.normalizePlatformAndRuntimePath(this.getCoreSourcePath()),
                this.versionSpecification,
                // https://github.com/php-integrator/atom-base/issues/303 - Unfortunately the dist involves using a ZIP on
                // Windows, which in turn causes temporary files to be created that exceed the maximum path limit. Hence
                // source installation is preferred.
                // '--prefer-dist',
                '--prefer-source',
                '--no-interaction',
                '--no-dev',
                '--no-progress'
            ], this.folder);
        }

        /**
       * @return {Boolean}
      */
        removeExistingFolderIfPresent() {
            if (fs.existsSync(this.getCoreSourcePath())) {
                return rimraf.sync(this.getCoreSourcePath());
            }
        }

        /**
       * @return {Boolean}
      */
        isInstalled() {
            return fs.existsSync(this.getComposerLockFilePath());
        }

        /**
       * @return {String}
      */
        getComposerLockFilePath() {
            return path.join(this.getCoreSourcePath(), 'composer.lock');
        }

        /**
       * @return {String}
      */
        getCoreSourcePath() {
            if (this.folder === null) {
                throw new Error('No folder configured for core installation');

            } else if (this.versionSpecification === null) {
                throw new Error('No folder configured for core installation');
            }

            const coreSourcePath = path.join(this.folder, this.versionSpecification);

            if ((coreSourcePath == null) || (coreSourcePath.length === 0)) {
                throw new Error('Failed producing a usable core source folder path');
            }

            if (coreSourcePath === '/') {
                // Can never be too careful with dynamic path generation (and recursive deletes).
                throw new Error('Nope, I\'m not going to use your filesystem root');
            }

            return coreSourcePath;
        }
    };
    CoreManager.initClass();
    return CoreManager;
})());
