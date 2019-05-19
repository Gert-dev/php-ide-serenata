/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS103: Rewrite code to no longer use __guard__
 * DS205: Consider reworking code to avoid use of IIFEs
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let ProjectManager;

const fs = require('fs');
const mkdirp = require('mkdirp');

module.exports =

//#*
// Handles project management
//#
(ProjectManager = (function() {
    ProjectManager = class ProjectManager {
        static initClass() {
            /**
             * @var {Object}
            */
            this.prototype.proxy = null;

            /**
             * The service instance from the project-manager package.
             *
             * @var {Object|null}
            */
            this.prototype.activeProject = null;
        }

        /**
         * @param {Object} proxy
        */
        constructor(proxy) {
            this.proxy = proxy;
        }

        /**
         * Sets up the specified project for usage with the Serenata server.
         *
         * @param {String} mainFolder
        */
        setUpProject(mainFolder) {
            const configFileFolderPath = mainFolder + '/.serenata';
            const configFilePath = configFileFolderPath + '/config.json';

            if (fs.existsSync(configFilePath)) {
                throw new Error(
                    'The currently active project was already initialized. To prevent existing settings from being ' +
                    'lost, the request has been aborted.'
                );
            }

            const template =
`{
    "uris": [
        "${mainFolder}"
    ],
    "phpVersion": 7.3,
    "excludedPathExpressions": [],
    "fileExtensions": [
        "php"
    ]
}`;

            mkdirp.sync(configFileFolderPath);
            fs.writeFileSync(configFilePath, template);
        }

        /**
         * @param {String} projectFolder
         *
         * @return {Boolean}
         */
        shouldStartForProject(projectFolder) {
            return fs.existsSync(this.getConfigFilePath(projectFolder));
        }

        /**
         * @param {String} projectFolder
         */
        tryLoad(projectFolder) {
            if (!this.shouldStartForProject(projectFolder)) {
                return;
            }

            this.load(projectFolder);
        }

        /**
         * @param {String} projectFolder
         */
        load(projectFolder) {
            this.activeProject = JSON.parse(fs.readFileSync(this.getConfigFilePath(projectFolder)));

            // TODO: Need way to trigger server once the project has been set up.
            // this.initializeCurrentProject();
        }

        /**
         * @param {String} projectFolder
         *
         * @return {String}
         */
        getConfigFilePath(projectFolder) {
            return projectFolder + '/.serenata/config.json';
        }

        /**
         * @return {Object|null}
        */
        getCurrentProjectSettings() {
            return this.activeProject;
        }
    };
    ProjectManager.initClass();
    return ProjectManager;
})());

function __guard__(value, transform) {
    return (typeof value !== 'undefined' && value !== null) ? transform(value) : undefined;
}
