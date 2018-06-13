/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
const {CompositeDisposable} = require('atom');

const {Emitter} = require('event-kit');

const packageDeps = require('atom-package-deps');

const fs = require('fs');
const process = require('process');

const Proxy = require('./Proxy');
const Service = require('./Service');
const AtomConfig = require('./AtomConfig');
const PhpInvoker = require('./PhpInvoker');
const CoreManager = require('./CoreManager');
const ConfigTester = require('./ConfigTester');
const ProjectManager = require('./ProjectManager');
const LinterProvider = require('./LinterProvider');
const ComposerService = require('./ComposerService');
const DatatipProvider = require('./DatatipProvider');
const IndexingMediator = require('./IndexingMediator');
const UseStatementHelper = require('./UseStatementHelper');
const SignatureHelpProvider = require('./SignatureHelpProvider');
const GotoDefinitionProvider = require('./GotoDefinitionProvider');
const AutocompletionProvider = require('./AutocompletionProvider');

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

module.exports = {
    /**
     * Configuration settings.
    */
    config: {
        core: {
            type: 'object',
            order: 1,
            properties: {
                phpExecutionType: {
                    title       : 'PHP execution type',
                    description : `How to start PHP, which is needed to start the core in turn. \n \n \
\
'Use PHP on the host' uses a PHP binary installed on your local machine. 'Use PHP \
container via Docker' requires Docker and uses a PHP container to start the server \
with. Using PolicyKit allows Linux users that are not part of the Docker group to \
enter their password via an authentication dialog to temporarily escalate privileges \
so the Docker daemon can be invoked once to start the server. \n \n \
\
You can use the php-ide-serenata:test-configuration command to test your setup. \
\n \n \
\
Requires a restart after changing. \n \n`,
                    type        : 'string',
                    default     : 'host',
                    order       : 1,
                    enum        : [
                        {
                            value       : 'host',
                            description : 'Use PHP on the host'
                        },

                        {
                            value       : 'docker',
                            description : 'Use a PHP container via Docker (experimental)'
                        },

                        {
                            value       : 'docker-polkit',
                            description : 'Use a PHP container via Docker, using PolicyKit for privilege escalation ' +
                                          ' (experimental, Linux only)'
                        }
                    ]
                },

                phpCommand: {
                    title       : 'PHP command',
                    description : `The path to your PHP binary (e.g. /usr/bin/php, php, ...). Only applies if you\'ve \
selected "Use PHP on the host" above. \n \n \
\
Requires a restart after changing.`,
                    type        : 'string',
                    default     : 'php',
                    order       : 2
                },

                memoryLimit: {
                    title       : 'Memory limit (in MB)',
                    description : `The memory limit to set for the PHP process. The PHP process uses the available \
memory for in-memory caching as well, so it should not be too low. On the other hand, \
it shouldn\'t be growing very large, so setting it to -1 is probably a bad idea as \
an infinite loop bug might take down your system. The default should suit most \
projects, from small to large. \n \n \
Requires a restart after changing.`,
                    type        : 'integer',
                    default     : 2048,
                    order       : 3
                },

                additionalDockerVolumes: {
                    title       : 'Additional Docker volumes',
                    description : `Additional paths to mount as Docker volumes. Only applies when using Docker to run \
the core. Separate these using comma\'s, where each item follows the format \
"src:dest" as the Docker -v flag uses. \n \n \
Requires a restart after changing.`,
                    type        : 'array',
                    default     : [],
                    order       : 4,
                    items       : {
                        type : 'string'
                    }
                }
            }
        },

        general: {
            type: 'object',
            order: 2,
            properties: {
                indexContinuously: {
                    title       : 'Index continuously',
                    description : `If enabled, indexing will happen continuously and automatically whenever the editor \
is modified. If disabled, indexing will only happen on save. This also influences \
linting, which happens automatically after indexing completes. In other words, if \
you would like linting to happen on save, you can disable this option.`,
                    type        : 'boolean',
                    default     : true,
                    order       : 1
                },

                additionalIndexingDelay: {
                    title       : 'Additional delay before reindexing (in ms)',
                    description : `Only applies when indexing continously, which happens after a fixed time (about 300 \
ms at the time of writing and managed by Atom). If this is too fast for you, you can \
introduce an additional delay here. Fewer indexes means less load as tasks such as \
linting are invoked less often. However, it also means that it will take longer for \
changes to code to be reflected in, for example, autocompletion.`,
                    type        : 'integer',
                    default     : 500,
                    order       : 2
                }
            }
        },

        datatips: {
            type: 'object',
            order: 3,
            properties: {
                enable: {
                    title       : 'Enable',
                    description : `When enabled, documentation for various structural elements can be displayed in a \
datatip (tooltip).`,
                    type        : 'boolean',
                    default     : true,
                    order       : 1
                }
            }
        },

        signatureHelp: {
            type: 'object',
            order: 4,
            properties: {
                enable: {
                    title       : 'Enable',
                    description : `When enabled, signature help (call tips) will be displayed when the keyboard cursor \
is inside a function, method or constructor call.`,
                    type        : 'boolean',
                    default     : true,
                    order       : 1
                }
            }
        },

        gotoDefinition: {
            type: 'object',
            order: 5,
            properties: {
                enable: {
                    title       : 'Enable',
                    description : 'When enabled, code navigation will be activated via the hyperclick package.',
                    type        : 'boolean',
                    default     : true,
                    order       : 1
                }
            }
        },

        autocompletion: {
            type: 'object',
            order: 6,
            properties: {
                enable: {
                    title       : 'Enable',
                    description : 'When enabled, autocompletion will be activated via the autocomplete-plus package.',
                    type        : 'boolean',
                    default     : true,
                    order       : 1
                }
            }
        },

        annotations: {
            type: 'object',
            order: 7,
            properties: {
                enable: {
                    title       : 'Enable',
                    description : `When enabled, annotations will be shown in the gutter with more information \
regarding member overrides and interface implementations.`,
                    type        : 'boolean',
                    default     : true,
                    order       : 1
                }
            }
        },

        refactoring: {
            type: 'object',
            order: 8,
            properties: {
                enable: {
                    title       : 'Enable',
                    description : 'When enabled, refactoring actions will be available via the intentions package.',
                    type        : 'boolean',
                    default     : true,
                    order       : 1
                }
            }
        },

        linting: {
            type: 'object',
            order: 9,
            properties: {
                enable: {
                    title       : 'Enable',
                    description : 'When enabled, linting will show problems and warnings picked up in your code.',
                    type        : 'boolean',
                    default     : true,
                    order       : 1
                },

                showUnknownClasses: {
                    title       : 'Show unknown classes',
                    description : 'Highlights class names that could not be found. This will also work for docblocks.',
                    type        : 'boolean',
                    default     : true,
                    order       : 2
                },

                showUnknownGlobalFunctions: {
                    title       : 'Show unknown (global) functions',
                    description : 'Highlights (global) functions that could not be found.',
                    type        : 'boolean',
                    default     : true,
                    order       : 3
                },

                showUnknownGlobalConstants: {
                    title       : 'Show unknown (global) constants',
                    description : 'Highlights (global) constants that could not be found.',
                    type        : 'boolean',
                    default     : true,
                    order       : 4
                },

                showUnusedUseStatements: {
                    title       : 'Show unused use statements',
                    description : 'Highlights use statements that don\'t seem to be used anywhere.',
                    type        : 'boolean',
                    default     : true,
                    order       : 5
                },

                showMissingDocs: {
                    title       : 'Show missing documentation',
                    description : 'Warns about structural elements that are missing documentation.',
                    type        : 'boolean',
                    default     : true,
                    order       : 6
                },

                validateDocblockCorrectness: {
                    title       : 'Validate docblock correctness',
                    description : `\
Analyzes the correctness of docblocks of various structural elements and will show various
problems such as undocumented parameters, mismatched parameter and deprecated tags.\
`,
                    type        : 'boolean',
                    default     : true,
                    order       : 7
                },

                showUnknownMembers: {
                    title       : 'Show unknown members (experimental)',
                    description : `\
Highlights use of unknown members. Note that this can be a large strain on performance and is
experimental (expect false positives, especially inside conditionals).\
`,
                    type        : 'boolean',
                    default     : false,
                    order       : 8
                }
            }
        }
    },

    /**
     * The version of the core to download (version specification string).
     *
     * @var {String}
    */
    coreVersionSpecification: '4.1.0',

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
     * @var {IndexingMediator}
    */
    indexingMediator: null,

    /**
     * A list of disposables to dispose when the package deactivates.
     *
     * @var {Object|null}
    */
    disposables: null,

    /**
     * The currently active project, if any.
     *
     * @var {Object|null}
    */
    activeProject: null,

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
     * The service instance from the project-manager package.
     *
     * @var {Object|null}
    */
    projectManagerService: null,

    /**
     * @var {Object|null}
    */
    editorTimeoutMap: null,

    /**
     * @var {Object|null}
    */
    datatipProvider: null,

    /**
     * @var {Object|null}
    */
    signatureHelpProvider: null,

    /**
     * @var {Object|null}
    */
    gotoDefinitionProvider: null,

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
            let errorMessage;
            if ((this.projectManagerService == null)) {
                errorMessage = `\
The project manager service was not found. Did you perhaps forget to install the project-manager
package or another package able to provide it?\
`;

                atom.notifications.addError('Incorrect setup!', {'detail': errorMessage});
                return;
            }

            if ((this.activeProject == null)) {
                errorMessage = `\
No project is currently active. Please save and activate one before attempting to set it up.
You can do it via the menu Packages → Project Manager → Save Project.\
`;

                atom.notifications.addError('Incorrect setup!', {'detail': errorMessage});
                return;
            }

            const project = this.activeProject;

            let newProperties = null;

            try {
                newProperties = this.projectManager.setUpProject(project);

                if ((newProperties == null)) {
                    throw new Error('No properties returned, this should never happen!');
                }

            } catch (error) {
                atom.notifications.addError('Error!', {
                    'detail' : error.message
                });

                return;
            }

            this.projectManagerService.saveProject(newProperties);

            atom.notifications.addSuccess('Success', {
                'detail' : 'Your current project has been set up as PHP project. Indexing will now commence.'
            });

            this.projectManager.load(project);

            return this.performInitialFullIndexForCurrentProject();
        }
        }
        );

        atom.commands.add('atom-workspace', { 'php-ide-serenata:index-project': () => {
            if (!this.projectManager.hasActiveProject()) { return; }

            return this.projectManager.attemptCurrentProjectIndex();
        }
        }
        );

        atom.commands.add('atom-workspace', { 'php-ide-serenata:force-index-project': () => {
            if (!this.projectManager.hasActiveProject()) { return; }

            return this.performInitialFullIndexForCurrentProject();
        }
        }
        );

        atom.commands.add('atom-workspace', { 'php-ide-serenata:test-configuration': () => {
            return this.testConfig();
        }
        }
        );

        return atom.commands.add('atom-workspace', { 'php-ide-serenata:sort-use-statements': () => {
            const activeTextEditor = atom.workspace.getActiveTextEditor();

            if ((activeTextEditor == null)) { return; }

            return this.getUseStatementHelper().sortUseStatements(activeTextEditor);
        }
        }
        );
    },

    /**
     * Performs the "initial" index for a new project by initializing it and then performing a project index.
     *
     * @return {Promise}
    */
    performInitialFullIndexForCurrentProject() {
        const successHandler = () => {
            return this.projectManager.attemptCurrentProjectIndex();
        };

        const failureHandler = reason => {
            console.error(reason);

            return atom.notifications.addError('Error!', {
                'detail' : 'The project could not be properly initialized!'
            });
        };

        return this.projectManager.initializeCurrentProject().then(successHandler, failureHandler);
    },

    /**
     * Registers listeners for configuration changes.
    */
    registerConfigListeners() {
        const config = this.getConfiguration();

        config.onDidChange('datatips.enable', value => {
            if (value) {
                return this.activateDatatips();

            } else {
                return this.deactivateDatatips();
            }
        });

        config.onDidChange('signatureHelp.enable', value => {
            if (value) {
                return this.activateSignatureHelp();

            } else {
                return this.deactivateSignatureHelp();
            }
        });

        config.onDidChange('gotoDefintion.enable', value => {
            if (value) {
                return this.activateGotoDefinition();

            } else {
                return this.deactivateGotoDefinition();
            }
        });

        config.onDidChange('autocompletion.enable', value => {
            if (value) {
                return this.activateAutocompletion();

            } else {
                return this.deactivateAutocompletion();
            }
        });

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

    /**
     * Registers status bar listeners.
    */
    registerStatusBarListeners() {
        const service = this.getService();

        const indexBusyMessageMap = new Map();

        const getBaseMessageForPath = function(path) {
            if (Array.isArray(path)) {
                path = path[0];
            }

            if (path.indexOf('~') !== false) {
                path = path.replace('~', process.env.HOME);
            }

            if (fs.lstatSync(path).isDirectory()) {
                return 'Indexing project - code assistance may be unavailable or incomplete';
            }

            return `Indexing ${path}`;
        };

        service.onDidStartIndexing(({path}) => {
            if (!indexBusyMessageMap.has(path)) {
                indexBusyMessageMap.set(path, new Array());
            }

            return indexBusyMessageMap.get(path).push(this.busySignalService.reportBusy(getBaseMessageForPath(path), {
                waitingFor    : 'computer',
                revealTooltip : true
            }));
        });

        service.onDidFinishIndexing(({path}) => {
            if (!indexBusyMessageMap.has(path)) { return; }

            indexBusyMessageMap.get(path).forEach(busyMessage => busyMessage.dispose());
            return indexBusyMessageMap.delete(path);
        });

        service.onDidFailIndexing(({path}) => {
            if (!indexBusyMessageMap.has(path)) { return; }

            indexBusyMessageMap.get(path).forEach(busyMessage => busyMessage.dispose());
            return indexBusyMessageMap.delete(path);
        });

        return service.onDidIndexingProgress(({path, percentage}) => {
            if (!indexBusyMessageMap.has(path)) { return; }

            return indexBusyMessageMap.get(path).forEach(busyMessage => {
                return busyMessage.setTitle(getBaseMessageForPath(path) + ' (' + percentage.toFixed(2) + ' %)');
            });
        });
    },

    /**
     * @return {Promise}
    */
    installCoreIfNecessary() {
        return new Promise((resolve, reject) => {
            let notification;
            if (this.getCoreManager().isInstalled()) {
                resolve();
                return;
            }

            const message =
                'The core isn\'t installed yet or is outdated. I can install the latest version for you ' +
                'automatically.\n \n' +

                'First time using this package? Please visit the package settings to set up PHP correctly first.';

            return notification = atom.notifications.addInfo('Serenata - Core Installation', {
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
                        text: 'Ready, install the core',
                        onDidClick: () => {
                            notification.dismiss();

                            const callback = () => {
                                const promise = this.installCore();

                                promise.catch(() => {
                                    return reject(new Error('Core installation failed'));
                                });

                                return promise.then(() => {
                                    return resolve();
                                });
                            };

                            if (this.busySignalService) {
                                return this.busySignalService.reportBusyWhile('Installing the core...', callback, {
                                    waitingFor    : 'computer',
                                    revealTooltip : false
                                });

                            } else {
                                return console.warn(
                                    'Busy signal service not loaded yet whilst installing core, not showing ' +
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
    installCore() {
        let message =
            'The core is being downloaded and installed. To do this, Composer is automatically downloaded and ' +
            'installed into a temporary folder.\n \n' +

            'Progress and output is sent to the developer tools console, in case you\'d like to monitor it.\n \n' +

            'You will be notified once the install finishes (or fails).';

        atom.notifications.addInfo('Serenata - Installing Core', {'detail': message, dismissable: true});

        const successHandler = () => atom.notifications.addSuccess('Serenata - Core Installation Succeeded', {dismissable: true});

        const failureHandler = function() {
            message =
                'Installation of the core failed. This can happen for a variety of reasons, such as an outdated PHP ' +
                'version or missing extensions.\n \n' +

                'Logs in the developer tools will likely provide you with more information about what is wrong. You ' +
                'can open it via the menu View → Developer → Toggle Developer Tools.\n \n' +

                'Additionally, the README provides more information about requirements and troubleshooting.';

            return atom.notifications.addError('Serenata - Core Installation Failed', {detail: message, dismissable: true});
        };

        return this.getCoreManager().install().then(successHandler, failureHandler);
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
     * Activates the package.
    */
    activate() {
        return packageDeps.install(this.packageName, true).then(() => {
            const promise = this.installCoreIfNecessary();

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
        this.notifyAboutRedundantNavigationPackageIfNecessary();
        this.notifyAboutRedundantAutocompletionPackageIfNecessary();
        this.notifyAboutRedundantAnnotationsPackageIfNecessary();
        this.notifyAboutRedundantRefactoringPackageIfNecessary();

        this.registerCommands();
        this.registerConfigListeners();
        this.registerStatusBarListeners();

        this.editorTimeoutMap = {};

        this.registerAtomListeners();

        if (this.getConfiguration().get('datatips.enable')) {
            this.activateDatatips();
        }

        if (this.getConfiguration().get('signatureHelp.enable')) {
            this.activateSignatureHelp();
        }

        if (this.getConfiguration().get('annotations.enable')) {
            this.activateAnnotations();
        }

        if (this.getConfiguration().get('linting.enable')) {
            this.activateLinting();
        }

        if (this.getConfiguration().get('refactoring.enable')) {
            this.activateRefactoring();
        }

        if (this.getConfiguration().get('gotoDefinition.enable')) {
            this.activateGotoDefinition();
        }

        if (this.getConfiguration().get('autocompletion.enable')) {
            this.activateAutocompletion();
        }

        this.getProxy().setIsActive(true);

        // This fixes the corner case where the core is still installing, the project manager service has already
        // loaded and the project is already active. At that point, the index that resulted from it silently
        // failed because the proxy (and core) weren't active yet. This in turn causes the project to not
        // automatically start indexing, which is especially relevant if a core update requires a reindex.
        if (this.activeProject != null) {
            return this.changeActiveProject(this.activeProject);
        }
    },

    /**
     * Registers listeners for events from Atom's API.
    */
    registerAtomListeners() {
        return this.getDisposables().add(atom.workspace.observeTextEditors(editor => {
            return this.registerTextEditorListeners(editor);
        })
        );
    },

    /**
     * Activates the datatip provider.
    */
    activateDatatips() {
        return this.getDatatipProvider().activate(this.getService());
    },

    /**
     * Deactivates the datatip provider.
    */
    deactivateDatatips() {
        return this.getDatatipProvider().deactivate();
    },

    /**
     * Activates the signature help provider.
    */
    activateSignatureHelp() {
        return this.getSignatureHelpProvider().activate(this.getService());
    },

    /**
     * Deactivates the signature help provider.
    */
    deactivateSignatureHelp() {
        return this.getSignatureHelpProvider().deactivate();
    },

    /**
     * Activates the goto definition provider.
    */
    activateGotoDefinition() {
        return this.getGotoDefinitionProvider().activate(this.getService());
    },

    /**
     * Deactivates the goto definition provider.
    */
    deactivateGotoDefinition() {
        return this.getGotoDefinitionProvider().deactivate();
    },

    /**
     * Activates the goto definition provider.
    */
    activateAutocompletion() {
        return this.getAutocompletionProvider().activate(this.getService());
    },

    /**
     * Deactivates the goto definition provider.
    */
    deactivateAutocompletion() {
        return this.getAutocompletionProvider().deactivate();
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
     * @param {TextEditor} editor
    */
    registerTextEditorListeners(editor) {
        if (this.getConfiguration().get('general.indexContinuously') === true) {
            // The default onDidStopChanging timeout is 300 milliseconds. As this is notcurrently configurable (and would
            // also impact other packages), we install our own timeout on top of the existing one. This is useful for users
            // that don't type particularly fast or are on slower machines and will prevent constant indexing from happening.
            this.getDisposables().add(editor.onDidStopChanging(() => {
                const path = editor.getPath();

                const additionalIndexingDelay = this.getConfiguration().get('general.additionalIndexingDelay');

                return this.editorTimeoutMap[path] = setTimeout(( () => {
                    this.onEditorDidStopChanging(editor);
                    return this.editorTimeoutMap[path] = null;
                }
                ), additionalIndexingDelay);
            })
            );

            return this.getDisposables().add(editor.onDidChange(() => {
                const path = editor.getPath();

                if (this.editorTimeoutMap[path] != null) {
                    clearTimeout(this.editorTimeoutMap[path]);
                    return this.editorTimeoutMap[path] = null;
                }
            })
            );

        } else {
            return this.getDisposables().add(editor.onDidSave(this.onEditorDidStopChanging.bind(this, editor)));
        }
    },

    /**
     * Invoked when an editor stops changing.
     *
     * @param {TextEditor} editor
    */
    onEditorDidStopChanging(editor) {
        if (!/text.html.php$/.test(editor.getGrammar().scopeName)) { return; }

        const fileName = editor.getPath();

        if (!fileName) { return; }

        const projectManager = this.getProjectManager();

        if (projectManager.hasActiveProject() && projectManager.isFilePartOfCurrentProject(fileName)) {
            return projectManager.attemptCurrentProjectFileIndex(fileName, editor.getBuffer().getText());
        }
    },

    /**
     * Deactivates the package.
    */
    deactivate() {
        if (this.disposables != null) {
            this.disposables.dispose();
            this.disposables = null;
        }

        this.deactivateLinting();
        this.deactivateDatatips();
        this.deactivateSignatureHelp();
        this.deactivateAnnotations();
        this.deactivateRefactoring();

        this.getProxy().exit();

    },

    /**
     * @param {mixed} service
     *
     * @return {Object}
    */
    setLinterIndieService(service) {
        const linter = service({
            name: 'Serenata'
        });

        this.getDisposables().add(linter);

        return this.getLinterProvider().setIndieLinter(linter);
    },

    /**
     * Sets the project manager service.
     *
     * @param {Object} service
    */
    setProjectManagerService(service) {
        this.projectManagerService = service;

        // NOTE: This method is actually called whenever the project changes as well.
        return service.getProject(project => {
            return this.onProjectChanged(project);
        });
    },

    /**
     * @param {Object} project
    */
    onProjectChanged(project) {
        return this.changeActiveProject(project);
    },

    /**
     * @param {Object} project
    */
    changeActiveProject(project) {
        this.activeProject = project;

        if ((project == null)) { return; }

        const projectManager = this.getProjectManager();
        projectManager.load(project);

        if (!projectManager.hasActiveProject()) { return; }

        const successHandler = isProjectInGoodShape => {
            // NOTE: If the index is manually deleted, testing will return false so the project is reinitialized.
            // This is needed to index built-in items as they are not automatically indexed by indexing the project.
            if (!isProjectInGoodShape) {
                return this.performInitialFullIndexForCurrentProject();

            } else {
                return this.projectManager.attemptCurrentProjectIndex();
            }
        };

        const failureHandler = function() {};
        // Ignore

        this.proxy.test().then(successHandler, failureHandler);

    },

    /**
     * Retrieves autocompletion providers for the autocompletion package.
     *
     * @return {Array}
    */
    getAutocompletionProviderServices() {
        return [this.getAutocompletionProvider()];
    },

    /**
     * @param {Object} signatureHelpService
    */
    consumeSignatureHelpService(signatureHelpService) {
        return signatureHelpService(this.getSignatureHelpProvider());
    },

    /**
     * @param {Object} busySignalService
    */
    consumeBusySignalService(busySignalService) {
        return this.busySignalService = busySignalService;
    },

    /**
     * @param {Object} datatipService
    */
    consumeDatatipService(datatipService) {
        return datatipService.addProvider(this.getDatatipProvider());
    },

    /**
     * Returns the hyperclick provider.
     *
     * @return {Object}
    */
    getHyperclickProvider() {
        return this.getGotoDefinitionProvider();
    },

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
            this.service = new Service(
                this.getProxy(),
                this.getProjectManager(),
                this.getIndexingMediator()
            );
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
            this.proxy.setCorePath(this.getCoreManager().getCoreSourcePath());
        }

        return this.proxy;
    },

    /**
     * @return {Emitter}
    */
    getEmitter() {
        if ((this.emitter == null)) {
            this.emitter = new Emitter();
        }

        return this.emitter;
    },

    /**
     * @return {ComposerService}
    */
    getComposerService() {
        if ((this.composerService == null)) {
            this.composerService = new ComposerService(
                this.getPhpInvoker(),
                this.getConfiguration().get('storagePath') + '/core/'
            );
        }

        return this.composerService;
    },

    /**
     * @return {CoreManager}
    */
    getCoreManager() {
        if ((this.coreManager == null)) {
            this.coreManager = new CoreManager(
                this.getComposerService(),
                this.coreVersionSpecification,
                this.getConfiguration().get('storagePath') + '/core/'
            );
        }

        return this.coreManager;
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

    /**
     * @return {IndexingMediator}
    */
    getIndexingMediator() {
        if ((this.indexingMediator == null)) {
            this.indexingMediator = new IndexingMediator(this.getProxy(), this.getEmitter());
        }

        return this.indexingMediator;
    },

    /**
     * @return {ProjectManager}
    */
    getProjectManager() {
        if ((this.projectManager == null)) {
            this.projectManager = new ProjectManager(this.getProxy(), this.getIndexingMediator());
        }

        return this.projectManager;
    },

    /**
     * @return {DatatipProvider}
    */
    getDatatipProvider() {
        if ((this.datatipProvider == null)) {
            this.datatipProvider = new DatatipProvider();
        }

        return this.datatipProvider;
    },

    /**
     * @return {SignatureHelpProvider}
    */
    getSignatureHelpProvider() {
        if ((this.signatureHelpProvider == null)) {
            this.signatureHelpProvider = new SignatureHelpProvider();
        }

        return this.signatureHelpProvider;
    },

    /**
     * @return {GotoDefinitionProvider}
    */
    getGotoDefinitionProvider() {
        if ((this.gotoDefinitionProvider == null)) {
            this.gotoDefinitionProvider = new GotoDefinitionProvider(this.getPhpInvoker());
        }

        return this.gotoDefinitionProvider;
    },

    /**
     * @return {LinterProvider}
    */
    getLinterProvider() {
        if ((this.linterProvider == null)) {
            this.linterProvider = new LinterProvider(this.getConfiguration());
        }

        return this.linterProvider;
    },

    /**
     * @return {AutocompletionProvider}
    */
    getAutocompletionProvider() {
        if ((this.autocompletionProvider == null)) {
            this.autocompletionProvider = new AutocompletionProvider();
        }

        return this.autocompletionProvider;
    },

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
    }
};
