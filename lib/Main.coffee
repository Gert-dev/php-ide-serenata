{Disposable, CompositeDisposable} = require 'atom';

{Emitter} = require 'event-kit';

packageDeps = require('atom-package-deps')

fs = require 'fs'
process = require 'process'

Proxy = require './Proxy'
Service = require './Service'
AtomConfig = require './AtomConfig'
PhpInvoker = require './PhpInvoker'
CoreManager = require './CoreManager';
ConfigTester = require './ConfigTester'
ProjectManager = require './ProjectManager'
LinterProvider = require './LinterProvider'
ComposerService = require './ComposerService';
DatatipProvider = require './DatatipProvider'
IndexingMediator = require './IndexingMediator'
UseStatementHelper = require './UseStatementHelper';
SignatureHelpProvider = require './SignatureHelpProvider'
GotoDefinitionProvider = require './GotoDefinitionProvider'
AutocompletionProvider = require './AutocompletionProvider'

MethodAnnotationProvider = require './Annotations/MethodAnnotationProvider'
PropertyAnnotationProvider = require './Annotations/PropertyAnnotationProvider'

DocblockProvider = require './Refactoring/DocblockProvider'
GetterSetterProvider = require './Refactoring/GetterSetterProvider'
ExtractMethodProvider = require './Refactoring/ExtractMethodProvider'
OverrideMethodProvider = require './Refactoring/OverrideMethodProvider'
IntroducePropertyProvider = require './Refactoring/IntroducePropertyProvider'
StubAbstractMethodProvider = require './Refactoring/StubAbstractMethodProvider'
StubInterfaceMethodProvider = require './Refactoring/StubInterfaceMethodProvider'
ConstructorGenerationProvider = require './Refactoring/ConstructorGenerationProvider'

Builder = require './Refactoring/ExtractMethodProvider/Builder'
TypeHelper = require './Refactoring/Utility/TypeHelper'
DocblockBuilder = require './Refactoring/Utility/DocblockBuilder'
FunctionBuilder = require './Refactoring/Utility/FunctionBuilder'
ParameterParser = require './Refactoring/ExtractMethodProvider/ParameterParser'

module.exports =
    ###*
     * Configuration settings.
    ###
    config:
        core:
            type: 'object'
            order: 1
            properties:
                phpExecutionType:
                    title       : 'PHP execution type'
                    description : "How to start PHP, which is needed to start the core in turn. \n \n

                                   'Use PHP on the host' uses a PHP binary installed on your local machine. 'Use PHP
                                   container via Docker' requires Docker and uses a PHP container to start the server
                                   with. Using PolicyKit allows Linux users that are not part of the Docker group to
                                   enter their password via an authentication dialog to temporarily escalate privileges
                                   so the Docker daemon can be invoked once to start the server. \n \n

                                   You can use the php-ide-serenata:test-configuration command to test your setup.
                                   \n \n

                                   Requires a restart after changing. \n \n"
                    type        : 'string'
                    default     : 'host'
                    order       : 1
                    enum        : [
                        {
                            value       : 'host'
                            description : 'Use PHP on the host'
                        },

                        {
                            value       : 'docker'
                            description : 'Use a PHP container via Docker (experimental)'
                        },

                        {
                            value       : 'docker-polkit'
                            description : 'Use a PHP container via Docker, using PolicyKit for privilege escalation ' +
                                          ' (experimental, Linux only)'
                        }
                    ]

                phpCommand:
                    title       : 'PHP command'
                    description : 'The path to your PHP binary (e.g. /usr/bin/php, php, ...). Only applies if you\'ve
                                   selected "Use PHP on the host" above. \n \n

                                   Requires a restart after changing.'
                    type        : 'string'
                    default     : 'php'
                    order       : 2

                memoryLimit:
                    title       : 'Memory limit (in MB)'
                    description : 'The memory limit to set for the PHP process. The PHP process uses the available
                                   memory for in-memory caching as well, so it should not be too low. On the other hand,
                                   it shouldn\'t be growing very large, so setting it to -1 is probably a bad idea as
                                   an infinite loop bug might take down your system. The default should suit most
                                   projects, from small to large. \n \n
                                   Requires a restart after changing.'
                    type        : 'integer'
                    default     : 2048
                    order       : 3

                additionalDockerVolumes:
                    title       : 'Additional Docker volumes'
                    description : 'Additional paths to mount as Docker volumes. Only applies when using Docker to run
                                   the core. Separate these using comma\'s, where each item follows the format
                                   "src:dest" as the Docker -v flag uses. \n \n
                                   Requires a restart after changing.'
                    type        : 'array'
                    default     : []
                    order       : 4
                    items       :
                        type : 'string'

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

        annotations:
            type: 'object'
            order: 7
            properties:
                enable:
                    title       : 'Enable'
                    description : 'When enabled, annotations will be shown in the gutter with more information
                                   regarding member overrides and interface implementations.'
                    type        : 'boolean'
                    default     : true
                    order       : 1

        refactoring:
            type: 'object'
            order: 8
            properties:
                enable:
                    title       : 'Enable'
                    description : 'When enabled, refactoring actions will be available via the intentions package.'
                    type        : 'boolean'
                    default     : true
                    order       : 1

        linting:
            type: 'object'
            order: 9
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
    coreVersionSpecification: "4.0.1"

    ###*
     * The name of the package.
     *
     * @var {String}
    ###
    packageName: 'php-ide-serenata'

    ###*
     * The configuration object.
     *
     * @var {Object}
    ###
    configuration: null

    ###*
     * @var {Object}
    ###
    PhpInvoker: null

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
     * @var {Object|null}
    ###
    typeHelper: null

    ###*
     * @var {Object|null}
    ###
    docblockBuilder: null

    ###*
     * @var {Object|null}
    ###
    functionBuilder: null

    ###*
     * @var {Object|null}
    ###
    parameterParser: null

    ###*
     * @var {Object|null}
    ###
    builder: null

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
     * @var {Array|null}
    ###
    annotationProviders: null

    ###*
     * @var {Array|null}
    ###
    refactoringProviders: null

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
    ###
    testConfig: () ->
        configTester = new ConfigTester(@getPhpInvoker())

        atom.notifications.addInfo 'Serenata - Testing Configuration', {
            dismissable: true,
            detail: 'Now testing your configuration... \n \n' +

                    'If you\'ve selected Docker, this may take a while the first time
                     as the Docker image has to be fetched first.'
        }

        callback = () =>
            return configTester.test().then (wasSuccessful) =>
                if not wasSuccessful
                    errorMessage =
                        "PHP is not configured correctly. Please visit the settings screen to correct this error. If you are
                        using a relative path to PHP, make sure it is in your PATH variable."

                    atom.notifications.addError('Serenata - Failure', {dismissable: true, detail: errorMessage})

                else
                    atom.notifications.addSuccess 'Serenata - Success', {
                        dismissable: true,
                        detail: 'Your setup is working correctly.'
                    }

        return @busySignalService.reportBusyWhile('Testing your configuration...', callback, {
            waitingFor    : 'computer',
            revealTooltip : false
        });

    ###*
     * Registers any commands that are available to the user.
    ###
    registerCommands: () ->
        atom.commands.add 'atom-workspace', "php-ide-serenata:set-up-current-project": =>
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

        atom.commands.add 'atom-workspace', "php-ide-serenata:index-project": =>
            return if not @projectManager.hasActiveProject()

            @projectManager.attemptCurrentProjectIndex()

        atom.commands.add 'atom-workspace', "php-ide-serenata:force-index-project": =>
            return if not @projectManager.hasActiveProject()

            @performInitialFullIndexForCurrentProject()

        atom.commands.add 'atom-workspace', "php-ide-serenata:test-configuration": =>
            @testConfig()

        atom.commands.add 'atom-workspace', "php-ide-serenata:sort-use-statements": =>
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

        config.onDidChange 'annotations.enable', (value) =>
            if value
                @activateAnnotations()

            else
                @deactivateAnnotations()

        config.onDidChange 'refactoring.enable', (value) =>
            if value
                @activateRefactoring()

            else
                @deactivateRefactoring()

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

            if path.indexOf('~') != false
                path = path.replace('~', process.env.HOME)

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
    installCoreIfNecessary: () ->
        return new Promise (resolve, reject) =>
            if @getCoreManager().isInstalled()
                resolve()
                return

            message =
                "The core isn't installed yet or is outdated. I can install the latest version for you " +
                "automatically.\n \n" +

                "First time using this package? Please visit the package settings to set up PHP correctly first."

            notification = atom.notifications.addInfo('Serenata - Core Installation', {
                detail      : message
                dismissable : true

                buttons: [
                    {
                        text: 'Open package settings'
                        onDidClick: () =>
                            atom.workspace.open('atom://config/packages/' + @packageName)
                    },

                    {
                        text: 'Test my setup'
                        onDidClick: () =>
                            @testConfig()
                    },

                    {
                        text: 'Ready, install the core'
                        onDidClick: () =>
                            notification.dismiss()

                            callback = () =>
                                promise = @installCore()

                                promise.catch () =>
                                    reject(new Error('Core installation failed'))

                                return promise.then () =>
                                    resolve()

                            @busySignalService.reportBusyWhile('Installing the core...', callback, {
                                waitingFor    : 'computer',
                                revealTooltip : false
                            });
                    },

                    {
                        text: 'No, go away'
                        onDidClick: () =>
                            notification.dismiss()
                            reject()
                    }
                ]
            })

    ###*
     * @return {Promise}
    ###
    installCore: () ->
        message =
            "The core is being downloaded and installed. To do this, Composer is automatically downloaded and " +
            "installed into a temporary folder.\n \n" +

            "Progress and output is sent to the developer tools console, in case you'd like to monitor it.\n \n" +

            "You will be notified once the install finishes (or fails)."

        atom.notifications.addInfo('Serenata - Installing Core', {'detail': message, dismissable: true})

        successHandler = () ->
            atom.notifications.addSuccess('Serenata - Core Installation Succeeded', dismissable: true)

        failureHandler = () ->
            message =
                "Installation of the core failed. This can happen for a variety of reasons, such as an outdated PHP " +
                "version or missing extensions.\n \n" +

                "Logs in the developer tools will likely provide you with more information about what is wrong. You " +
                "can open it via the menu View → Developer → Toggle Developer Tools.\n \n" +

                "Additionally, the README provides more information about requirements and troubleshooting."

            atom.notifications.addError('Serenata - Core Installation Failed', {detail: message, dismissable: true})

        @getCoreManager().install().then(successHandler, failureHandler)

    ###*
     * Checks if the php-integrator-navigation package is installed and notifies the user it is obsolete if it is.
    ###
    notifyAboutRedundantNavigationPackageIfNecessary: () ->
        atom.packages.onDidActivatePackage (packageData) ->
            return if packageData.name != 'php-integrator-navigation'

            message =
                "It seems you still have the php-integrator-navigation package installed and activated. As of this " +
                "release, it is obsolete and all its functionality is already included in the base package.\n \n" +

                "It is recommended to disable or remove it, shall I disable it for you?"

            notification = atom.notifications.addInfo('Serenata - Navigation', {
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
    notifyAboutRedundantAutocompletionPackageIfNecessary: () ->
        atom.packages.onDidActivatePackage (packageData) ->
            return if packageData.name != 'php-integrator-autocomplete-plus'

            message =
                "It seems you still have the php-integrator-autocomplete-plus package installed and activated. As of " +
                "this release, it is obsolete and all its functionality is already included in the base package.\n \n" +

                "It is recommended to disable or remove it, shall I disable it for you?"

            notification = atom.notifications.addInfo('Serenata - Autocompletion', {
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
     * Checks if the php-integrator-annotations package is installed and notifies the user it is obsolete if it
     * is.
    ###
    notifyAboutRedundantAnnotationsPackageIfNecessary: () ->
        atom.packages.onDidActivatePackage (packageData) ->
            return if packageData.name != 'php-integrator-annotations'

            message =
                "It seems you still have the php-integrator-annotations package installed and activated. As of " +
                "this release, it is obsolete and all its functionality is already included in the base package.\n \n" +

                "It is recommended to disable or remove it, shall I disable it for you?"

            notification = atom.notifications.addInfo('Serenata - Autocompletion', {
                detail      : message
                dismissable : true

                buttons: [
                    {
                        text: 'Yes, nuke it'
                        onDidClick: () ->
                            atom.packages.disablePackage('php-integrator-annotations');
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
     * Checks if the php-integrator-refactoring package is installed and notifies the user it is obsolete if it
     * is.
    ###
    notifyAboutRedundantRefactoringPackageIfNecessary: () ->
        atom.packages.onDidActivatePackage (packageData) ->
            return if packageData.name != 'php-integrator-refactoring'

            message =
                "It seems you still have the php-integrator-refactoring package installed and activated. As of " +
                "this release, it is obsolete and all its functionality is already included in the base package.\n \n" +

                "It is recommended to disable or remove it, shall I disable it for you?"

            notification = atom.notifications.addInfo('Serenata - Autocompletion', {
                detail      : message
                dismissable : true

                buttons: [
                    {
                        text: 'Yes, nuke it'
                        onDidClick: () ->
                            atom.packages.disablePackage('php-integrator-refactoring');
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
        return packageDeps.install(@packageName, true).then () =>
            promise = @installCoreIfNecessary()

            promise.then () =>
                @doActivate()

            promise.catch () =>
                return

            return promise

    ###*
     * Does the actual activation.
    ###
    doActivate: () ->
        @notifyAboutRedundantNavigationPackageIfNecessary()
        @notifyAboutRedundantAutocompletionPackageIfNecessary()
        @notifyAboutRedundantAnnotationsPackageIfNecessary()
        @notifyAboutRedundantRefactoringPackageIfNecessary()

        @registerCommands()
        @registerConfigListeners()
        @registerStatusBarListeners()

        @editorTimeoutMap = {}

        @registerAtomListeners()

        if @getConfiguration().get('datatips.enable')
            @activateDatatips()

        if @getConfiguration().get('signatureHelp.enable')
            @activateSignatureHelp()

        if @getConfiguration().get('annotations.enable')
            @activateAnnotations()

        if @getConfiguration().get('linting.enable')
            @activateLinting()

        if @getConfiguration().get('refactoring.enable')
            @activateRefactoring()

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
     * Activates annotations.
    ###
    activateAnnotations: () ->
        @annotationProviders = []
        @annotationProviders.push new MethodAnnotationProvider()
        @annotationProviders.push new PropertyAnnotationProvider()

        for provider in @annotationProviders
            provider.activate(@getService())

    ###*
     * Deactivates annotations.
    ###
    deactivateAnnotations: () ->
        for provider in @annotationProviders
            provider.deactivate()

        @annotationProviders = []

    ###*
     * Activates refactoring.
    ###
    activateRefactoring: () ->
        @getRefactoringBuilder().setService(@getService())
        @getRefactoringTypeHelper().setService(@getService())

        for provider in @getRefactoringProviders()
            provider.activate(@getService())

    ###*
     * Deactivates refactoring.
    ###
    deactivateRefactoring: () ->
        for provider in @getRefactoringProviders()
            provider.deactivate()

        @refactoringProviders = null

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

        @deactivateLinting()
        @deactivateDatatips()
        @deactivateSignatureHelp()
        @deactivateAnnotations()
        @deactivateRefactoring()

        @getProxy().exit()

        return

    ###*
     * @param {mixed} service
     *
     * @return {Disposable}
    ###
    setLinterIndieService: (service) ->
        linter = service({
            name: 'Serenata'
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
     * Consumes the atom/snippet service.
     *
     * @param {Object} snippetManager
    ###
    setSnippetManager: (snippetManager) ->
        for provider in @getRefactoringProviders()
            provider.setSnippetManager(snippetManager)

    ###*
     * Returns a list of intention providers.
     *
     * @return {Array}
    ###
    provideIntentions: () ->
        intentionProviders = []

        for provider in @getRefactoringProviders()
            intentionProviders = intentionProviders.concat(provider.getIntentionProviders())

        return intentionProviders

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
     * @return {Configuration}
    ###
    getPhpInvoker: () ->
        if not @phpInvoker?
            @phpInvoker = new PhpInvoker(@getConfiguration())

        return @phpInvoker

    ###*
     * @return {Proxy}
    ###
    getProxy: () ->
        if not @proxy?
            @proxy = new Proxy(@getConfiguration(), @getPhpInvoker())
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
                @getPhpInvoker(),
                @getConfiguration().get('storagePath') + '/core/'
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
                @getConfiguration().get('storagePath') + '/core/'
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

    ###*
     * @return {TypeHelper}
    ###
    getRefactoringTypeHelper: () ->
        if not @typeHelper?
            @typeHelper = new TypeHelper()

        return @typeHelper

    ###*
     * @return {DocblockBuilder}
    ###
    getRefactoringDocblockBuilder: () ->
        if not @docblockBuilder?
            @docblockBuilder = new DocblockBuilder()

        return @docblockBuilder

    ###*
     * @return {FunctionBuilder}
    ###
    getRefactoringFunctionBuilder: () ->
        if not @functionBuilder?
            @functionBuilder = new FunctionBuilder()

        return @functionBuilder

    ###*
     * @return {ParameterParser}
    ###
    getRefactoringParameterParser: () ->
        if not @parameterParser?
            @parameterParser = new ParameterParser(@getRefactoringTypeHelper())

        return @parameterParser

    ###*
     * @return {Builder}
    ###
    getRefactoringBuilder: () ->
        if not @builder?
            @builder = new Builder(
                @getRefactoringParameterParser(),
                @getRefactoringDocblockBuilder(),
                @getRefactoringFunctionBuilder(),
                @getRefactoringTypeHelper()
            )

        return @builder

    ###*
     * @return {Array}
    ###
    getRefactoringProviders: () ->
        if not @refactoringProviders?
            @refactoringProviders = []
            @refactoringProviders.push new DocblockProvider(@getRefactoringTypeHelper(), @getRefactoringDocblockBuilder())
            @refactoringProviders.push new IntroducePropertyProvider(@getRefactoringDocblockBuilder())
            @refactoringProviders.push new GetterSetterProvider(@getRefactoringTypeHelper(), @getRefactoringFunctionBuilder(), @getRefactoringDocblockBuilder())
            @refactoringProviders.push new ExtractMethodProvider(@getRefactoringBuilder())
            @refactoringProviders.push new ConstructorGenerationProvider(@getRefactoringTypeHelper(), @getRefactoringFunctionBuilder(), @getRefactoringDocblockBuilder())

            @refactoringProviders.push new OverrideMethodProvider(@getRefactoringDocblockBuilder(), @getRefactoringFunctionBuilder())
            @refactoringProviders.push new StubAbstractMethodProvider(@getRefactoringDocblockBuilder(), @getRefactoringFunctionBuilder())
            @refactoringProviders.push new StubInterfaceMethodProvider(@getRefactoringDocblockBuilder(), @getRefactoringFunctionBuilder())

        return @refactoringProviders
