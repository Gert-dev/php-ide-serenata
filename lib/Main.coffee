{Disposable, CompositeDisposable} = require 'atom';

{Emitter} = require 'event-kit';

packageDeps = require('atom-package-deps')

fs = require 'fs'

Proxy =                  require './Proxy'
Service =                require './Service'
AtomConfig =             require './AtomConfig'
CoreManager =            require './CoreManager';
ConfigTester =           require './ConfigTester'
ProjectManager =         require './ProjectManager'
LinterProvider =         require './LinterProvider'
ComposerService =        require './ComposerService';
DatatipProvider =        require './DatatipProvider'
IndexingMediator =       require './IndexingMediator'
UseStatementHelper =     require './UseStatementHelper';
SignatureHelpProvider =  require './SignatureHelpProvider'
GotoDefinitionProvider = require './GotoDefinitionProvider'
AutocompletionProvider = require './AutocompletionProvider'

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
                    default     : 500
                    order       : 2

        datatips:
            type: 'object'
            order: 3
            properties:
                enable:
                    title       : 'Enable'
                    description : 'When enabled, documentation for various structural elements can be displayed in a
                                  datatip (tooltip).'
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

        gotoDefinition:
            type: 'object'
            order: 5
            properties:
                enable:
                    title       : 'Enable'
                    description : 'When enabled, code navigation will be activated via the hyperclick package.'
                    type        : 'boolean'
                    default     : true
                    order       : 1

        autocompletion:
            type: 'object'
            order: 6
            properties:
                enable:
                    title       : 'Enable'
                    description : 'When enabled, autocompletion will be activated via the autocomplete-plus package.'
                    type        : 'boolean'
                    default     : true
                    order       : 1

        linting:
            type: 'object'
            order: 7
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
    coreVersionSpecification: "3.2.1"

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
    datatipProvider: null

    ###*
     * @var {Object|null}
    ###
    signatureHelpProvider: null

    ###*
     * @var {Object|null}
    ###
    gotoDefinitionProvider: null

    ###*
     * @var {Object|null}
    ###
    linterProvider: null

    ###*
     * @var {Object|null}
    ###
    busySignalService: null

    ###*
     * Tests the user's configuration.
     *
     * @param {bool} testServices
     *
     * @return {bool}
    ###
    testConfig: (testServices = true) ->
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
                    No project is currently active. Please save and activate one before attempting to set it up.
                    You can do it via the menu Packages → Project Manager → Save Project.
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

        config.onDidChange 'datatips.enable', (value) =>
            if value
                @activateDatatips()

            else
                @deactivateDatatips()

        config.onDidChange 'signatureHelp.enable', (value) =>
            if value
                @activateSignatureHelp()

            else
                @deactivateSignatureHelp()

        config.onDidChange 'gotoDefintion.enable', (value) =>
            if value
                @activateGotoDefinition()

            else
                @deactivateGotoDefinition()

        config.onDidChange 'autocompletion.enable', (value) =>
            if value
                @activateAutocompletion()

            else
                @deactivateAutocompletion()

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

        indexBusyMessageMap = new Map()

        getBaseMessageForPath = (path) ->
            if Array.isArray(path)
                path = path[0]

            if fs.lstatSync(path).isDirectory()
                return 'Indexing project - code assistance may be unavailable or incomplete'

            return 'Indexing ' + path

        service.onDidStartIndexing ({path}) =>
            if not indexBusyMessageMap.has(path)
                indexBusyMessageMap.set(path, new Array())

            indexBusyMessageMap.get(path).push(@busySignalService.reportBusy(getBaseMessageForPath(path), {
                waitingFor    : 'computer',
                revealTooltip : true
            }))

        service.onDidFinishIndexing ({path}) =>
            return if not indexBusyMessageMap.has(path)

            indexBusyMessageMap.get(path).forEach((busyMessage) => busyMessage.dispose())
            indexBusyMessageMap.delete(path)

        service.onDidFailIndexing ({path}) =>
            return if not indexBusyMessageMap.has(path)

            indexBusyMessageMap.get(path).forEach((busyMessage) => busyMessage.dispose())
            indexBusyMessageMap.delete(path)

        service.onDidIndexingProgress ({path, percentage}) =>
            return if not indexBusyMessageMap.has(path)

            indexBusyMessageMap.get(path).forEach (busyMessage) =>
                busyMessage.setTitle(getBaseMessageForPath(path) + " (" + percentage.toFixed(2) + " %)")

    ###*
     * @return {Promise}
    ###
    updateCoreIfOutdated: () ->
        if @getCoreManager().isInstalled()
            return new Promise (resolve, reject) ->
                resolve()

        message =
            "The core isn't installed yet or is outdated. A new version is in the process of being downloaded.\n \n" +

            "Progress is being sent to the developer tools console, in case you'd like to monitor it.\n \n" +

            "You will be notified once the install finishes (or fails)."

        atom.notifications.addInfo('PHP Integrator - Downloading Core', {'detail': message, dismissable: true})

        successHandler = () ->
            atom.notifications.addSuccess('Core installation successful', dismissable: true)

        failureHandler = () ->
            message =
                "The core failed to install. This can happen for a variety of reasons, such as an outdated PHP " +
                "version or missing extensions.\n \n" +

                "Logs in the developer tools will likely provide you with more information about what is wrong. You " +
                "can open it via the menu View → Developer → Toggle Developer Tools.\n \n" +

                "Additionally, the README provides more information about requirements and troubleshooting."

            atom.notifications.addError('Core installation failed', {detail: message, dismissable: true})

        return @getCoreManager().install().then(successHandler, failureHandler)

    ###*
     * Checks if the php-integrator-navigation package is installed and notifies the user it is obsolete if it is.
    ###
    notifyAboutRedundantNavigationPackageIfnecessary: () ->
        atom.packages.onDidActivatePackage (packageData) ->
            return if packageData.name != 'php-integrator-navigation'

            message =
                "It seems you still have the php-integrator-navigation package installed and activated. As of this " +
                "release, it is obsolete and all its functionality is already included in the base package.\n \n" +

                "It is recommended to disable or remove it, shall I disable it for you?"

            notification = atom.notifications.addInfo('PHP Integrator - Navigation', {
                detail      : message
                dismissable : true

                buttons: [
                    {
                        text: 'Yes, nuke it'
                        onDidClick: () ->
                            atom.packages.disablePackage('php-integrator-navigation');
                            notification.dismiss()
                    },

                    {
                        text: 'No, don\'t touch it'
                        onDidClick: () ->
                            notification.dismiss()
                    }
                ]
            })

    ###*
     * Checks if the php-integrator-autocomplete-plus package is installed and notifies the user it is obsolete if it
     * is.
    ###
    notifyAboutRedundantAutocompletionPackageIfnecessary: () ->
        atom.packages.onDidActivatePackage (packageData) ->
            return if packageData.name != 'php-integrator-autocomplete-plus'

            message =
                "It seems you still have the php-integrator-autocomplete-plus package installed and activated. As of " +
                "this release, it is obsolete and all its functionality is already included in the base package.\n \n" +

                "It is recommended to disable or remove it, shall I disable it for you?"

            notification = atom.notifications.addInfo('PHP Integrator - Autocompletion', {
                detail      : message
                dismissable : true

                buttons: [
                    {
                        text: 'Yes, nuke it'
                        onDidClick: () ->
                            atom.packages.disablePackage('php-integrator-autocomplete-plus');
                            notification.dismiss()
                    },

                    {
                        text: 'No, don\'t touch it'
                        onDidClick: () ->
                            notification.dismiss()
                    }
                ]
            })

    ###*
     * Activates the package.
    ###
    activate: ->
        packageDeps.install(@packageName, true).then () =>
            return if not @testConfig(false)

            @updateCoreIfOutdated().then () =>
                @notifyAboutRedundantNavigationPackageIfnecessary()
                @notifyAboutRedundantAutocompletionPackageIfnecessary()

                @registerCommands()
                @registerConfigListeners()
                @registerStatusBarListeners()

                @editorTimeoutMap = {}

                @registerAtomListeners()

                if @getConfiguration().get('datatips.enable')
                    @activateDatatips()

                if @getConfiguration().get('signatureHelp.enable')
                    @activateSignatureHelp()

                if @getConfiguration().get('linting.enable')
                    @activateLinting()

                if @getConfiguration().get('gotoDefinition.enable')
                    @activateGotoDefinition()

                if @getConfiguration().get('autocompletion.enable')
                    @activateAutocompletion()

                @getProxy().setIsActive(true)

                # This fixes the corner case where the core is still installing, the project manager service has already
                # loaded and the project is already active. At that point, the index that resulted from it silently
                # failed because the proxy (and core) weren't active yet. This in turn causes the project to not
                # automatically start indexing, which is especially relevant if a core update requires a reindex.
                if @activeProject?
                    @changeActiveProject(@activeProject)

    ###*
     * Registers listeners for events from Atom's API.
    ###
    registerAtomListeners: () ->
        @getDisposables().add atom.workspace.observeTextEditors (editor) =>
            @registerTextEditorListeners(editor)

    ###*
     * Activates the datatip provider.
    ###
    activateDatatips: () ->
        @getDatatipProvider().activate(@getService())

    ###*
     * Deactivates the datatip provider.
    ###
    deactivateDatatips: () ->
        @getDatatipProvider().deactivate()

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
     * Activates the goto definition provider.
    ###
    activateGotoDefinition: () ->
        @getGotoDefinitionProvider().activate(@getService())

    ###*
     * Deactivates the goto definition provider.
    ###
    deactivateGotoDefinition: () ->
        @getGotoDefinitionProvider().deactivate()

    ###*
     * Activates the goto definition provider.
    ###
    activateAutocompletion: () ->
        @getAutocompletionProvider().activate(@getService())

    ###*
     * Deactivates the goto definition provider.
    ###
    deactivateAutocompletion: () ->
        @getAutocompletionProvider().deactivate()

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
        if @disposables?
            @disposables.dispose()
            @disposables = null

        @getLinterProvider().deactivate()
        @getDatatipProvider().deactivate()
        @getSignatureHelpProvider().deactivate()

        @getProxy().exit()

        return

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
        @changeActiveProject(project)

    ###*
     * @param {Object} project
    ###
    changeActiveProject: (project) ->
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

        return

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
     * @param {Object} signatureHelpService
    ###
    consumeSignatureHelpService: (signatureHelpService) ->
        signatureHelpService(@getSignatureHelpProvider())

    ###*
     * @param {Object} busySignalService
    ###
    consumeBusySignalService: (busySignalService) ->
        @busySignalService = busySignalService

    ###*
     * @param {Object} datatipService
    ###
    consumeDatatipService: (datatipService) ->
        datatipService.addProvider(@getDatatipProvider())

    ###*
     * Returns the hyperclick provider.
     *
     * @return {Object}
    ###
    getHyperclickProvider: () ->
        return @getGotoDefinitionProvider()

    ###*
     * @return {Service}
    ###
    getService: () ->
        if not @service?
            @service = new Service(
                @getConfiguration(),
                @getProxy(),
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
            @disposables = new CompositeDisposable()

        return @disposables

    ###*
     * @return {Configuration}
    ###
    getConfiguration: () ->
        if not @configuration?
            @configuration = new AtomConfig(@packageName)

        return @configuration

    ###*
     * @return {Proxy}
    ###
    getProxy: () ->
        if not @proxy?
            @proxy = new Proxy(@getConfiguration())
            @proxy.setCorePath(@getCoreManager().getCoreSourcePath())

        return @proxy

    ###*
     * @return {Emitter}
    ###
    getEmitter: () ->
        if not @emitter?
            @emitter = new Emitter()

        return @emitter

    ###*
     * @return {ComposerService}
    ###
    getComposerService: () ->
        if not @composerService?
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
            @useStatementHelper = new UseStatementHelper(true)

        return @useStatementHelper

    ###*
     * @return {IndexingMediator}
    ###
    getIndexingMediator: () ->
        if not @indexingMediator?
            @indexingMediator = new IndexingMediator(@getProxy(), @getEmitter())

        return @indexingMediator

    ###*
     * @return {ProjectManager}
    ###
    getProjectManager: () ->
        if not @projectManager?
            @projectManager = new ProjectManager(@getProxy(), @getIndexingMediator())

        return @projectManager

    ###*
     * @return {DatatipProvider}
    ###
    getDatatipProvider: () ->
        if not @datatipProvider?
            @datatipProvider = new DatatipProvider()

        return @datatipProvider

    ###*
     * @return {SignatureHelpProvider}
    ###
    getSignatureHelpProvider: () ->
        if not @signatureHelpProvider?
            @signatureHelpProvider = new SignatureHelpProvider()

        return @signatureHelpProvider

    ###*
     * @return {GotoDefinitionProvider}
    ###
    getGotoDefinitionProvider: () ->
        if not @gotoDefinitionProvider?
            @gotoDefinitionProvider = new GotoDefinitionProvider()

        return @gotoDefinitionProvider

    ###*
     * @return {LinterProvider}
    ###
    getLinterProvider: () ->
        if not @linterProvider?
            @linterProvider = new LinterProvider(@getConfiguration())

        return @linterProvider

    ###*
     * @return {AutocompletionProvider}
    ###
    getAutocompletionProvider: () ->
        if not @autocompletionProvider?
            @autocompletionProvider = new AutocompletionProvider()

        return @autocompletionProvider
