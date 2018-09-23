/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
const {CompositeDisposable} = require('atom');

const packageDeps = require('atom-package-deps');

const fs = require('fs');
const process = require('process');

const Proxy = require('./Proxy');
const Service = require('./Service');
const AtomConfig = require('./AtomConfig');
const PhpInvoker = require('./PhpInvoker');
const ConfigTester = require('./ConfigTester');
const ServerManager = require('./ServerManager');
const ProjectManager = require('./ProjectManager');
const LinterProvider = require('./LinterProvider');
const ComposerService = require('./ComposerService');
const UseStatementHelper = require('./UseStatementHelper');

const MethodAnnotationProvider = require('./Annotations/MethodAnnotationProvider');
const PropertyAnnotationProvider = require('./Annotations/PropertyAnnotationProvider');

const DocblockProvider = require('./Refactoring/DocblockProvider');
const GetterSetterProvider = require('./Refactoring/GetterSetterProvider');
const ExtractMethodProvider = require('./Refactoring/ExtractMethodProvider');
const OverrideMethodProvider = require('./Refactoring/OverrideMethodProvider');
const IntroducePropertyProvider = require('./Refactoring/IntroducePropertyProvider');
const StubAbstractMethodProvider = require('./Refactoring/StubAbstractMethodProvider');
const StubInterfaceMethodProvider = require('./Refactoring/StubInterfaceMethodProvider');
const ConstructorGenerationProvider = require('./Refactoring/ConstructorGenerationProvider');

const Builder = require('./Refactoring/ExtractMethodProvider/Builder');
const TypeHelper = require('./Refactoring/Utility/TypeHelper');
const DocblockBuilder = require('./Refactoring/Utility/DocblockBuilder');
const FunctionBuilder = require('./Refactoring/Utility/FunctionBuilder');
const ParameterParser = require('./Refactoring/ExtractMethodProvider/ParameterParser');

const functions = {
    /**
     * The version of the server to download (version specification string).
     *
     * @var {String}
    */
    serverVersionSpecification: '4.3.0',

    /**
     * The name of the package.
     *
     * @var {String}
    */
    packageName: 'php-ide-serenata',

    /**
     * The configuration object.
     *
     * @var {Object}
    */
    configuration: null,

    /**
     * @var {Object}
    */
    PhpInvoker: null,

    /**
     * The proxy object.
     *
     * @var {Object}
    */
    proxy: null,

    /**
     * The exposed service.
     *
     * @var {Object}
    */
    service: null,

    /**
     * A list of disposables to dispose when the package deactivates.
     *
     * @var {Object|null}
    */
    disposables: null,

    /**
     * @var {String|null}
    */
    timerName: null,

    /**
     * @var {Object|null}
    */
    typeHelper: null,

    /**
     * @var {Object|null}
    */
    docblockBuilder: null,

    /**
     * @var {Object|null}
    */
    functionBuilder: null,

    /**
     * @var {Object|null}
    */
    parameterParser: null,

    /**
     * @var {Object|null}
    */
    builder: null,

    /**
     * @var {Array|null}
    */
    annotationProviders: null,

    /**
     * @var {Array|null}
    */
    refactoringProviders: null,

    /**
     * @var {Object|null}
    */
    linterProvider: null,

    /**
     * @var {Object|null}
    */
    busySignalService: null,

    /**
     * Tests the user's configuration.
    */
    testConfig() {
        const configTester = new ConfigTester(this.getPhpInvoker());

        atom.notifications.addInfo('Serenata - Testing Configuration', {
            dismissable: true,
            detail: 'Now testing your configuration... \n \n' +

                    `If you\'ve selected Docker, this may take a while the first time \
as the Docker image has to be fetched first.`
        });

        const callback = () => {
            return configTester.test().then(wasSuccessful => {
                if (!wasSuccessful) {
                    const errorMessage =
                        `PHP is not configured correctly. Please visit the settings screen to correct this error. If you are \
using a relative path to PHP, make sure it is in your PATH variable.`;

                    return atom.notifications.addError('Serenata - Failure', {dismissable: true, detail: errorMessage});

                } else {
                    return atom.notifications.addSuccess('Serenata - Success', {
                        dismissable: true,
                        detail: 'Your setup is working correctly.'
                    });
                }
            });
        };

        return this.busySignalService.reportBusyWhile('Testing your configuration...', callback, {
            waitingFor    : 'computer',
            revealTooltip : false
        });
    },

    /**
     * Registers any commands that are available to the user.
    */
    registerCommands() {
        atom.commands.add('atom-workspace', { 'php-ide-serenata:set-up-current-project': () => {
            const paths = atom.project.getPaths();

            if (paths.length === 0) {
                atom.notifications.addError('Serenata - No active project', {
                    detail : 'Please ensure the folder of your project is already active in the tree view before ' +
                        'attempting to set it up.'
                });

                return;
            }

            try {
                this.projectManager.setUpProject(paths[0]);
            } catch (error) {
                atom.notifications.addError('Serenata - Could not set up project', {
                    detail : error.message
                });

                return;
            }

            atom.notifications.addSuccess('Success', {
                detail : 'Your active project has been set up for Serenata. Indexing will now commence.'
            });

            this.projectManager.load(paths[0]);
        }});

        atom.commands.add('atom-workspace', { 'php-ide-serenata:test-configuration': () => {
            return this.testConfig();
        }});

        return atom.commands.add('atom-workspace', { 'php-ide-serenata:sort-use-statements': () => {
            const activeTextEditor = atom.workspace.getActiveTextEditor();

            if ((activeTextEditor == null)) { return; }

            return this.getUseStatementHelper().sortUseStatements(activeTextEditor);
        }});
    },

    /**
     * Registers listeners for configuration changes.
    */
    registerConfigListeners() {
        const config = this.getConfiguration();

        config.onDidChange('annotations.enable', value => {
            if (value) {
                return this.activateAnnotations();

            } else {
                return this.deactivateAnnotations();
            }
        });

        config.onDidChange('refactoring.enable', value => {
            if (value) {
                return this.activateRefactoring();

            } else {
                return this.deactivateRefactoring();
            }
        });

        return config.onDidChange('linting.enable', value => {
            if (value) {
                return this.activateLinting();

            } else {
                return this.deactivateLinting();
            }
        });
    },

    // /**
    //  * Registers status bar listeners.
    // */
    // registerStatusBarListeners() {
    //     const service = this.getService();
    //
    //     const indexBusyMessageMap = new Map();
    //
    //     const getBaseMessageForPath = function(path) {
    //         if (Array.isArray(path)) {
    //             path = path[0];
    //         }
    //
    //         if (path.indexOf('~') !== false) {
    //             path = path.replace('~', process.env.HOME);
    //         }
    //
    //         if (fs.lstatSync(path).isDirectory()) {
    //             return 'Indexing project - code assistance may be unavailable or incomplete';
    //         }
    //
    //         return `Indexing ${path}`;
    //     };
    //
    //     service.onDidStartIndexing(({path}) => {
    //         if (!indexBusyMessageMap.has(path)) {
    //             indexBusyMessageMap.set(path, new Array());
    //         }
    //
    //         return indexBusyMessageMap.get(path).push(this.busySignalService.reportBusy(getBaseMessageForPath(path), {
    //             waitingFor    : 'computer',
    //             revealTooltip : true
    //         }));
    //     });
    //
    //     service.onDidFinishIndexing(({path}) => {
    //         if (!indexBusyMessageMap.has(path)) { return; }
    //
    //         indexBusyMessageMap.get(path).forEach(busyMessage => busyMessage.dispose());
    //         return indexBusyMessageMap.delete(path);
    //     });
    //
    //     service.onDidFailIndexing(({path}) => {
    //         if (!indexBusyMessageMap.has(path)) { return; }
    //
    //         indexBusyMessageMap.get(path).forEach(busyMessage => busyMessage.dispose());
    //         return indexBusyMessageMap.delete(path);
    //     });
    //
    //     return service.onDidIndexingProgress(({path, percentage}) => {
    //         if (!indexBusyMessageMap.has(path)) { return; }
    //
    //         return indexBusyMessageMap.get(path).forEach(busyMessage => {
    //             return busyMessage.setTitle(getBaseMessageForPath(path) + ' (' + percentage.toFixed(2) + ' %)');
    //         });
    //     });
    // },

    /**
     * @return {Promise}
    */
    installServerIfNecessary() {
        return new Promise((resolve, reject) => {
            let notification;
            if (this.getServerManager().isInstalled()) {
                resolve();
                return;
            }

            const message =
                'The server isn\'t installed yet or is outdated. I can install the latest version for you ' +
                'automatically.\n \n' +

                'First time using this package? Please visit the package settings to set up PHP correctly first.';

            return notification = atom.notifications.addInfo('Serenata - Server Installation', {
                detail      : message,
                dismissable : true,

                buttons: [
                    {
                        text: 'Open package settings',
                        onDidClick: () => {
                            return atom.workspace.open(`atom://config/packages/${this.packageName}`);
                        }
                    },

                    {
                        text: 'Test my setup',
                        onDidClick: () => {
                            return this.testConfig();
                        }
                    },

                    {
                        text: 'Ready, install the server',
                        onDidClick: () => {
                            notification.dismiss();

                            const callback = () => {
                                const promise = this.installServer();

                                promise.catch(() => {
                                    return reject(new Error('Server installation failed'));
                                });

                                return promise.then(() => {
                                    return resolve();
                                });
                            };

                            if (this.busySignalService) {
                                return this.busySignalService.reportBusyWhile('Installing the server...', callback, {
                                    waitingFor    : 'computer',
                                    revealTooltip : false
                                });

                            } else {
                                return console.warn(
                                    'Busy signal service not loaded yet whilst installing server, not showing ' +
                                    'loading spinner'
                                );
                            }
                        }
                    },

                    {
                        text: 'No, go away',
                        onDidClick: () => {
                            notification.dismiss();
                            return reject();
                        }
                    }
                ]
            });
        });
    },

    /**
     * @return {Promise}
    */
    installServer() {
        let message =
            'The server is being downloaded and installed. To do this, Composer is automatically downloaded and ' +
            'installed into a temporary folder.\n \n' +

            'Progress and output is sent to the developer tools console, in case you\'d like to monitor it.\n \n' +

            'You will be notified once the install finishes (or fails).';

        atom.notifications.addInfo('Serenata - Installing Server', {'detail': message, dismissable: true});

        const successHandler = () => atom.notifications.addSuccess('Serenata - Server Installation Succeeded', {dismissable: true});

        const failureHandler = function() {
            message =
                'Installation of the server failed. This can happen for a variety of reasons, such as an outdated ' +
                'PHP version or missing extensions.\n \n' +

                'Logs in the developer tools will likely provide you with more information about what is wrong. You ' +
                'can open it via the menu View → Developer → Toggle Developer Tools.\n \n' +

                'Additionally, the README provides more information about requirements and troubleshooting.';

            return atom.notifications.addError('Serenata - Server Installation Failed', {detail: message, dismissable: true});
        };

        return this.getServerManager().install().then(successHandler, failureHandler);
    },

    /**
     * Checks if the php-integrator-navigation package is installed and notifies the user it is obsolete if it is.
    */
    notifyAboutRedundantNavigationPackageIfNecessary() {
        return atom.packages.onDidActivatePackage(function(packageData) {
            let notification;
            if (packageData.name !== 'php-integrator-navigation') { return; }

            const message =
                'It seems you still have the php-integrator-navigation package installed and activated. As of this ' +
                'release, it is obsolete and all its functionality is already included in the base package.\n \n' +

                'It is recommended to disable or remove it, shall I disable it for you?';

            return notification = atom.notifications.addInfo('Serenata - Navigation', {
                detail      : message,
                dismissable : true,

                buttons: [
                    {
                        text: 'Yes, nuke it',
                        onDidClick() {
                            atom.packages.disablePackage('php-integrator-navigation');
                            return notification.dismiss();
                        }
                    },

                    {
                        text: 'No, don\'t touch it',
                        onDidClick() {
                            return notification.dismiss();
                        }
                    }
                ]
            });
        });
    },

    /**
     * Checks if the php-integrator-autocomplete-plus package is installed and notifies the user it is obsolete if it
     * is.
    */
    notifyAboutRedundantAutocompletionPackageIfNecessary() {
        return atom.packages.onDidActivatePackage(function(packageData) {
            let notification;
            if (packageData.name !== 'php-integrator-autocomplete-plus') { return; }

            const message =
                'It seems you still have the php-integrator-autocomplete-plus package installed and activated. As of ' +
                'this release, it is obsolete and all its functionality is already included in the base package.\n \n' +

                'It is recommended to disable or remove it, shall I disable it for you?';

            return notification = atom.notifications.addInfo('Serenata - Autocompletion', {
                detail      : message,
                dismissable : true,

                buttons: [
                    {
                        text: 'Yes, nuke it',
                        onDidClick() {
                            atom.packages.disablePackage('php-integrator-autocomplete-plus');
                            return notification.dismiss();
                        }
                    },

                    {
                        text: 'No, don\'t touch it',
                        onDidClick() {
                            return notification.dismiss();
                        }
                    }
                ]
            });
        });
    },

    /**
     * Checks if the php-integrator-annotations package is installed and notifies the user it is obsolete if it
     * is.
    */
    notifyAboutRedundantAnnotationsPackageIfNecessary() {
        return atom.packages.onDidActivatePackage(function(packageData) {
            let notification;
            if (packageData.name !== 'php-integrator-annotations') { return; }

            const message =
                'It seems you still have the php-integrator-annotations package installed and activated. As of ' +
                'this release, it is obsolete and all its functionality is already included in the base package.\n \n' +

                'It is recommended to disable or remove it, shall I disable it for you?';

            return notification = atom.notifications.addInfo('Serenata - Autocompletion', {
                detail      : message,
                dismissable : true,

                buttons: [
                    {
                        text: 'Yes, nuke it',
                        onDidClick() {
                            atom.packages.disablePackage('php-integrator-annotations');
                            return notification.dismiss();
                        }
                    },

                    {
                        text: 'No, don\'t touch it',
                        onDidClick() {
                            return notification.dismiss();
                        }
                    }
                ]
            });
        });
    },

    /**
     * Checks if the php-integrator-refactoring package is installed and notifies the user it is obsolete if it
     * is.
    */
    notifyAboutRedundantRefactoringPackageIfNecessary() {
        return atom.packages.onDidActivatePackage(function(packageData) {
            let notification;
            if (packageData.name !== 'php-integrator-refactoring') { return; }

            const message =
                'It seems you still have the php-integrator-refactoring package installed and activated. As of ' +
                'this release, it is obsolete and all its functionality is already included in the base package.\n \n' +

                'It is recommended to disable or remove it, shall I disable it for you?';

            return notification = atom.notifications.addInfo('Serenata - Autocompletion', {
                detail      : message,
                dismissable : true,

                buttons: [
                    {
                        text: 'Yes, nuke it',
                        onDidClick() {
                            atom.packages.disablePackage('php-integrator-refactoring');
                            return notification.dismiss();
                        }
                    },

                    {
                        text: 'No, don\'t touch it',
                        onDidClick() {
                            return notification.dismiss();
                        }
                    }
                ]
            });
        });
    },

    /**
     * Shows a notification informing about support.
    */
    notifyAboutSponsoringUnobtrusively() {
        if (this.getConfiguration().get('general.doNotAskForSupport') === true) {
            return;
        }

        let projectOpens = this.getConfiguration().get('general.projectOpenCount');

        if (isNaN(projectOpens)) {
            projectOpens = 0;
        }

        ++projectOpens;

        this.getConfiguration().set('general.projectOpenCount', projectOpens);

        // Only show this after a couple of project opens to avoid bothering the user when he is still setting up and
        // getting the lay of the land (and maybe doesn't even want to continue using Serenata).
        if (projectOpens !== 10) {
            return;
        }

        const message =
            'Hello!\n \n' +

            'Hopefully Serenata is working perfectly fine for you and everything is fine and dandy. If not, the ' +
            'issue tracker is always available for feedback or issues.\n \n' +

            'Since Serenata is open source, libre as well as gratis, and is a large project to maintain, I wanted ' +
            'to take this moment to shamelessly plug a link to support its further development.\n \n' +

            'If you\'re not interested, you can just click the button below and never have to hear this again. ' +
            'If you are, great! People like you help make open source more sustainable - even a one-time symbolic ' +
            'gesture of a single cent is appreciated.';

        const me = this;

        const notification = atom.notifications.addInfo('Serenata - Support', {
            detail      : message,
            dismissable : true,

            buttons: [
                {
                    text: 'Tell me more',
                    onDidClick() {
                        const {shell} = require('electron');
                        shell.openExternal('https://serenata.gitlab.io/#support');
                    }
                },

                {
                    text: 'Remind me later',
                    onDidClick() {
                        me.getConfiguration().set('general.projectOpenCount', 0);

                        return notification.dismiss();
                    }
                },

                {
                    text: 'No, and don\'t ask me again',
                    onDidClick() {
                        me.getConfiguration().set('general.doNotAskForSupport', true);

                        return notification.dismiss();
                    }
                }
            ]
        });
    },

    /**
     * Activates the package.
    */
    activate() {
        return packageDeps.install(this.packageName, true).then(() => {
            const promise = this.installServerIfNecessary();

            promise.then(() => {
                return this.doActivate();
            });

            promise.catch(() => {
            });

            return promise;
        });
    },

    /**
     * Does the actual activation.
    */
    doActivate() {
        // TODO: Extract separate class and refactor into SerenataClient.
        this.notifyAboutRedundantNavigationPackageIfNecessary();
        this.notifyAboutRedundantAutocompletionPackageIfNecessary();
        this.notifyAboutRedundantAnnotationsPackageIfNecessary();
        this.notifyAboutRedundantRefactoringPackageIfNecessary();

        this.registerCommands();

        // TODO
        // this.registerConfigListeners();
        // this.registerStatusBarListeners();

        if (this.getConfiguration().get('annotations.enable')) {
            this.activateAnnotations();
        }

        if (this.getConfiguration().get('linting.enable')) {
            this.activateLinting();
        }

        if (this.getConfiguration().get('refactoring.enable')) {
            this.activateRefactoring();
        }

        // TODO: Will still be necessary as we need to follow the active project and its config for legacy refactoring
        // code. It is possible this can be fetched from the language client itself.
        // atom.project.onDidChangePaths(this.onChangeActiveProjectPaths);
        //
        // this.onChangeActiveProjectPaths(atom.project.getPaths());
    },

    /**
     * Called when the active project paths change.
     */
    onChangeActiveProjectPaths(projectPaths) {
        if (projectPaths.length === 0) {
            return;
        }

        this.projectManager.tryLoad(projectPaths[0]);

        this.notifyAboutSponsoringUnobtrusively();
    },

    /**
     * Activates annotations.
    */
    activateAnnotations() {
        this.annotationProviders = [];
        this.annotationProviders.push(new MethodAnnotationProvider());
        this.annotationProviders.push(new PropertyAnnotationProvider());

        return this.annotationProviders.map((provider) => {
            provider.activate(this.getService());
        });
    },

    /**
     * Deactivates annotations.
    */
    deactivateAnnotations() {
        for (const provider of this.annotationProviders) {
            provider.deactivate();
        }

        return this.annotationProviders = [];
    },

    /**
     * Activates refactoring.
    */
    activateRefactoring() {
        this.getRefactoringBuilder().setService(this.getService());
        this.getRefactoringTypeHelper().setService(this.getService());

        return this.getRefactoringProviders().map((provider) => {
            provider.activate(this.getService());
        });
    },

    /**
     * Deactivates refactoring.
    */
    deactivateRefactoring() {
        for (const provider of this.getRefactoringProviders()) {
            provider.deactivate();
        }

        return this.refactoringProviders = null;
    },

    /**
     * Activates linting.
    */
    activateLinting() {
        return this.getLinterProvider().activate(this.getService());
    },

    /**
     * Deactivates linting.
    */
    deactivateLinting() {
        return this.getLinterProvider().deactivate();
    },

    /**
     * Deactivates the package.
    */
    deactivate() {
        if (this.disposables != null) {
            this.disposables.dispose();
            this.disposables = null;
        }

        // this.deactivateLinting();
        // this.deactivateAnnotations();
        // this.deactivateRefactoring();

    },

    // /**
    //  * @param {mixed} service
    //  *
    //  * @return {Object}
    // */
    // setLinterIndieService(service) {
    //     const linter = service({
    //         name: 'Serenata'
    //     });
    //
    //     this.getDisposables().add(linter);
    //
    //     return this.getLinterProvider().setIndieLinter(linter);
    // },
    //
    // /**
    //  * @param {Object} busySignalService
    // */
    // consumeBusySignalService(busySignalService) {
    //     return this.busySignalService = busySignalService;
    // },

    /**
     * Consumes the atom/snippet service.
     *
     * @param {Object} snippetManager
    */
    setSnippetManager(snippetManager) {
        return this.getRefactoringProviders().map((provider) => {
            provider.setSnippetManager(snippetManager);
        });
    },

    /**
     * Returns a list of intention providers.
     *
     * @return {Array}
    */
    provideIntentions() {
        let intentionProviders = [];

        for (const provider of this.getRefactoringProviders()) {
            intentionProviders = intentionProviders.concat(provider.getIntentionProviders());
        }

        return intentionProviders;
    },

    /**
     * @return {Service}
    */
    getService() {
        if ((this.service == null)) {
            this.service = new Service(this.getProxy(), this.getProjectManager());
        }

        return this.service;
    },

    /**
     * @return {CompositeDisposable}
    */
    getDisposables() {
        if ((this.disposables == null)) {
            this.disposables = new CompositeDisposable();
        }

        return this.disposables;
    },

    /**
     * @return {Configuration}
    */
    getConfiguration() {
        if ((this.configuration == null)) {
            this.configuration = new AtomConfig(this.packageName);
            this.configuration.load();
        }

        return this.configuration;
    },

    /**
     * @return {Configuration}
    */
    getPhpInvoker() {
        if ((this.phpInvoker == null)) {
            this.phpInvoker = new PhpInvoker(this.getConfiguration());
        }

        return this.phpInvoker;
    },

    /**
     * @return {Proxy}
    */
    getProxy() {
        if ((this.proxy == null)) {
            this.proxy = new Proxy(this.getConfiguration(), this.getPhpInvoker());
            this.proxy.setServerPath(this.getServerManager().getServerSourcePath());
        }

        return this.proxy;
    },

    /**
     * @return {ComposerService}
    */
    getComposerService() {
        if ((this.composerService == null)) {
            this.composerService = new ComposerService(
                this.getPhpInvoker(),
                this.getConfiguration().get('storagePath') + '/server/'
            );
        }

        return this.composerService;
    },

    /**
     * @return {ServerManager}
    */
    getServerManager() {
        if ((this.serverManager == null)) {
            this.serverManager = new ServerManager(
                this.getComposerService(),
                this.serverVersionSpecification,
                this.getConfiguration().get('storagePath') + '/server/'
            );
        }

        return this.serverManager;
    },

    /**
     * @return {UseStatementHelper}
    */
    getUseStatementHelper() {
        if ((this.useStatementHelper == null)) {
            this.useStatementHelper = new UseStatementHelper(true);
        }

        return this.useStatementHelper;
    },

    // /**
    //  * @return {ProjectManager}
    // */
    // getProjectManager() {
    //     if ((this.projectManager == null)) {
    //         this.projectManager = new ProjectManager(this.getProxy());
    //     }
    //
    //     return this.projectManager;
    // },
    //
    // /**
    //  * @return {LinterProvider}
    // */
    // getLinterProvider() {
    //     if ((this.linterProvider == null)) {
    //         this.linterProvider = new LinterProvider(this.getConfiguration());
    //     }
    //
    //     return this.linterProvider;
    // },

    /**
     * @return {TypeHelper}
    */
    getRefactoringTypeHelper() {
        if ((this.typeHelper == null)) {
            this.typeHelper = new TypeHelper();
        }

        return this.typeHelper;
    },

    /**
     * @return {DocblockBuilder}
    */
    getRefactoringDocblockBuilder() {
        if ((this.docblockBuilder == null)) {
            this.docblockBuilder = new DocblockBuilder();
        }

        return this.docblockBuilder;
    },

    /**
     * @return {FunctionBuilder}
    */
    getRefactoringFunctionBuilder() {
        if ((this.functionBuilder == null)) {
            this.functionBuilder = new FunctionBuilder();
        }

        return this.functionBuilder;
    },

    /**
     * @return {ParameterParser}
    */
    getRefactoringParameterParser() {
        if ((this.parameterParser == null)) {
            this.parameterParser = new ParameterParser(this.getRefactoringTypeHelper());
        }

        return this.parameterParser;
    },

    /**
     * @return {Builder}
    */
    getRefactoringBuilder() {
        if ((this.builder == null)) {
            this.builder = new Builder(
                this.getRefactoringParameterParser(),
                this.getRefactoringDocblockBuilder(),
                this.getRefactoringFunctionBuilder(),
                this.getRefactoringTypeHelper()
            );
        }

        return this.builder;
    },

    /**
     * @return {Array}
    */
    getRefactoringProviders() {
        if ((this.refactoringProviders == null)) {
            this.refactoringProviders = [];
            this.refactoringProviders.push(new DocblockProvider(this.getRefactoringTypeHelper(), this.getRefactoringDocblockBuilder()));
            this.refactoringProviders.push(new IntroducePropertyProvider(this.getRefactoringDocblockBuilder()));
            this.refactoringProviders.push(new GetterSetterProvider(this.getRefactoringTypeHelper(), this.getRefactoringFunctionBuilder(), this.getRefactoringDocblockBuilder()));
            this.refactoringProviders.push(new ExtractMethodProvider(this.getRefactoringBuilder()));
            this.refactoringProviders.push(new ConstructorGenerationProvider(this.getRefactoringTypeHelper(), this.getRefactoringFunctionBuilder(), this.getRefactoringDocblockBuilder()));

            this.refactoringProviders.push(new OverrideMethodProvider(this.getRefactoringDocblockBuilder(), this.getRefactoringFunctionBuilder()));
            this.refactoringProviders.push(new StubAbstractMethodProvider(this.getRefactoringDocblockBuilder(), this.getRefactoringFunctionBuilder()));
            this.refactoringProviders.push(new StubInterfaceMethodProvider(this.getRefactoringDocblockBuilder(), this.getRefactoringFunctionBuilder()));
        }

        return this.refactoringProviders;
    },
};

const SerenataClient = require('./SerenataClient');

const client = new SerenataClient(functions.getProxy());

module.exports = client;
