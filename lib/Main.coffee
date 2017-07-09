module.exports =
    ###*
     * Configuration settings.
    ###
    config:
        core:
            type: 'object'
            order: 1
            properties:
                phpCommand:
                    title       : 'PHP command'
                    description : 'The path to your PHP binary (e.g. /usr/bin/php, php, ...).
                                   Requires a restart after changing.'
                    type        : 'string'
                    default     : 'php'
                    order       : 1

                memoryLimit:
                    title       : 'Memory limit (in MB)'
                    description : 'The memory limit to set for the PHP process. The PHP process uses the available
                                   memory for in-memory caching as well, so it should not be too low. On the other hand,
                                   it shouldn\'t be growing very large, so setting it to -1 is probably a bad idea as
                                   an infinite loop bug might take down your system. The default should suit most
                                   projects, from small to large.'
                    type        : 'integer'
                    default     : 1024
                    order       : 2

        general:
            type: 'object'
            order: 2
            properties:
                indexContinuously:
                    title       : 'Index continuously'
                    description : 'If enabled, indexing will happen continuously and automatically whenever the editor
                                   is modified. If disabled, indexing will only happen on save. This also influences
                                   linting, which happens automatically after indexing completes. In other words, if
                                   you would like linting to happen on save, you can disable this option.'
                    type        : 'boolean'
                    default     : true
                    order       : 1

                additionalIndexingDelay:
                    title       : 'Additional delay before reindexing (in ms)'
                    description : 'Only applies when indexing continously, which happens after a fixed time (about 300
                                   ms at the time of writing and managed by Atom). If this is too fast for you, you can
                                   introduce an additional delay here. Fewer indexes means less load as tasks such as
                                   linting are invoked less often. However, it also means that it will take longer for
                                   changes to code to be reflected in, for example, autocompletion.'
                    type        : 'integer'
                    default     : 0
                    order       : 2

                insertNewlinesForUseStatements:
                    title       : 'Allow newlines when inserting use statements'
                    description : 'If enabled, additional newlines may be inserted before or after automatically added
                                   use statements when they can\'t be nicely added to an existing \'group\'. This
                                   results in more cleanly separated use statements but will create additional vertical
                                   whitespace.'
                    type        : 'boolean'
                    default     : false
                    order       : 3

        tooltips:
            type: 'object'
            order: 3
            properties:
                enable:
                    title       : 'Enable'
                    description : 'When enabled, documentation for various structural elements can be displayed in a
                                   dock by activating intentions. (This is called tooltips as the data provided is
                                   meant to be displayed in tooltips, but a dock offers advantages such as proper
                                   scrolling and can be permanent.)'
                    type        : 'boolean'
                    default     : true
                    order       : 1

        signatureHelp:
            type: 'object'
            order: 4
            properties:
                enable:
                    title       : 'Enable'
                    description : 'When enabled, signature help (call tips) will be displayed when the keyboard cursor
                                   is inside a function, method or constructor call.'
                    type        : 'boolean'
                    default     : true
                    order       : 1

        linting:
            type: 'object'
            order: 5
            properties:
                enable:
                    title       : 'Enable'
                    description : 'When enabled, linting will show problems and warnings picked up in your code.'
                    type        : 'boolean'
                    default     : true
                    order       : 1

                showUnknownClasses:
                    title       : 'Show unknown classes'
                    description : 'Highlights class names that could not be found. This will also work for docblocks.'
                    type        : 'boolean'
                    default     : true
                    order       : 2

                showUnknownGlobalFunctions:
                    title       : 'Show unknown (global) functions'
                    description : 'Highlights (global) functions that could not be found.'
                    type        : 'boolean'
                    default     : true
                    order       : 3

                showUnknownGlobalConstants:
                    title       : 'Show unknown (global) constants'
                    description : 'Highlights (global) constants that could not be found.'
                    type        : 'boolean'
                    default     : true
                    order       : 4

                showUnusedUseStatements:
                    title       : 'Show unused use statements'
                    description : 'Highlights use statements that don\'t seem to be used anywhere.'
                    type        : 'boolean'
                    default     : true
                    order       : 5

                showMissingDocs:
                    title       : 'Show missing documentation'
                    description : 'Warns about structural elements that are missing documentation.'
                    type        : 'boolean'
                    default     : true
                    order       : 6

                validateDocblockCorrectness:
                    title       : 'Validate docblock correctness'
                    description : '''
                        Analyzes the correctness of docblocks of various structural elements and will show various
                        problems such as undocumented parameters, mismatched parameter and deprecated tags.
                    '''
                    type        : 'boolean'
                    default     : true
                    order       : 7

                showUnknownMembers:
                    title       : 'Show unknown members (experimental)'
                    description : '''
                        Highlights use of unknown members. Note that this can be a large strain on performance and is
                        experimental (expect false positives, especially inside conditionals).
                    '''
                    type        : 'boolean'
                    default     : false
                    order       : 8

    ###*
     * The version of the core to download (version specification string).
     *
     * @var {String}
    ###
    coreVersionSpecification: "3.0.0"

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
     * @var {Object|null}
    ###
    editorTimeoutMap: null

    ###*
     * @var {Object|null}
    ###
    tooltipProvider: null

    ###*
     * @var {Object|null}
    ###
    signatureHelpProvider: null

    ###*
     * @var {Object|null}
    ###
    linterProvider: null

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
                 sure they are in your PATH.

                 Please restart Atom after correcting the path."

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

            @performInitialFullIndexForCurrentProject()

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

        config.onDidChange 'general.insertNewlinesForUseStatements', (value) =>
            @getUseStatementHelper().setAllowAdditionalNewlines(value)

        config.onDidChange 'tooltips.enable', (value) =>
            if value
                @activateTooltips()

            else
                @deactivateTooltips()

        config.onDidChange 'signatureHelp.enable', (value) =>
            if value
                @activateSignatureHelp()

            else
                @deactivateSignatureHelp()

        config.onDidChange 'linting.enable', (value) =>
            if value
                @activateLinting()

            else
                @deactivateLinting()

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
     * @return {Promise}
    ###
    updateCoreIfOutdated: () ->
        if @getCoreManager().isInstalled()
            return new Promise (resolve, reject) ->
                resolve()

        message =
            "The core isn't installed yet or is outdated. A new version is in the process of being downloaded."

        atom.notifications.addInfo('PHP Integrator - Downloading Core', {'detail': message})

        successHandler = () ->
            atom.notifications.addSuccess('Core installation successful')

        failureHandler = () ->
            atom.notifications.addError('Core installation failed')

        return @getCoreManager().install().then(successHandler, failureHandler)

    ###*
     * Activates the package.
    ###
    activate: ->
        require('atom-package-deps').install(@packageName, true).then () =>
            return if not @testConfig(false)

            @updateCoreIfOutdated().then () =>
                @registerCommands()
                @registerConfigListeners()
                @registerStatusBarListeners()

                @editorTimeoutMap = {}

                @registerAtomListeners()

                if @getConfiguration().get('tooltips.enable')
                    @activateTooltips()

                if @getConfiguration().get('signatureHelp.enable')
                    @activateSignatureHelp()

                if @getConfiguration().get('linting.enable')
                    @activateLinting()

                @getCachingProxy().setIsActive(true)

    ###*
     * Registers listeners for events from Atom's API.
    ###
    registerAtomListeners: () ->
        @getDisposables().add atom.workspace.observeTextEditors (editor) =>
            @registerTextEditorListeners(editor)

    ###*
     * Activates the tooltip provider.
    ###
    activateTooltips: () ->
        @getTooltipProvider().activate(@getService())

    ###*
     * Deactivates the tooltip provider.
    ###
    deactivateTooltips: () ->
        @getTooltipProvider().deactivate()

    ###*
     * Activates the signature help provider.
    ###
    activateSignatureHelp: () ->
        @getSignatureHelpProvider().activate(@getService())

    ###*
     * Deactivates the signature help provider.
    ###
    deactivateSignatureHelp: () ->
        @getSignatureHelpProvider().deactivate()

    ###*
     * Activates linting.
    ###
    activateLinting: () ->
        @getLinterProvider().activate(@getService())

    ###*
     * Deactivates linting.
    ###
    deactivateLinting: () ->
        @getLinterProvider().deactivate()

    ###*
     * @param {TextEditor} editor
    ###
    registerTextEditorListeners: (editor) ->
        if @getConfiguration().get('general.indexContinuously') == true
            # The default onDidStopChanging timeout is 300 milliseconds. As this is notcurrently configurable (and would
            # also impact other packages), we install our own timeout on top of the existing one. This is useful for users
            # that don't type particularly fast or are on slower machines and will prevent constant indexing from happening.
            @getDisposables().add editor.onDidStopChanging () =>
                path = editor.getPath()

                additionalIndexingDelay = @getConfiguration().get('general.additionalIndexingDelay')

                @editorTimeoutMap[path] = setTimeout ( =>
                    @onEditorDidStopChanging(editor)
                    @editorTimeoutMap[path] = null
                ), additionalIndexingDelay

            @getDisposables().add editor.onDidChange () =>
                path = editor.getPath()

                if @editorTimeoutMap[path]?
                    clearTimeout(@editorTimeoutMap[path])
                    @editorTimeoutMap[path] = null

        else
            @getDisposables().add editor.onDidSave(@onEditorDidStopChanging.bind(this, editor))

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

        @getLinterProvider().deactivate()
        @getTooltipProvider().deactivate()
        @getSignatureHelpProvider().deactivate()

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
     * @param {mixed} service
     *
     * @return {Disposable}
    ###
    setLinterIndieService: (service) ->
        linter = service({
            name: 'PHP Integrator'
        })

        @getDisposables().add(linter)

        @getLinterProvider().setIndieLinter(linter)

    ###*
     * Sets the project manager service.
     *
     * @param {Object} service
    ###
    setProjectManagerService: (service) ->
        @projectManagerService = service

        # NOTE: This method is actually called whenever the project changes as well.
        service.getProject (project) =>
            @onProjectChanged(project)

    ###*
     * @param {Object} project
    ###
    onProjectChanged: (project) ->
        @activeProject = project

        return if not project?

        @proxy.clearCache()

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
     * Retrieves autocompletion providers for the autocompletion package.
     *
     * @return {Array}
    ###
    getAutocompletionProviderServices: () ->
        return [@getAutocompletionProvider()]

    ###*
     * Returns a list of intention providers.
     *
     * @return {Array}
    ###
    getIntentionProviders: () ->
        return @getTooltipProvider().getIntentionProviders()

    ###*
     * @return {Service}
    ###
    getService: () ->
        if not @service?
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
            @proxy.setCorePath(@getCoreManager().getCoreSourcePath())

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
     * @return {ComposerService}
    ###
    getComposerService: () ->
        if not @composerService?
            ComposerService = require './ComposerService';

            @composerService = new ComposerService(
                @getConfiguration().get('core.phpCommand'),
                @getConfiguration().get('packagePath') + '/core/'
            )

        return @composerService

    ###*
     * @return {CoreManager}
    ###
    getCoreManager: () ->
        if not @coreManager?
            CoreManager = require './CoreManager';

            @coreManager = new CoreManager(
                @getComposerService(),
                @coreVersionSpecification,
                @getConfiguration().get('packagePath') + '/core/'
            )

        return @coreManager

    ###*
     * @return {UseStatementHelper}
    ###
    getUseStatementHelper: () ->
        if not @useStatementHelper?
            UseStatementHelper = require './UseStatementHelper';

            @useStatementHelper = new UseStatementHelper(@getConfiguration().get('general.insertNewlinesForUseStatements'))

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

    ###*
     * @return {TooltipProvider}
    ###
    getTooltipProvider: () ->
        if not @tooltipProvider?
            TooltipProvider = require './TooltipProvider'

            @tooltipProvider = new TooltipProvider()

        return @tooltipProvider

    ###*
     * @return {SignatureHelpProvider}
    ###
    getSignatureHelpProvider: () ->
        if not @signatureHelpProvider?
            SignatureHelpProvider = require './SignatureHelpProvider'

            @signatureHelpProvider = new SignatureHelpProvider()

        return @signatureHelpProvider

    ###*
     * @return {LinterProvider}
    ###
    getLinterProvider: () ->
        if not @linterProvider?
            LinterProvider = require './LinterProvider'

            @linterProvider = new LinterProvider(@getConfiguration())

        return @linterProvider

    ###*
     * @return {AutocompletionProvider}
    ###
    getAutocompletionProvider: () ->
        if not @autocompletionProvider?
            AutocompletionProvider = require './AutocompletionProvider'

            @autocompletionProvider = new AutocompletionProvider(@getConfiguration(), @getService())

        return @autocompletionProvider
