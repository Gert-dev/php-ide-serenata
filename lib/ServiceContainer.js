'use strict';

module.exports =

/**
 * Container that provides instances of application services.
 */
class ServiceContainer
{
    constructor() {
        this.packageName = 'php-ide-serenata';

        this.configuration = null;
        this.phpInvoker = null;
        this.proxy = null;
        this.composerService = null;
        this.serverManager = null;
        this.useStatementHelper = null;
        this.projectManager = null;
        this.codeLensManager = null;
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

    getServerManager() {
        if ((this.serverManager === null)) {
            const ServerManager = require('./ServerManager');
            this.serverManager = new ServerManager(
                this.getPhpInvoker(),
                this.getConfiguration().get('storagePath') + '/'
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
            this.projectManager = new ProjectManager(this.getProxy(), this.getConfiguration());
        }

        return this.projectManager;
    }

    getCodeLensManager() {
        if (this.codeLensManager === null) {
            const CodeLensManager = require('./CodeLensManager');
            this.codeLensManager = new CodeLensManager();
        }

        return this.codeLensManager;
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
