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

        insertNewlinesForUseStatements:
            title       : 'Insert newlines for use statements'
            description : 'When enabled, additional newlines are inserted before or after an automatically added
                           use statement when they can\'t be nicely added to an existing \'group\'. This results in
                           more cleanly separated use statements but will create additional vertical whitespace.'
            type        : 'boolean'
            default     : false
            order       : 2

    ###*
     * The name of the package.
     *
     * @var {String}
    ###
    packageName: 'php-integrator-base'

    ###*
     * The configuration object.
     *
     * @var {Object}
    ###
    configuration: null

    ###*
     * The proxy object.
     *
     * @var {Object}
    ###
    proxy: null

    ###*
     * The exposed service.
     *
     * @var {Object}
    ###
    service: null

    ###*
     * The status bar manager.
     *
     * @var {Object}
    ###
    statusBarManager: null

    ###*
     * @var {IndexingMediator}
    ###
    indexingMediator: null

    ###*
     * A list of disposables to dispose when the package deactivates.
     *
     * @var {Object|null}
    ###
    disposables: null

    ###*
     * The currently active project, if any.
     *
     * @var {Object|null}
    ###
    activeProject: null

    ###*
     * @var {String|null}
    ###
    timerName: null

    ###*
     * @var {String|null}
    ###
    progressBarTimeout: null

    ###*
     * The service instance from the project-manager package.
     *
     * @var {Object|null}
    ###
    projectManagerService: null

    ###*
     * Tests the user's configuration.
     *
     * @param {bool} testServices
     *
     * @return {bool}
    ###
    testConfig: (testServices = true) ->
        ConfigTester = require './ConfigTester'

        configTester = new ConfigTester(@getConfiguration())

        result = configTester.test()

        if not result
            errorMessage =
                "PHP is not correctly set up and as a result PHP integrator will not work. Please visit the settings
                 screen to correct this error. If you are not specifying an absolute path for PHP or Composer, make
                 sure they are in your PATH."

            atom.notifications.addError('Incorrect setup!', {'detail': errorMessage})

            return false

        if testServices and not @projectManagerService?
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
            if not @projectManagerService?
                errorMessage = '''
                    The project manager service was not found. Did you perhaps forget to install the project-manager
                    package or another package able to provide it?
                '''

                atom.notifications.addError('Incorrect setup!', {'detail': errorMessage})
                return

            if not @activeProject?
                errorMessage = '''
                    No project is currently active. Please set up and activate one before attempting to set it up.
                '''

                atom.notifications.addError('Incorrect setup!', {'detail': errorMessage})
                return

            project = @activeProject

            newProperties = null

            try
                newProperties = @projectManager.setUpProject(project)

                if not newProperties?
                    throw new Error('No properties returned, this should never happen!')

            catch error
                atom.notifications.addError('Error!', {
                    'detail' : error.message
                })

                return

            @projectManagerService.saveProject(newProperties)

            atom.notifications.addSuccess 'Success', {
                'detail' : 'Your current project has been set up as PHP project. Indexing will now commence.'
            }

            @projectManager.load(project)

            @performInitialFullIndexForCurrentProject()

        atom.commands.add 'atom-workspace', "php-integrator-base:index-project": =>
            return if not @projectManager.hasActiveProject()

            @projectManager.attemptCurrentProjectIndex()

        atom.commands.add 'atom-workspace', "php-integrator-base:force-index-project": =>
            return if not @projectManager.hasActiveProject()

            successHandler = () =>
                @performInitialFullIndexForCurrentProject()

            failureHandler = () =>
                # Do nothing.

            @projectManager.truncateCurrentProject().then(successHandler, failureHandler)

        atom.commands.add 'atom-workspace', "php-integrator-base:configuration": =>
            return unless @testConfig()

            atom.notifications.addSuccess 'Success', {
                'detail' : 'Your PHP integrator configuration is working correctly!'
            }

        atom.commands.add 'atom-workspace', "php-integrator-base:sort-use-statements": =>
            activeTextEditor = atom.workspace.getActiveTextEditor()

            return if not activeTextEditor?

            @getUseStatementHelper().sortUseStatements(activeTextEditor)

    ###*
     * Performs the "initial" index for a new project by initializing it and then performing a project index.
     *
     * @return {Promise}
    ###
    performInitialFullIndexForCurrentProject: () ->
        successHandler = () =>
            return @projectManager.attemptCurrentProjectIndex()

        failureHandler = (reason) =>
            console.error(reason)

            atom.notifications.addError('Error!', {
                'detail' : 'The project could not be properly initialized!'
            })

        return @projectManager.initializeCurrentProject().then(successHandler, failureHandler)

    ###*
     * Registers listeners for configuration changes.
    ###
    registerConfigListeners: () ->
        config = @getConfiguration()

        config.onDidChange 'insertNewlinesForUseStatements', (value) =>
            @getUseStatementHelper().setAllowAdditionalNewlines(value)

    ###*
     * Registers status bar listeners.
    ###
    registerStatusBarListeners: () ->
        service = @getService()

        service.onDidStartIndexing () =>
            if @progressBarTimeout
                clearTimeout(@progressBarTimeout)

            # Indexing could be anything: the entire project or just a file. If indexing anything takes too long, show
            # the progress bar to indicate we're doing something.
            @progressBarTimeout = setTimeout ( =>
                @progressBarTimeout = null

                @timerName = @packageName + " - Indexing"

                console.time(@timerName);

                if @statusBarManager?
                    @statusBarManager.setLabel("Indexing...")
                    @statusBarManager.setProgress(null)
                    @statusBarManager.show()
            ), 1000

        service.onDidFinishIndexing () =>
            if @progressBarTimeout
                clearTimeout(@progressBarTimeout)
                @progressBarTimeout = null

            else
                console.timeEnd(@timerName)

            if @statusBarManager?
                @statusBarManager.setLabel("Indexing completed!")
                @statusBarManager.hide()

        service.onDidFailIndexing () =>
            if @progressBarTimeout
                clearTimeout(@progressBarTimeout)
                @progressBarTimeout = null

            else
                console.timeEnd(@timerName)

            if @statusBarManager?
                @statusBarManager.showMessage("Indexing failed!", "highlight-error")
                @statusBarManager.hide()

        service.onDidIndexingProgress (data) =>
            if @statusBarManager?
                @statusBarManager.setProgress(data.percentage)
                @statusBarManager.setLabel("Indexing... (" + data.percentage.toFixed(2) + " %)")

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
     * Uninstalls the core package (which contains the PHP payload) if it is outdated. It can then be installed in an
     * up-to-date version again.
     *
     * @return {Promise}
    ###
    uninstallCorePackageIfOutdated: () ->
        fs = require 'fs'
        path = require 'path'

        corePackagePath = atom.packages.resolvePackagePath("php-integrator-core")

        if corePackagePath?
            coreVersion = JSON.parse(fs.readFileSync(path.join(corePackagePath, 'package.json')))?.version
            baseVersion = JSON.parse(fs.readFileSync(path.join(atom.packages.resolvePackagePath(@packageName), 'package.json')))?.version

            if coreVersion? and coreVersion < baseVersion
                return new Promise (resolve, reject) =>
                    rmdirRecursive = require('rimraf');

                    rmdirRecursive corePackagePath, (error) =>
                        resolve()

        return new Promise (resolve, reject) ->
            resolve()

    ###*
     * Activates the package.
    ###
    activate: ->
        @uninstallCorePackageIfOutdated().then () =>
            require('atom-package-deps').install(@packageName).then () =>
                @testConfig(false)

                @registerCommands()
                @registerConfigListeners()
                @registerStatusBarListeners()
                @registerAtomListeners()

    ###*
     * Registers listeners for events from Atom's API.
    ###
    registerAtomListeners: () ->
        @getDisposables().add atom.workspace.observeTextEditors (editor) =>
            @registerTextEditorListeners(editor)

    ###*
     * @param {TextEditor} editor
    ###
    registerTextEditorListeners: (editor) ->
        @getDisposables().add editor.onDidStopChanging () =>
            @onEditorDidStopChanging(editor)

    ###*
     * Invoked when an editor stops changing.
     *
     * @param {TextEditor} editor
    ###
    onEditorDidStopChanging: (editor) ->
        return unless /text.html.php$/.test(editor.getGrammar().scopeName)

        fileName = editor.getPath()

        return if not fileName

        projectManager = @getProjectManager()

        if projectManager.hasActiveProject() and projectManager.isFilePartOfCurrentProject(fileName)
            projectManager.attemptCurrentProjectFileIndex(fileName, editor.getBuffer().getText())

    ###*
     * Deactivates the package.
    ###
    deactivate: ->
        if @disposables
            @disposables.dispose()
            @disposables = null

        @getCachingProxy().stopPhpServer()

    ###*
     * Sets the status bar service, which is consumed by this package.
     *
     * @param {Object} service
    ###
    setStatusBarService: (service) ->
        @attachStatusBarItems(service)

        # This method is usually invoked after the indexing has already started, hence we can't unconditionally hide it
        # here or it will never be made visible again.
        if not @getProjectManager().isProjectIndexing()
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

        # NOTE: This method is actually called whenever the project changes as well.
        service.getProject (project) =>
            @activeProject = project

            return if not project?

            projectManager = @getProjectManager()
            projectManager.load(project)

            return if not projectManager.hasActiveProject()

            successHandler = (isProjectInGoodShape) =>
                # NOTE: If the index is manually deleted, testing will return false so the project is reinitialized.
                # This is needed to index built-in items as they are not automatically indexed by indexing the project.
                if not isProjectInGoodShape
                    return @performInitialFullIndexForCurrentProject()

                else
                    return @projectManager.attemptCurrentProjectIndex()

            failureHandler = () ->
                # Ignore

            @proxy.test().then(successHandler, failureHandler)

    ###*
     * Retrieves the base package service that can be used by other packages.
     *
     * @return {Service}
    ###
    getServiceInstance: () ->
        return @getService()

    ###*
     * @return {Service}
    ###
    getService: () ->
        if not @disposables?
            Service = require './Service'

            @service = new Service(
                @getConfiguration(),
                @getCachingProxy(),
                @getProjectManager(),
                @getIndexingMediator(),
                @getUseStatementHelper()
            )

        return @service

    ###*
     * @return {Disposables}
    ###
    getDisposables: () ->
        if not @disposables?
            {CompositeDisposable} = require 'atom';

            @disposables = new CompositeDisposable()

        return @disposables

    ###*
     * @return {Configuration}
    ###
    getConfiguration: () ->
        if not @configuration?
            AtomConfig = require './AtomConfig'

            @configuration = new AtomConfig(@packageName)

        return @configuration

    ###*
     * @return {CachingProxy}
    ###
    getCachingProxy: () ->
        if not @proxy?
            CachingProxy = require './CachingProxy'

            @proxy = new CachingProxy(@getConfiguration())

        return @proxy

    ###*
     * @return {Emitter}
    ###
    getEmitter: () ->
        if not @emitter?
            {Emitter} = require 'event-kit';

            @emitter = new Emitter()

        return @emitter

    ###*
     * @return {UseStatementHelper}
    ###
    getUseStatementHelper: () ->
        if not @useStatementHelper?
            UseStatementHelper = require './UseStatementHelper';

            @useStatementHelper = new UseStatementHelper(@getConfiguration().get('insertNewlinesForUseStatements'))

        return @useStatementHelper

    ###*
     * @return {IndexingMediator}
    ###
    getIndexingMediator: () ->
        if not @indexingMediator?
            IndexingMediator = require './IndexingMediator'

            @indexingMediator = new IndexingMediator(@getCachingProxy(), @getEmitter())

        return @indexingMediator

    ###*
     * @return {ProjectManager}
    ###
    getProjectManager: () ->
        if not @projectManager?
            ProjectManager = require './ProjectManager'

            @projectManager = new ProjectManager(@getCachingProxy(), @getIndexingMediator())

        return @projectManager
