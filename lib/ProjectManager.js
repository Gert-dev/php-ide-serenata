/* global atom */

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
const {Directory} = require('atom');

const fs = require('fs');
const path = require('path');

const process = require('process');

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
             * @var {Object}
            */
            this.prototype.indexingMediator = null;

            /**
             * The service instance from the project-manager package.
             *
             * @var {Object|null}
            */
            this.prototype.activeProject = null;

            /**
             * Whether project indexing is currently happening.
             *
             * @var {bool}
            */
            this.prototype.isProjectIndexingFlag = false;

            /**
             * Keeps track of files that are being indexed.
             *
             * @var {Object}
            */
            this.prototype.indexMap = null;
        }

        /**
         * @param {Object} proxy
         * @param {Object} indexingMediator
        */
        constructor(proxy, indexingMediator) {
            this.proxy = proxy;
            this.indexingMediator = indexingMediator;
            this.indexMap = {};
        }

        /**
         * @return {Object|null}
        */
        getActiveProject() {
            return this.activeProject;
        }

        /**
         * @return {bool}
        */
        hasActiveProject() {
            if (this.getActiveProject() != null) {
                return true;
            }

            return false;
        }

        /**
         * @return {bool}
        */
        isProjectIndexing() {
            return this.isProjectIndexingFlag;
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

            this.initializeCurrentProject();
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
         * Retrieves a list of file extensions to include in indexing.
         *
         * @param {Object} project
         *
         * @return {Array}
        */
        getFileExtensionsToIndex(project) {
            let fileExtensions = project != null ? project.fileExtensions : undefined;

            if ((fileExtensions == null)) {
                fileExtensions = [];
            }

            return fileExtensions;
        }

        /**
         * Retrieves a list of absolute paths to exclude from indexing.
         *
         * @param {Object} project
         *
         * @return {Array}
        */
        getAbsoluteExcludedPaths(project) {
            let excludedPaths = project != null ? project.excludedPathExpressions : undefined;

            if ((excludedPaths == null)) {
                excludedPaths = [];
            }

            const absoluteExcludedPaths = [];

            for (const excludedPath of excludedPaths) {
                if (path.isAbsolute(excludedPath)) {
                    absoluteExcludedPaths.push(excludedPath);

                } else {
                    const matches = excludedPath.match(/^\{(\d+)\}(\/.*)$/);

                    if (matches != null) {
                        const index = matches[1];

                        // Relative paths starting with {n} are relative to the project path at index {n}, e.g.
                        // "{0}/test".
                        if (index > project.uris.length) {
                            throw new Error(
                                `Requested project path index ${index}, but the project does not have that many paths!`
                            );
                        }

                        absoluteExcludedPaths.push(project.uris[index] + matches[2]);

                    } else {
                        absoluteExcludedPaths.push(path.normalize(excludedPath));
                    }
                }
            }

            return absoluteExcludedPaths;
        }

        /**
         * Indexes the project asynchronously.
         *
         * @param {Object} project
         *
         * @return {Promise}
        */
        performIndex(project) {
            return this.indexingMediator.reindex(
                project.uris,
                null,
                this.getAbsoluteExcludedPaths(project),
                this.getFileExtensionsToIndex(project)
            );
        }

        /**
         * Performs a project index, but only if one is not currently already happening.
         *
         * @param {Object} project
         *
         * @return {Promise|null}
        */
        attemptIndex(project) {
            if (this.isProjectIndexing()) { return null; }

            this.isProjectIndexingFlag = true;

            const handler = () => {
                return this.isProjectIndexingFlag = false;
            };

            const successHandler = handler;
            const failureHandler = handler;

            return this.performIndex(project).then(successHandler, failureHandler);
        }

        /**
         * Indexes the current project, but only if one is not currently already happening.
         *
         * @return {Promise}
        */
        attemptCurrentProjectIndex() {
            return this.attemptIndex(this.getActiveProject());
        }

        /**
         * Initializes the project.
         *
         * @return {Promise|null}
        */
        initializeCurrentProject() {
            return this.indexingMediator.initialize(this.activeProject.uris[0]);
        }

        /**
         * Indexes a file asynchronously.
         *
         * @param {Object}      project
         * @param {String}      fileName The file to index.
         * @param {String|null} source   The source code of the file to index.
         *
         * @return {CancellablePromise}
        */
        performFileIndex(project, fileName, source = null) {
            return this.indexingMediator.reindex(
                fileName,
                source,
                this.getAbsoluteExcludedPaths(project),
                this.getFileExtensionsToIndex(project)
            );
        }

        /**
         * Performs a file index.
         *
         * @param {Object}      project
         * @param {String}      fileName The file to index.
         * @param {String|null} source   The source code of the file to index.
         *
         * @return {Promise|null}
        */
        attemptFileIndex(project, fileName, source = null) {
            if (fileName in this.indexMap) {
                this.indexMap[fileName].cancel();
            }

            this.indexMap[fileName] = this.performFileIndex(project, fileName, source);

            const onIndexFinish = () => {
                return delete this.indexMap[fileName];
            };

            return this.indexMap[fileName].then(onIndexFinish, onIndexFinish);
        }

        /**
         * Indexes the current project asynchronously.
         *
         * @param {String}      fileName The file to index.
         * @param {String|null} source   The source code of the file to index.
         *
         * @return {Promise}
        */
        attemptCurrentProjectFileIndex(fileName, source = null) {
            return this.attemptFileIndex(this.getActiveProject(),  fileName, source);
        }

        /**
         * @return {Object|null}
        */
        getCurrentProjectSettings() {
            return this.getActiveProject();
        }

        /**
         * Indicates if the specified file is part of the project.
         *
         * @param {Object} project
         * @param {String} fileName
         *
         * @return {bool}
        */
        isFilePartOfProject(project, fileName) {
            for (const projectDirectory of project.uris) {
                const projectDirectoryObject = new Directory(projectDirectory);

                // #295 - Resolve home folders. The core supports this out of the box, but we still need to limit files to
                // the workspace here. Atom is picky about this: if the project contains a tilde, the contains method below
                // will only return true if the file path also contains the tilde, which is not the case by default for
                // editor file paths.
                if (projectDirectory.startsWith('~')) {
                    fileName = fileName.replace(this.getHomeFolderPath(), '~');
                }

                if (projectDirectoryObject.contains(fileName)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @return {String}
        */
        getHomeFolderPath() {
            const homeFolderVarName = process.platform === 'win32' ? 'USERPROFILE' : 'HOME';

            return process.env[homeFolderVarName];
        }

        /**
         * Indicates if the specified file is part of the current project.
         *
         * @param {String} fileName
         *
         * @return {bool}
        */
        isFilePartOfCurrentProject(fileName) {
            return this.isFilePartOfProject(this.getActiveProject(), fileName);
        }
    };
    ProjectManager.initClass();
    return ProjectManager;
})());

function __guard__(value, transform) {
    return (typeof value !== 'undefined' && value !== null) ? transform(value) : undefined;
}
