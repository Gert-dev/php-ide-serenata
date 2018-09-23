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
            const configFilePath = mainFolder + '/.serenata/config.json';

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
    "phpVersion": 7.2,
    "excludedPathExpressions": [],
    "fileExtensions": [
        "php"
    ]
}`;

            fs.writeFileSync(configFilePath, template);
        }

        /**
         * @param {String} projectFolder
         */
        tryLoad(projectFolder) {
            if (!fs.existsSync(this.getConfigFilePath(projectFolder))) {
                return;
            }

            this.load(projectFolder);
        }

        /**
         * @param {String} projectFolder
         */
        load(projectFolder) {
            this.activeProject = JSON.parse(fs.readFileSync(this.getConfigFilePath(projectFolder)));

            this.proxy.setIsActive(true);

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
