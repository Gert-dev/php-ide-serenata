'use strict';

const Proxy = require('./Proxy');
const AtomConfig = require('./AtomConfig');
const PhpInvoker = require('./PhpInvoker');
const ServerManager = require('./ServerManager');
const ProjectManager = require('./ProjectManager');
const SerenataClient = require('./SerenataClient');
const ComposerService = require('./ComposerService');
const UseStatementHelper = require('./UseStatementHelper');

module.exports =

/**
 * Container that provides instances of application services.
 */
class ServiceContainer
{
    constructor() {
        this.packageName = 'php-ide-serenata';
        this.serverVersionSpecification = '5.0.0-RC';

        this.configuration = null;
        this.phpInvoker = null;
        this.proxy = null;
        this.composerService = null;
        this.serverManager = null;
        this.useStatementHelper = null;
        this.projectManager = null;
        this.serenataClient = null;
    }

    getConfiguration() {
        if ((this.configuration === null)) {
            this.configuration = new AtomConfig(this.packageName);
            this.configuration.load();
        }

        return this.configuration;
    }

    getPhpInvoker() {
        if ((this.phpInvoker === null)) {
            this.phpInvoker = new PhpInvoker(this.getConfiguration());
        }

        return this.phpInvoker;
    }

    getProxy() {
        if ((this.proxy === null)) {
            this.proxy = new Proxy(this.getConfiguration(), this.getPhpInvoker());
            this.proxy.setServerPath(this.getServerManager().getServerSourcePath());
        }

        return this.proxy;
    }

    getComposerService() {
        if ((this.composerService === null)) {
            this.composerService = new ComposerService(
                this.getPhpInvoker(),
                this.getConfiguration().get('storagePath') + '/server/'
            );
        }

        return this.composerService;
    }

    getServerManager() {
        if ((this.serverManager === null)) {
            this.serverManager = new ServerManager(
                this.getComposerService(),
                this.serverVersionSpecification,
                this.getConfiguration().get('storagePath') + '/server/'
            );
        }

        return this.serverManager;
    }

    getUseStatementHelper() {
        if ((this.useStatementHelper === null)) {
            this.useStatementHelper = new UseStatementHelper(true);
        }

        return this.useStatementHelper;
    }

    getProjectManager() {
        if (this.projectManager === null) {
            this.projectManager = new ProjectManager(this.getProxy());
        }

        return this.projectManager;
    }

    getSerenataClient() {
        if (this.serenataClient === null) {
            this.serenataClient = new SerenataClient(
                this.getProxy(),
                this.getProjectManager(),
                this.getServerManager(),
                this.getUseStatementHelper(),
                this.getPhpInvoker(),
                this.getConfiguration(),
                this.packageName
            );
        }

        return this.serenataClient;
    }
};
