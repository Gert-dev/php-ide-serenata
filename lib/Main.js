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
const AtomConfig = require('./AtomConfig');
const PhpInvoker = require('./PhpInvoker');
const ConfigTester = require('./ConfigTester');
const ServerManager = require('./ServerManager');
const ProjectManager = require('./ProjectManager');
const ComposerService = require('./ComposerService');
const UseStatementHelper = require('./UseStatementHelper');

const functions = {
    /**
     * The version of the server to download (version specification string).
     *
     * @var {String}
    */
    serverVersionSpecification: '4.3.1',

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
     * Tests the user's configuration.
    */
    testConfig() {
        const configTester = new ConfigTester(this.getPhpInvoker());

        atom.notifications.addInfo('Serenata - Testing Configuration', {
            dismissable: true,
            detail: 'Now testing your configuration... \n \n' +

                    `If you've selected Docker, this may take a while the first time as the Docker image has to be ` +
                    `fetched first.`
        });

        const callback = () => {
            return configTester.test().then(wasSuccessful => {
                if (!wasSuccessful) {
                    const errorMessage =
                        `PHP is not configured correctly. Please visit the settings screen to correct this error. ` +
                        `If you are using a relative path to PHP, make sure it is in your PATH variable.`;

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

    /**
     * @return {ProjectManager}
     */
    getProjectManager() {
        if (this.projectManager == null) {
            this.projectManager = new ProjectManager(this.getProxy());
        }

        return this.projectManager;
    },
};

const SerenataClient = require('./SerenataClient');

const client = new SerenataClient(
    functions.getProxy(),
    functions.getProjectManager(),
    functions.getPhpInvoker(),
    functions.getConfiguration()
);

module.exports = client;
