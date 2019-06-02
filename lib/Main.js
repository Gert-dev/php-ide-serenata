/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
const {CompositeDisposable} = require('atom');

const fs = require('fs');
const process = require('process');

const Proxy = require('./Proxy');
const AtomConfig = require('./AtomConfig');
const PhpInvoker = require('./PhpInvoker');
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
    serverVersionSpecification: '5.0.0-RC',

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
     * Registers any commands that are available to the user.
    */
    registerCommands() {
        return atom.commands.add('atom-workspace', { 'php-ide-serenata:sort-use-statements': () => {
            const activeTextEditor = atom.workspace.getActiveTextEditor();

            if ((activeTextEditor == null)) { return; }

            return this.getUseStatementHelper().sortUseStatements(activeTextEditor);
        }});
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
    functions.getServerManager(),
    functions.getPhpInvoker(),
    functions.getConfiguration()
);

module.exports = client;
