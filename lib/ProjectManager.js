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

            /**
             * Default settings for projects.
             *
             * Note that this object will be shared across instances!
             *
             * @var {Object}
            */
            this.prototype.defaultProjectSettings = {
                enabled: true,
                serenata: {
                    enabled: true,
                    phpVersion: 7.2,
                    excludedPaths: [],
                    fileExtensions: ['php']
                }
            };
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
         * Sets up the specified project for usage with this package.
         *
         * Default settings will be stored inside the package, if they aren't already present. If they already exist, they
         * will not be overwritten.
         *
         * Note that this method does not explicitly request persisting settings from the external project manager service.
         *
         * @param {Object} project
         *
         * @return {Object} The new settings of the project (that could be persisted).
        */
        setUpProject(project) {
            const projectPhpSettings = (project.getProps().php != null) ? project.getProps().php : {};

            if (projectPhpSettings.serenata != null) {
                throw new Error(`\
The currently active project was already initialized. To prevent existing settings from getting lost,
the request has been aborted.\
`);
            }

            if (!projectPhpSettings.enabled) {
                projectPhpSettings.enabled = true;
            }

            if ((projectPhpSettings.serenata == null)) {
                projectPhpSettings.serenata = this.defaultProjectSettings.serenata;
            }

            const existingProps = project.getProps();
            existingProps.php = projectPhpSettings;

            return existingProps;
        }

        /**
         * @param {Object} project
        */
        load(project) {
            this.activeProject = null;

            if (__guard__(project.getProps().php, x => x.enabled) !== true) { return; }

            const projectSettings = this.getProjectSettings(project);

            if ((projectSettings != null ? projectSettings.enabled : undefined) !== true) { return; }

            this.validateProject(project);

            this.activeProject = project;

            this.proxy.setIndexDatabaseName(this.getIndexDatabaseName(project));

            const successHandler = repository => {
                if ((repository == null)) { return; }
                if ((repository.async == null)) { return; }

                // Will trigger on things such as git checkout.
                return repository.async.onDidChangeStatuses(() => {
                    return this.attemptIndex(project);
                });
            };

            const failureHandler = () => {
            };

            return (() => {
                const result = [];

                for (const projectDirectory of this.getProjectPaths(project)) {
                    const projectDirectoryObject = new Directory(projectDirectory);

                    result.push(atom.project.repositoryForDirectory(projectDirectoryObject).then(successHandler, failureHandler));
                }

                return result;
            })();
        }

        /**
         * @param {Object}
         *
         * @return {String}
        */
        getIndexDatabaseName(project) {
            return project.getProps().title;
        }

        /**
         * Validates a project by validating its settings.
         *
         * Throws an Error if something is not right with the project.
         *
         * @param {Object} project
        */
        validateProject(project) {
            const projectSettings = this.getProjectSettings(project);

            if ((projectSettings == null)) {
                throw new Error(
                    'No project settings were found under a node called "php.serenata" in your project settings'
                );
            }

            const { phpVersion } = projectSettings;

            if (isNaN(parseFloat(phpVersion)) || !isFinite(phpVersion)) {
                throw new Error(`\
PHP version in your project settings must be a number such as 7.2\
`);
            }
        }

        /**
         * Retrieves a list of file extensions to include in indexing.
         *
         * @param {Object} project
         *
         * @return {Array}
        */
        getFileExtensionsToIndex(project) {
            const projectPaths = this.getProjectPaths(project);
            const projectSettings = this.getProjectSettings(project);

            let fileExtensions = projectSettings != null ? projectSettings.fileExtensions : undefined;

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
            const projectPaths = this.getProjectPaths(project);
            const projectSettings = this.getProjectSettings(project);

            let excludedPaths = projectSettings != null ? projectSettings.excludedPaths : undefined;

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

                        // Relative paths starting with {n} are relative to the project path at index {n}, e.g. "{0}/test".
                        if (index > projectPaths.length) {
                            throw new Error(`Requested project path index ${index}, but the project does not have that many paths!`);
                        }

                        absoluteExcludedPaths.push(projectPaths[index] + matches[2]);

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
            const successHandler = () => {
                return this.indexingMediator.reindex(
                    this.getProjectPaths(project),
                    null,
                    this.getAbsoluteExcludedPaths(project),
                    this.getFileExtensionsToIndex(project)
                );
            };

            return this.indexingMediator.vacuum().then(successHandler);
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
            return this.indexingMediator.initialize();
        }

        /**
         * Vacuums the project.
         *
         * @return {Promise|null}
        */
        vacuumCurrentProject() {
            return this.indexingMediator.vacuum();
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
        getProjectSettings(project) {
            if (__guard__(project.getProps().php, x => x.serenata) != null) {
                return project.getProps().php.serenata;

            // Legacy name supported for backwards compatibility.
            } else if (__guard__(project.getProps().php, x1 => x1.php_integrator) != null) {
                return project.getProps().php.php_integrator;
            }

            return null;
        }

        /**
         * @return {Object|null}
        */
        getCurrentProjectSettings() {
            return this.getProjectSettings(this.getActiveProject());
        }

        /**
         * @return {Array}
        */
        getProjectPaths(project) {
            return project.getProps().paths;
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
            for (const projectDirectory of this.getProjectPaths(project)) {
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
