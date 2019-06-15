'use strict';

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
            const AtomConfig = require('./AtomConfig');
            this.configuration = new AtomConfig(this.packageName);
            this.configuration.load();
        }

        return this.configuration;
    }

    getPhpInvoker() {
        if ((this.phpInvoker === null)) {
            const PhpInvoker = require('./PhpInvoker');
            this.phpInvoker = new PhpInvoker(this.getConfiguration());
        }

        return this.phpInvoker;
    }

    getProxy() {
        if ((this.proxy === null)) {
            const Proxy = require('./Proxy');
            this.proxy = new Proxy(this.getConfiguration(), this.getPhpInvoker());
            this.proxy.setServerPath(this.getServerManager().getServerSourcePath());
        }

        return this.proxy;
    }

    getComposerService() {
        if ((this.composerService === null)) {
            const ComposerService = require('./ComposerService');
            this.composerService = new ComposerService(
                this.getPhpInvoker(),
                this.getConfiguration().get('storagePath') + '/server/'
            );
        }

        return this.composerService;
    }

    getServerManager() {
        if ((this.serverManager === null)) {
            const ServerManager = require('./ServerManager');
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
            const UseStatementHelper = require('./UseStatementHelper');
            this.useStatementHelper = new UseStatementHelper(true);
        }

        return this.useStatementHelper;
    }

    getProjectManager() {
        if (this.projectManager === null) {
            const ProjectManager = require('./ProjectManager');
            this.projectManager = new ProjectManager(this.getProxy());
        }

        return this.projectManager;
    }

    getSerenataClient() {
        if (this.serenataClient === null) {
            const SerenataClient = require('./SerenataClient');
            this.serenataClient = new SerenataClient(
                this,
                this.packageName
            );
        }

        return this.serenataClient;
    }
};
