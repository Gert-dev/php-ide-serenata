module.exports =

##*
# Handles project management
##
class ProjectManager
    ###*
     * The service instance from the project-manager package.
     *
     * @var {Object|null}
    ###
    projectManagerService: null

    ###*
     * @var {Object}
    ###
    proxy: null

    ###*
     * @var {Object}
    ###
    service: null

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
        php_integrator:
            enabled: true
            phpVersion: 5.6
            excludedPaths: []
            fileExtensions: ['php']

    ###*
     * @param {Object} proxy
     * @param {Object} service
    ###
    constructor: (@proxy, @service) ->
        @indexMap = {}

    ###*
     * @return {Object}
    ###
    getProjectManagerService: () ->
        return @projectManagerService

    ###*
     * @param {Object} projectManagerService
    ###
    setProjectManagerService: (@projectManagerService) ->

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
     * @param {Object} project
    ###
    setUpProject: (project) ->
        projectPhpSettings = if project.props.php? then project.props.php else {}

        if projectPhpSettings.php_integrator?
            throw new Error('''
                The currently active project was already initialized. To prevent existing settings from getting lost,
                the request has been aborted.
            ''')

        if not projectPhpSettings.enabled
            projectPhpSettings.enabled = true

        if not projectPhpSettings.php_integrator?
            projectPhpSettings.php_integrator = @defaultProjectSettings.php_integrator

        project.set('php', projectPhpSettings)

    ###*
     * @param {Object} project
     *
     * @return {Promise|null}
    ###
    load: (project) ->
        @activeProject = null

        return if project.props.php?.enabled != true
        return if project.props.php?.php_integrator?.enabled != true
        return if project.props.paths.length == 0

        @activeProject = project

        @proxy.setProjectName(project.props._id)
        @proxy.setIndexDatabaseName(project.props._id)

        successHandler = (repository) =>
            return if not repository?
            return if not repository.async?

            # Will trigger on things such as git checkout.
            repository.async.onDidChangeStatuses () =>
                @attemptIndex(project)

        failureHandler = () =>
            return

        {Directory} = require 'atom'

        for projectDirectory in project.props.paths
            projectDirectoryObject = new Directory(projectDirectory)

            atom.project.repositoryForDirectory(projectDirectoryObject).then(successHandler, failureHandler)

    ###*
     * Retrieves a list of file extensions to include in indexing.
     *
     * @param {Object} project
     *
     * @return {Array}
    ###
    getFileExtensionsToIndex: (project) ->
        projectPaths = project.props.paths
        fileExtensions = project.props.php?.php_integrator?.fileExtensions

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
        projectPaths = project.props.paths
        excludedPaths = project.props.php?.php_integrator?.excludedPaths

        if not excludedPaths?
            excludedPaths = []

        path = require 'path'

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
     * @param {Object}        project
     * @param {Callback|null} progressStreamCallback
     *
     * @return {Promise}
    ###
    performIndex: (project, progressStreamCallback = null) ->
        return @service.reindex(
            project.props.paths,
            null,
            progressStreamCallback,
            @getAbsoluteExcludedPaths(project),
            @getFileExtensionsToIndex(project)
        )

    ###*
     * Performs a project index, but only if one is not currently already happening.
     *
     * @param {Object} project
     * @param {Callback|null} progressStreamCallback
     *
     * @return {Promise|null}
    ###
    attemptIndex: (project, progressStreamCallback = null) ->
        return null if @isProjectIndexing()

        @isProjectIndexingFlag = true

        handler = () =>
            @isProjectIndexingFlag = false

        successHandler = handler
        failureHandler = handler

        return @performIndex(project, progressStreamCallback).then(successHandler, failureHandler)

    ###*
     * Indexes the current project, but only if one is not currently already happening.
     *
     * @param {Callback|null} progressStreamCallback
     *
     * @return {Promise}
    ###
    attemptCurrentProjectIndex: (progressStreamCallback = null) ->
        return @attemptIndex(@getActiveProject(), progressStreamCallback)

    ###*
     * Truncates the project, removing the existing indexing database.
     *
     * @return {Promise|null}
    ###
    truncateCurrentProject: () ->
        return @service.truncate()

    ###*
     * Indexes a file asynchronously.
     *
     * @param {Object}      project
     * @param {String}      fileName The file to index.
     * @param {String|null} source   The source code of the file to index.
     *
     * @return {Promise}
    ###
    performFileIndex: (project, fileName, source = null) ->
        successHandler = () =>
            return

        failureHandler = () =>
            return

        return @service.reindex(
            fileName,
            source,
            null,
            @getAbsoluteExcludedPaths(project),
            @getFileExtensionsToIndex(project)
        ).then(successHandler, failureHandler)

    ###*
     * Performs a file index, but only if the file is not currently already being indexed (otherwise silently returns).
     *
     * @param {Object}      project
     * @param {String}      fileName The file to index.
     * @param {String|null} source   The source code of the file to index.
     *
     * @return {Promise|null}
    ###
    attemptFileIndex: (project, fileName, source = null) ->
        return null if @isProjectIndexing()

        if fileName not of @indexMap
            @indexMap[fileName] = {
                isBeingIndexed  : true
                nextIndexSource : null
            }

        else if @indexMap[fileName].isBeingIndexed
            # This file is already being indexed, so keep track of the most recent changes so we can index any changes
            # after the current indexing process finishes.
            @indexMap[fileName].nextIndexSource = source
            return null

        @indexMap[fileName].isBeingIndexed = true

        handler = () =>
            @indexMap[fileName].isBeingIndexed = false

            if @indexMap[fileName].nextIndexSource?
                nextIndexSource = @indexMap[fileName].nextIndexSource

                @indexMap[fileName].nextIndexSource = null

                @attemptFileIndex(project, fileName, nextIndexSource)

        successHandler = handler
        failureHandler = handler

        return @performFileIndex(project, fileName, source).then(successHandler, failureHandler)

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
     * Indicates if the specified file is part of the project.
     *
     * @param {Object} project
     * @param {String} fileName
     *
     * @return {bool}
    ###
    isFilePartOfProject: (project, fileName) ->
        {Directory} = require 'atom'

        for projectDirectory in project.props.paths
            projectDirectoryObject = new Directory(projectDirectory)

            if projectDirectoryObject.contains(path)
                return true

        return false

    ###*
     * Indicates if the specified file is part of the current project.
     *
     * @param {String} fileName
     *
     * @return {bool}
    ###
    isFilePartOfCurrentProject: (project, fileName) ->
        return @isFilePartOfProject(@getActiveProject(), fileName)
