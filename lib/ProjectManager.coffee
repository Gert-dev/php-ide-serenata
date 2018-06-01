{Directory} = require 'atom'

path = require 'path'

process = require 'process'

module.exports =

##*
# Handles project management
##
class ProjectManager
    ###*
     * @var {Object}
    ###
    proxy: null

    ###*
     * @var {Object}
    ###
    indexingMediator: null

    ###*
     * The service instance from the project-manager package.
     *
     * @var {Object|null}
    ###
    activeProject: null

    ###*
     * Whether project indexing is currently happening.
     *
     * @var {bool}
    ###
    isProjectIndexingFlag: false

    ###*
     * Keeps track of files that are being indexed.
     *
     * @var {Object}
    ###
    indexMap: null

    ###*
     * Default settings for projects.
     *
     * Note that this object will be shared across instances!
     *
     * @var {Object}
    ###
    defaultProjectSettings:
        enabled: true
        serenata:
            enabled: true
            phpVersion: 7.2
            excludedPaths: []
            fileExtensions: ['php']

    ###*
     * @param {Object} proxy
     * @param {Object} indexingMediator
    ###
    constructor: (@proxy, @indexingMediator) ->
        @indexMap = {}

    ###*
     * @return {Object|null}
    ###
    getActiveProject: () ->
        return @activeProject

    ###*
     * @return {bool}
    ###
    hasActiveProject: () ->
        if @getActiveProject()?
            return true

        return false

    ###*
     * @return {bool}
    ###
    isProjectIndexing: () ->
        return @isProjectIndexingFlag

    ###*
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
    ###
    setUpProject: (project) ->
        projectPhpSettings = if project.getProps().php? then project.getProps().php else {}

        if projectPhpSettings.serenata?
            throw new Error('''
                The currently active project was already initialized. To prevent existing settings from getting lost,
                the request has been aborted.
            ''')

        if not projectPhpSettings.enabled
            projectPhpSettings.enabled = true

        if not projectPhpSettings.serenata?
            projectPhpSettings.serenata = @defaultProjectSettings.serenata

        existingProps = project.getProps()
        existingProps.php = projectPhpSettings

        return existingProps

    ###*
     * @param {Object} project
    ###
    load: (project) ->
        @activeProject = null

        return if project.getProps().php?.enabled != true

        projectSettings = @getProjectSettings(project)

        return if projectSettings?.enabled != true

        @validateProject(project)

        @activeProject = project

        @proxy.setIndexDatabaseName(@getIndexDatabaseName(project))

        successHandler = (repository) =>
            return if not repository?
            return if not repository.async?

            # Will trigger on things such as git checkout.
            repository.async.onDidChangeStatuses () =>
                @attemptIndex(project)

        failureHandler = () =>
            return

        for projectDirectory in @getProjectPaths(project)
            projectDirectoryObject = new Directory(projectDirectory)

            atom.project.repositoryForDirectory(projectDirectoryObject).then(successHandler, failureHandler)

    ###*
     * @param {Object}
     *
     * @return {String}
    ###
    getIndexDatabaseName: (project) ->
        return project.getProps().title

    ###*
     * Validates a project by validating its settings.
     *
     * Throws an Error if something is not right with the project.
     *
     * @param {Object} project
    ###
    validateProject: (project) ->
        projectSettings = @getProjectSettings(project)

        if not projectSettings?
            throw new Error(
                'No project settings were found under a node called "php.serenata" in your project settings'
            )

        phpVersion = projectSettings.phpVersion

        if isNaN(parseFloat(phpVersion)) or not isFinite(phpVersion)
            throw new Error('''
                PHP version in your project settings must be a number such as 7.2
            ''')

    ###*
     * Retrieves a list of file extensions to include in indexing.
     *
     * @param {Object} project
     *
     * @return {Array}
    ###
    getFileExtensionsToIndex: (project) ->
        projectPaths = @getProjectPaths(project)
        projectSettings = @getProjectSettings(project)

        fileExtensions = projectSettings?.fileExtensions

        if not fileExtensions?
            fileExtensions = []

        return fileExtensions

    ###*
     * Retrieves a list of absolute paths to exclude from indexing.
     *
     * @param {Object} project
     *
     * @return {Array}
    ###
    getAbsoluteExcludedPaths: (project) ->
        projectPaths = @getProjectPaths(project)
        projectSettings = @getProjectSettings(project)

        excludedPaths = projectSettings?.excludedPaths

        if not excludedPaths?
            excludedPaths = []

        absoluteExcludedPaths = []

        for excludedPath in excludedPaths
            if path.isAbsolute(excludedPath)
                absoluteExcludedPaths.push(excludedPath)

            else
                matches = excludedPath.match(/^\{(\d+)\}(\/.*)$/)

                if matches?
                    index = matches[1]

                    # Relative paths starting with {n} are relative to the project path at index {n}, e.g. "{0}/test".
                    if index > projectPaths.length
                        throw new Error("Requested project path index " + index + ", but the project does not have that many paths!")

                    absoluteExcludedPaths.push(projectPaths[index] + matches[2])

                else
                    absoluteExcludedPaths.push(path.normalize(excludedPath))

        return absoluteExcludedPaths

    ###*
     * Indexes the project asynchronously.
     *
     * @param {Object} project
     *
     * @return {Promise}
    ###
    performIndex: (project) ->
        successHandler = () =>
            return @indexingMediator.reindex(
                @getProjectPaths(project),
                null,
                @getAbsoluteExcludedPaths(project),
                @getFileExtensionsToIndex(project)
            )

        return @indexingMediator.vacuum().then(successHandler)

    ###*
     * Performs a project index, but only if one is not currently already happening.
     *
     * @param {Object} project
     *
     * @return {Promise|null}
    ###
    attemptIndex: (project) ->
        return null if @isProjectIndexing()

        @isProjectIndexingFlag = true

        handler = () =>
            @isProjectIndexingFlag = false

        successHandler = handler
        failureHandler = handler

        return @performIndex(project).then(successHandler, failureHandler)

    ###*
     * Indexes the current project, but only if one is not currently already happening.
     *
     * @return {Promise}
    ###
    attemptCurrentProjectIndex: () ->
        return @attemptIndex(@getActiveProject())

    ###*
     * Initializes the project.
     *
     * @return {Promise|null}
    ###
    initializeCurrentProject: () ->
        return @indexingMediator.initialize()

    ###*
     * Vacuums the project.
     *
     * @return {Promise|null}
    ###
    vacuumCurrentProject: () ->
        return @indexingMediator.vacuum()

    ###*
     * Indexes a file asynchronously.
     *
     * @param {Object}      project
     * @param {String}      fileName The file to index.
     * @param {String|null} source   The source code of the file to index.
     *
     * @return {CancellablePromise}
    ###
    performFileIndex: (project, fileName, source = null) ->
        return @indexingMediator.reindex(
            fileName,
            source,
            @getAbsoluteExcludedPaths(project),
            @getFileExtensionsToIndex(project)
        )

    ###*
     * Performs a file index.
     *
     * @param {Object}      project
     * @param {String}      fileName The file to index.
     * @param {String|null} source   The source code of the file to index.
     *
     * @return {Promise|null}
    ###
    attemptFileIndex: (project, fileName, source = null) ->
        if fileName of @indexMap
            @indexMap[fileName].cancel()

        @indexMap[fileName] = @performFileIndex(project, fileName, source)

        onIndexFinish = () =>
            delete @indexMap[fileName]

        return @indexMap[fileName].then(onIndexFinish, onIndexFinish)

    ###*
     * Indexes the current project asynchronously.
     *
     * @param {String}      fileName The file to index.
     * @param {String|null} source   The source code of the file to index.
     *
     * @return {Promise}
    ###
    attemptCurrentProjectFileIndex: (fileName, source = null) ->
        return @attemptFileIndex(@getActiveProject(),  fileName, source)

    ###*
     * @return {Object|null}
    ###
    getProjectSettings: (project) ->
        if project.getProps().php?.serenata?
            return project.getProps().php.serenata

        # Legacy name supported for backwards compatibility.
        else if project.getProps().php?.php_integrator?
            return project.getProps().php.php_integrator

        return null

    ###*
     * @return {Object|null}
    ###
    getCurrentProjectSettings: () ->
        return @getProjectSettings(@getActiveProject())

    ###*
     * @return {Array}
    ###
    getProjectPaths: (project) ->
        return project.getProps().paths

    ###*
     * Indicates if the specified file is part of the project.
     *
     * @param {Object} project
     * @param {String} fileName
     *
     * @return {bool}
    ###
    isFilePartOfProject: (project, fileName) ->
        for projectDirectory in @getProjectPaths(project)
            projectDirectoryObject = new Directory(projectDirectory)

            # #295 - Resolve home folders. The core supports this out of the box, but we still need to limit files to
            # the workspace here. Atom is picky about this: if the project contains a tilde, the contains method below
            # will only return true if the file path also contains the tilde, which is not the case by default for
            # editor file paths.
            if projectDirectory.startsWith('~')
                fileName = fileName.replace(@getHomeFolderPath(), '~')

            if projectDirectoryObject.contains(fileName)
                return true

        return false

    ###*
     * @return {String}
    ###
    getHomeFolderPath: () ->
        homeFolderVarName = if process.platform == 'win32' then 'USERPROFILE' else 'HOME'

        return process.env[homeFolderVarName];

    ###*
     * Indicates if the specified file is part of the current project.
     *
     * @param {String} fileName
     *
     * @return {bool}
    ###
    isFilePartOfCurrentProject: (fileName) ->
        return @isFilePartOfProject(@getActiveProject(), fileName)
