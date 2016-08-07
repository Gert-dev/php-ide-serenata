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
     * Whether project indexing is currently happening.
    ###
    isProjectIndexBusy: false

    ###*
     * A list of disposables to dispose when the package deactivates.
    ###
    disposables: null

    ###*
     * Tests the user's configuration.
     *
     * @return {boolean}
    ###
    testConfig: () ->
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

        return true

    ###*
     * Registers any commands that are available to the user.
    ###
    registerCommands: () ->
        atom.commands.add 'atom-workspace', "php-integrator-base:index-project": =>
            return @attemptProjectIndex()

        atom.commands.add 'atom-workspace', "php-integrator-base:force-index-project": =>
            return @attemptForceProjectIndex()

        atom.commands.add 'atom-workspace', "php-integrator-base:configuration": =>
            return unless @testConfig()

            atom.notifications.addSuccess 'Success', {
                'detail' : 'Your PHP integrator configuration is working correctly!'
            }

    ###*
     * Registers listeners for config changes.
    ###
    registerConfigListeners: () ->
        @configuration.onDidChange 'phpCommand', () =>
            @attemptProjectIndex()

    ###*
     * Indexes a list of directories.
     *
     * @param {Array}    directories
     * @param {Callback} progressStreamCallback
     *
     * @return {Promise}
    ###
    performProjectDirectoriesIndex: (directories, progressStreamCallback) ->


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

        pathArrays = project.props.paths

        @proxy.setProjectName(project.props._id)
        @proxy.setIndexDatabaseName(project.props._id)

        return @service.reindex(pathArrays, null, progressStreamCallback).then(successHandler, failureHandler)

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
        fs = require 'fs'

        try
            fs.unlinkSync(@proxy.getIndexDatabasePath())

        catch error
            # If the file doesn't exist, just bail out.

        return @attemptProjectIndex(project)

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
     * @param {String}      fileName The file to index.
     * @param {String|null} source   The source code of the file to index.
     *
     * @return {Promise}
    ###
    performFileIndex: (fileName, source = null) ->
        successHandler = () =>
            return

        failureHandler = () =>
            return

        return @service.reindex(fileName, source).then(successHandler, failureHandler)

    ###*
     * Performs a file index, but only if the file is not currently already being indexed (otherwise silently returns).
     *
     * @param {String}      fileName The file to index.
     * @param {String|null} source   The source code of the file to index.
     *
     * @return {Promise|null}
    ###
    attemptFileIndex: (fileName, source = null) ->
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

                @attemptFileIndex(fileName, nextIndexSource)

        successHandler = handler
        failureHandler = handler

        return @performFileIndex(fileName, source).then(successHandler, failureHandler)

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
     * @param {Object} project
    ###
    loadProject: (project) ->
        projectDirectories = project.props.paths

        return if projectDirectories.length == 0

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

        for projectDirectory in projectDirectories
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
        @testConfig()

        @proxy = new CachingProxy(@configuration)

        emitter = new Emitter()

        @service = new Service(@proxy, emitter)

        @registerCommands()
        @registerConfigListeners()

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
        return if not @projectManagerService

        @projectManagerService.projects.getCurrent (project) =>
            for projectDirectory in project.props.paths
                if projectDirectory.contains(path)
                    @attemptFileIndex(path, editor.getBuffer().getText())
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
