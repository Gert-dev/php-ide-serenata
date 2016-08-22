module.exports =
    ###*
     * Configuration settings.
    ###
    config:
        phpCommand:
            title       : 'PHP command'
            description : 'The path to your PHP binary (e.g. /usr/bin/php, php, ...).'
            type        : 'string'
            default     : 'php'
            order       : 1

    ###*
     * The name of the package.
    ###
    packageName: 'php-integrator-base'

    ###*
     * The configuration object.
    ###
    configuration: null

    ###*
     * The proxy object.
    ###
    proxy: null

    ###*
     * Keeps track of files that are being indexed.
    ###
    indexMap: {}

    ###*
     * The exposed service.
    ###
    service: null

    ###*
     * The status bar manager.
    ###
    statusBarManager: null

    ###*
     * The project manager service.
    ###
    projectManagerService: null

    ###*
     * The currently active project, if any.
    ###
    activeProject: null

    ###*
     * Whether project indexing is currently happening.
    ###
    isProjectIndexBusy: false

    ###*
     * A list of disposables to dispose when the package deactivates.
    ###
    disposables: null

    ###*
     * Default
    ###
    defaultProjectSettings:
        enabled: true
        php_integrator:
            enabled: true
            phpVersion: 5.6
            excludedPaths: []
            fileExtensions: ['php']

    ###*
     * Tests the user's configuration.
     *
     * @param {boolean} testServices
     *
     * @return {boolean}
    ###
    testConfig: (testServices = true) ->
        ConfigTester = require './ConfigTester'

        configTester = new ConfigTester(@configuration)

        result = configTester.test()

        if not result
            errorMessage =
                "PHP is not correctly set up and as a result PHP integrator will not work. Please visit the settings
                 screen to correct this error. If you are not specifying an absolute path for PHP or Composer, make
                 sure they are in your PATH."

            atom.notifications.addError('Incorrect setup!', {'detail': errorMessage})

            return false

        if testServices and not @projectManagerService
            errorMessage =
                "There is no project manager service available. Install the atom-project-manager package for project
                support to work in its full extent."

            atom.notifications.addError('Incorrect setup!', {'detail': errorMessage})

            return false

        return true

    ###*
     * Registers any commands that are available to the user.
    ###
    registerCommands: () ->
        atom.commands.add 'atom-workspace', "php-integrator-base:set-up-current-project": =>
            return if not @projectManagerService

            @projectManagerService.projects.getCurrent (project) =>
                projectPhpSettings = if project.props.php? then project.props.php else {}

                if projectPhpSettings.php_integrator?
                    atom.notifications.addError 'Already initialized', {
                        'detail' : 'The currently active project was already initialized. To prevent existing ' +
                            'settings from getting lost, the request has been aborted.'
                    }

                    return

                if not projectPhpSettings.enabled
                    projectPhpSettings.enabled = true

                if not projectPhpSettings.php_integrator?
                    projectPhpSettings.php_integrator = @defaultProjectSettings.php_integrator

                project.set('php', projectPhpSettings)

                atom.notifications.addSuccess 'Success', {
                    'detail' : 'Your current project has been set up as PHP project. Indexing will now commence.'
                }

                @loadProject(project)

        atom.commands.add 'atom-workspace', "php-integrator-base:index-project": =>
            return if not @activeProject

            project = @activeProject

            return @attemptProjectIndex(project)

        atom.commands.add 'atom-workspace', "php-integrator-base:force-index-project": =>
            return if not @activeProject

            project = @activeProject

            return @attemptForceProjectIndex(project)

        atom.commands.add 'atom-workspace', "php-integrator-base:configuration": =>
            return unless @testConfig()

            atom.notifications.addSuccess 'Success', {
                'detail' : 'Your PHP integrator configuration is working correctly!'
            }

    ###*
     * Indexes the project aynschronously.
     *
     * @param {Object} project
     *
     * @return {Promise}
    ###
    performProjectIndex: (project) ->
        timerName = @packageName + " - Project indexing"

        console.time(timerName);

        if @statusBarManager
            @statusBarManager.setLabel("Indexing...")
            @statusBarManager.setProgress(null)
            @statusBarManager.show()

        successHandler = () =>
            if @statusBarManager
                @statusBarManager.setLabel("Indexing completed!")
                @statusBarManager.hide()

            console.timeEnd(timerName);

        failureHandler = () =>
            if @statusBarManager
                @statusBarManager.showMessage("Indexing failed!", "highlight-error")
                @statusBarManager.hide()

        progressStreamCallback = (progress) =>
            progress = parseFloat(progress)

            if not isNaN(progress)
                if @statusBarManager
                    @statusBarManager.setProgress(progress)
                    @statusBarManager.setLabel("Indexing... (" + progress.toFixed(2) + " %)")

        return @service.reindex(
            project.props.paths,
            null,
            progressStreamCallback,
            @getAbsoluteExcludedPaths(project),
            @getFileExtensionsToIndex(project)
        ).then(successHandler, failureHandler)

    ###*
     * Performs a project index, but only if one is not currently already happening.
     *
     * @param {Object} project
     *
     * @return {Promise|null}
    ###
    attemptProjectIndex: (project) ->
        return null if @isProjectIndexBusy

        @isProjectIndexBusy = true

        handler = () =>
            @isProjectIndexBusy = false

        successHandler = handler
        failureHandler = handler

        return @performProjectIndex(project).then(successHandler, failureHandler)

    ###*
     * Forcibly indexes the project in its entirety by removing the existing indexing database first.
     *
     * @param {Object} project
     *
     * @return {Promise|null}
    ###
    forceProjectIndex: (project) ->
        handler = () =>
            @attemptProjectIndex(project)

        successHandler = handler
        failureHandler = handler

        @service.truncate().then(successHandler, failureHandler)

    ###*
     * Forcibly indexes the project in its entirety by removing the existing indexing database first, but only if a
     * project indexing operation is not already busy.
     *
     * @param {Object} project
     *
     * @return {Promise|null}
    ###
    attemptForceProjectIndex: (project) ->
        return null if @isProjectIndexBusy

        return @forceProjectIndex(project)

    ###*
     * Indexes a file aynschronously.
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
        return null if @isProjectIndexBusy

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
     * Attaches items to the status bar.
     *
     * @param {mixed} statusBarService
    ###
    attachStatusBarItems: (statusBarService) ->
        if not @statusBarManager
            StatusBarManager = require "./Widgets/StatusBarManager"

            @statusBarManager = new StatusBarManager()
            @statusBarManager.initialize(statusBarService)
            @statusBarManager.setLabel("Indexing...")

    ###*
     * Detaches existing items from the status bar.
    ###
    detachStatusBarItems: () ->
        if @statusBarManager
            @statusBarManager.destroy()
            @statusBarManager = null

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
     * @param {Object} project
    ###
    loadProject: (project) ->
        @activeProject = null

        return if project.props.php?.enabled != true
        return if project.props.php?.php_integrator?.enabled != true
        return if project.props.paths.length == 0

        @activeProject = project

        @proxy.setProjectName(project.props._id)
        @proxy.setIndexDatabaseName(project.props._id)

        @attemptProjectIndex(project)

        successHandler = (repository) =>
            return if not repository?
            return if not repository.async?

            # Will trigger on things such as git checkout.
            repository.async.onDidChangeStatuses () =>
                @attemptProjectIndex(project)

        failureHandler = () =>
            return

        {Directory} = require 'atom'

        for projectDirectory in project.props.paths
            projectDirectoryObject = new Directory(projectDirectory)

            atom.project.repositoryForDirectory(projectDirectoryObject).then(successHandler, failureHandler)

    ###*
     * Activates the package.
    ###
    activate: ->
        Service               = require './Service'
        AtomConfig            = require './AtomConfig'
        CachingProxy          = require './CachingProxy'

        {Emitter}             = require 'event-kit';
        {CompositeDisposable} = require 'atom';

        @disposables = new CompositeDisposable()

        @configuration = new AtomConfig(@packageName)

        # See also atom-autocomplete-php pull request #197 - Disabled for now because it does not allow the user to
        # reactivate or try again.
        # return unless @testConfig()
        @testConfig(false)

        @proxy = new CachingProxy(@configuration)

        emitter = new Emitter()

        @service = new Service(@proxy, emitter)

        @registerCommands()

        @disposables.add atom.workspace.observeTextEditors (editor) =>
            # Wait a while for the editor to stabilize so we don't reindex multiple times after an editor opens just
            # because the contents are still loading.
            setTimeout ( =>
                return if not @disposables

                @disposables.add editor.onDidStopChanging () =>
                    @onEditorDidStopChanging(editor)
            ), 1500

    ###*
     * Invoked when an editor stops changing.
     *
     * @param {TextEditor} editor
    ###
    onEditorDidStopChanging: (editor) ->
        return unless /text.html.php$/.test(editor.getGrammar().scopeName)

        path = editor.getPath()

        return if not path
        return if not @activeProject

        {Directory} = require 'atom'

        for projectDirectory in @activeProject.props.paths
            projectDirectoryObject = new Directory(projectDirectory)

            if projectDirectoryObject.contains(path)
                @attemptFileIndex(@activeProject, path, editor.getBuffer().getText())
                return

    ###*
     * Deactivates the package.
    ###
    deactivate: ->
        if @disposables
            @disposables.dispose()
            @disposables = null

    ###*
     * Sets the status bar service, which is consumed by this package.
     *
     * @param {Object} service
    ###
    setStatusBarService: (service) ->
        @attachStatusBarItems(service)

        # This method is usually invoked after the indexing has already started, hence we can't unconditionally hide it
        # here or it will never be made visible again.
        if not @isProjectIndexBusy
            @statusBarManager.hide()

        {Disposable} = require 'atom'

        return new Disposable => @detachStatusBarItems()

    ###*
     * Sets the project manager service.
     *
     * @param {Object} service
    ###
    setProjectManagerService: (service) ->
        @projectManagerService = service

        service.projects.getCurrent (project) =>
            @loadProject(project)

    ###*
     * Retrieves the service exposed by this package.
    ###
    getService: ->
        return @service
