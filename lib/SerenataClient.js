/* global atom */

'use strict';

const {AutoLanguageClient} = require('atom-languageclient');

module.exports =

class SerenataClient extends AutoLanguageClient
{
    constructor(container, packageName) {
        super();

        this.hasBootstrappedGlobally = false;
        this.container = container;
        this.connection = null;
        this.refactoringProviders = null;
        this.packageName = packageName;
        this.indexingProgressBusyMessage = null;
    }

    getGrammarScopes() {
        return ['text.html.php'];
    }

    getLanguageName() {
        return 'PHP';
    }

    getServerName() {
        return 'Serenata';
    }

    getConnectionType() {
        return 'socket';
    }

    activate() {
        super.activate();

        this.registerCommands();
    }

    deactivate() {
        this.deactivateRefactoring();

        return super.deactivate();
    }

    async installServerIfNecessary() {
        return new Promise((resolve, reject) => {
            if (this.container.getServerManager().isInstalled()) {
                resolve();
                return;
            }

            const message =
                'Serenata isn\'t installed yet or outdated, install or update to the latest version?\n \n' +

                'First time using this package? Please visit the package settings to set up PHP correctly first.';

            let notification = atom.notifications.addInfo('Serenata - Server Installation', {
                description : message,
                dismissable : true,

                buttons: [
                    {
                        text: 'Open settings',
                        onDidClick: () => {
                            atom.workspace.open(`atom://config/packages/${this.packageName}`);
                        }
                    },

                    {
                        text: 'Test my setup',
                        onDidClick: () => {
                            this.testConfig();
                        }
                    },

                    {
                        text: 'Yes',
                        onDidClick: () => {
                            const callback = async () => {
                                if (!await this.installServer()) {
                                    reject(new Error('Server installation failed'));
                                    return;
                                }

                                notification.dismiss();
                                resolve();
                            };

                            if (this.busySignalService) {
                                this.busySignalService.reportBusyWhile('Installing the server...', callback, {
                                    waitingFor    : 'computer',
                                    revealTooltip : false
                                });
                            } else {
                                console.warn(
                                    'Busy signal service not loaded yet whilst installing server, not showing ' +
                                    'loading spinner'
                                );
                            }
                        }
                    },

                    {
                        text: 'No, not now',
                        onDidClick: () => {
                            notification.dismiss();
                            reject();
                        }
                    }
                ]
            });
        });
    }

    async installServer() {
        const message =
            'The server is being downloaded and installed. To do this, Composer is automatically downloaded and ' +
            'installed into a temporary folder.\n \n' +

            'Progress and output is sent to the developer tools console, in case you\'d like to monitor it.\n \n' +

            'You will be notified once the install finishes (or fails).';

        atom.notifications.addInfo('Serenata - Installing Server', {description: message, dismissable: true});

        try {
            await this.container.getServerManager().install();
        } catch (e) {
            const message =
                'Installation of the server failed. This can happen for a variety of reasons, such as no network ' +
                'connection.\n \n' +

                'Logs in the developer tools will likely provide you with more information about what is wrong. You ' +
                'can view them via the menu View → Developer → Toggle Developer Tools.\n \n' +

                'Additionally, the README provides more information about requirements and troubleshooting.';

            atom.notifications.addError('Serenata - Server Installation Failed', {
                description: message,
                dismissable: true,
            });

            return false;
        }

        const successMessage =
            'Installation was successful. You can now start using Serenata by opening a PHP file. \n\n' +

            'You can also customize your projects by generating an explicit configuration using ' +
            'Packages → php-ide-serenata → Set Up Current Project.';

        atom.notifications.addSuccess('Serenata - Server Installation Succeeded', {
            description: successMessage,
            dismissable: true,
        });

        return true;
    }

    async startServerProcess() {
        const packageDeps = require('atom-package-deps');

        await packageDeps.install(this.packageName, true);
        await this.installServerIfNecessary();

        let server;

        [this.socket, server] = await this.container.getProxy().getSocketConnection();

        return server;
    }

    getInitializeParams(projectPath, process) {
        const params = super.getInitializeParams(projectPath, process),
            configuration = this.container.getProjectManager().getCurrentProjectSettings();

        if (configuration !== null) {
            params.initializationOptions = {
                configuration: configuration,
            };
        }

        return params;
    }

    preInitialization(connection) {
        // We can move this to "activate", but that will cause this code to be executed regardless of the project that
        // is activated. Instead, by doing this here, we are only executed when the Atom language client (the class
        // we extend) decides that we need to be loaded (i.e. we are in a PHP project). That way, we can avoid
        // bootstrapping anything at all for non-PHP projects and reduce activation time that way.
        if (this.hasBootstrappedGlobally === false) {
            this.bootstrapGlobally();
            this.hasBootstrappedGlobally = true;
        }

        this.connection = connection;

        connection.onCustom('serenata/openTextDocument', this.onOpenTextDocument.bind(this));
        connection.onCustom('serenata/didProgressIndexing', this.onDidProgressIndexing.bind(this));

        if (this.container.getConfiguration().get('refactoring.enable')) {
            this.activateRefactoring();
        }
    }

    bootstrapGlobally() {
        this.registerConfigListeners();

        atom.project.onDidChangePaths(this.onChangeActiveProjectPaths.bind(this));

        this.onChangeActiveProjectPaths(atom.project.getPaths());
    }

    registerConfigListeners() {
        this.container.getConfiguration().onDidChange('refactoring.enable', (value) => {
            if (value) {
                this.activateRefactoring();
            } else {
                this.deactivateRefactoring();
            }
        });
    }

    provideCodeHighlight() {
        return {
            grammarScopes: this.getGrammarScopes(),
            priority: 1,
            highlight: async (editor, position) => {
                // Code lenses are not officially supported by the AutoLanguageClient, but we want to trigger fetching
                // them after a reasonable time, just like getCodeHighlight does. For this reaosn, we hijack this
                // method to do our own stuff and then let it continue as normal.
                this.updateCodeLenses(editor);

                return this.getCodeHighlight(editor, position);
            },
        };
    }

    async updateCodeLenses(editor) {
        const server = await this._serverManager.getServer(editor);

        if (server === null) {
            return null;
        }

        const codeLenses = await this.codeLens(editor);

        this.container.getCodeLensManager().process(
            editor,
            codeLenses,
            this.connection.executeCommand.bind(this.connection)
        );
    }

    async codeLens(editor) {
        const {Convert} = require('atom-languageclient');

        return await this.connection.sendCustomRequest('textDocument/codeLens', {
            textDocument: Convert.editorToTextDocumentIdentifier(editor)
        });
    }

    activateRefactoring() {
        this.getRefactoringProviders().forEach((provider) => {
            provider.activate(this.getLegacyServiceShim());
        });
    }

    deactivateRefactoring() {
        this.getRefactoringProviders().forEach((provider) => {
            provider.deactivate();
        });
    }

    getRefactoringProviders() {
        if (this.refactoringProviders === null) {
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

            const typeHelper = new TypeHelper();
            const docblockBuilder = new DocblockBuilder();
            const functionBuilder = new FunctionBuilder();
            const builder = new Builder(new ParameterParser(typeHelper), docblockBuilder, functionBuilder, typeHelper);

            builder.setService(this.getLegacyServiceShim());
            typeHelper.setService(this.getLegacyServiceShim());

            this.refactoringProviders = [];
            this.refactoringProviders.push(new DocblockProvider(typeHelper, docblockBuilder));
            this.refactoringProviders.push(new IntroducePropertyProvider(docblockBuilder));
            this.refactoringProviders.push(new GetterSetterProvider(typeHelper, functionBuilder, docblockBuilder));
            this.refactoringProviders.push(new ExtractMethodProvider(builder));
            this.refactoringProviders.push(new ConstructorGenerationProvider(
                typeHelper,
                functionBuilder,
                docblockBuilder
            ));
            this.refactoringProviders.push(new OverrideMethodProvider(docblockBuilder, functionBuilder));
            this.refactoringProviders.push(new StubAbstractMethodProvider(docblockBuilder, functionBuilder));
            this.refactoringProviders.push(new StubInterfaceMethodProvider(docblockBuilder, functionBuilder));
        }

        return this.refactoringProviders;
    }

    provideIntentions() {
        let intentionProviders = [];

        for (const provider of this.getRefactoringProviders()) {
            intentionProviders = intentionProviders.concat(provider.getIntentionProviders());
        }

        return intentionProviders;

        // return [{
        //     grammarScopes: ['source.php'],
        //     getIntentions: (options) => {
        //         return this.refactoringProviders.reduce((accumulator, provider) => {
        //             const lists = provider.getIntentionProviders().map((intentionProvider) => {
        //                 return intentionProvider.getIntentions(options);
        //             });
        //
        //             return accumulator.concat(lists.reduce((innerAccumulator, list) => {
        //                 return innerAccumulator.concat(list);
        //             }, []));
        //         }, []);
        //
        //
        //     }
        // }];
    }

    setSnippetManager(snippetManager) {
        this.getRefactoringProviders().forEach((provider) => {
            provider.setSnippetManager(snippetManager);
        });
    }

    provideAutocomplete() {
        const provider = super.provideAutocomplete();

        // Prevent language-php suggestions from showing up, we already provide them.
        provider.excludeLowerPriority = true;

        // The server does filtering by itself.
        provider.filterSuggestions = false;

        return provider;
    }

    getSuggestions(request) {
        // Temporary workaround for the languageclient library applying its own filtering and resorting on top of the
        // result list returned by the server, which should be the sole source of truth and not be modified in any
        // further way. See also https://github.com/atom/atom-languageclient/issues/218.
        request.prefix = '';

        return super.getSuggestions(request);
    }

    filterChangeWatchedFiles(filePath) {
        // Prevent changes to the index file from spamming change events.
        return !filePath.includes('/.serenata/');
    }

    onDidConvertAutocomplete(completionItem, suggestion/*, request*/) {
        suggestion.className = 'php-ide-serenata-autocompletion-suggestion';

        if (completionItem.deprecated) {
            suggestion.className += ' php-ide-serenata-autocompletion-strike';
        }

        suggestion.completionItem = completionItem;
        suggestion.leftLabelHTML = '';

        if (!completionItem.detail) {
            return;
        }

        const detailParts = completionItem.detail.split(' — ');
        const returnType = detailParts.shift();
        const accessModifier = detailParts.shift();

        if (accessModifier === 'public') {
            suggestion.leftLabelHTML += '<span class="icon icon-globe import">&nbsp;</span>';
        } else if (accessModifier === 'protected') {
            suggestion.leftLabelHTML += '<span class="icon icon-shield">&nbsp;</span>';
        } else if (accessModifier === 'private') {
            suggestion.leftLabelHTML += '<span class="icon icon-lock selector">&nbsp;</span>';
        } else {
            detailParts.push(accessModifier);
        }

        if (returnType != null) {
            suggestion.leftLabelHTML += returnType;
        }

        suggestion.rightLabel = detailParts.join(' — ');
    }

    onDidInsertSuggestion({editor/*, triggerPosition*/, suggestion}) {
        const additionalTextEdits = suggestion.completionItem.additionalTextEdits;

        if (!additionalTextEdits || additionalTextEdits.length === 0) {
            return;
        }

        return editor.transact(() => {
            return additionalTextEdits.map((additionalTextEdit) => {
                editor.setTextInBufferRange(
                    [
                        [additionalTextEdit.range.start.line, additionalTextEdit.range.start.character],
                        [additionalTextEdit.range.end.line, additionalTextEdit.range.end.character]
                    ],
                    additionalTextEdit.newText
                );
            });
        });
    }

    onOpenTextDocument(parameters) {
        const {Convert} = require('atom-languageclient');

        atom.workspace.open(Convert.uriToPath(parameters.uri), {
            initialLine: parameters.position.line,
            searchAllPanes: true,
        });
    }

    onDidProgressIndexing(data) {
        if (!this.indexingProgressBusyMessage) {
            this.indexingProgressBusyMessage = this.busySignalService.reportBusy('Indexing (scanning)', {
                waitingFor    : 'computer',
                revealTooltip : true
            });
        }

        this.indexingProgressBusyMessage.setTitle(data.info);

        if (data.progressPercentage === 100) {
            this.indexingProgressBusyMessage.dispose();
            this.indexingProgressBusyMessage = null;
        }
    }

    onChangeActiveProjectPaths(projectPaths) {
        if (projectPaths.length === 0) {
            return;
        }

        this.container.getProjectManager().tryLoad(projectPaths[0]);

        this.notifyAboutProjectFormatChange();
        this.notifyAboutSponsoringUnobtrusively();
    }

    notifyAboutProjectFormatChange() {
        if (this.container.getConfiguration().get('general.doNotShowProjectChangeMessage') === true) {
            return;
        }

        const message =
            'Serenata has updated to a new major version. Large changes have taken place, and you no longer need ' +
            '_project-manager_ to run.\n \n' +

            'A necessary switch to a new project format has happened, so a one-time action to **set up your ' +
            'projects again** is required. You can do this in the usual way, via the _Set up current project_ action.';

        const me = this;

        const notification = atom.notifications.addWarning('Serenata - Project Changes', {
            description : message,
            dismissable : true,

            buttons: [
                {
                    text: 'Got it',
                    onDidClick() {
                        me.container.getConfiguration().set('general.doNotShowProjectChangeMessage', true);

                        notification.dismiss();
                    }
                },
            ]
        });
    }

    notifyAboutSponsoringUnobtrusively() {
        if (this.container.getConfiguration().get('general.doNotAskForSupport') === true) {
            return;
        }

        let projectOpens = this.container.getConfiguration().get('general.projectOpenCount');

        if (isNaN(projectOpens)) {
            projectOpens = 0;
        }

        ++projectOpens;

        if (projectOpens > 10) {
            return; // We've already shown the message, skip updating the property to not trigger settings sync.
        }

        this.container.getConfiguration().set('general.projectOpenCount', projectOpens);

        // Only show this after a couple of project opens to avoid bothering the user when he is still setting up and
        // getting the lay of the land (and maybe doesn't even want to continue using Serenata).
        if (projectOpens !== 10) {
            return;
        }

        const message =
            'Hello!\n \n' +

            'Hopefully Serenata is working great for you. If not, the ' +
            '[issue](https://github.com/Gert-dev/php-ide-serenata/issues) ' +
            '[trackers](https://gitlab.com/Serenata/Serenata/issues) ' +
            'are always available for issues or other feedback.\n \n' +

            'Since Serenata is open source, libre as well as gratis, and is a large project to maintain, I wanted ' +
            'to take this moment to _shamelessly_ plug a link to support its further development.\n \n' +

            'Not interested? Click the button below to never hear this again.\n \n ' +

            'Interested? Great! You\'re helping make open source more sustainable - even symbolic gestures of a ' +
            'single cent are appreciated!';

        const me = this;

        const notification = atom.notifications.addInfo('Serenata - Support', {
            description : message,
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
                        me.config.set('general.projectOpenCount', 0);

                        notification.dismiss();
                    }
                },

                {
                    text: 'No, don\'t ask again',
                    onDidClick() {
                        me.config.set('general.doNotAskForSupport', true);

                        notification.dismiss();
                    }
                }
            ]
        });
    }

    getLegacyServiceShim() {
        // The shim exposes required legacy functionality until it is added to the server and we can drop this here.
        return {
            getClassInfo: (className) => {
                return this.connection.sendCustomRequest('serenata/deprecated/getClassInfo', {
                    name: className,
                });
            },

            getClassListForFile: (uri) => {
                return this.connection.sendCustomRequest('serenata/deprecated/getClassListForFile', {
                    uri: this.container.getPhpInvoker().normalizePlatformAndRuntimePath(uri),
                });
            },

            deduceTypesAt: (expression, editor, bufferPosition) => {
                const parameters = {
                    uri: this.container.getPhpInvoker().normalizePlatformAndRuntimePath('file://' + editor.getPath()),
                    position: {
                        line      : bufferPosition.row,
                        character : bufferPosition.column
                    }
                };

                if (expression != null) {
                    parameters.expression = expression;
                }

                return this.connection.sendCustomRequest('serenata/deprecated/deduceTypes', parameters);
            },

            getCurrentProjectSettings: () => {
                return this.container.getProjectManager().getCurrentProjectSettings();
            },

            getGlobalConstants: () => {
                return this.connection.sendCustomRequest('serenata/deprecated/getGlobalConstants');
            },

            getGlobalFunctions: () => {
                return this.connection.sendCustomRequest('serenata/deprecated/getGlobalFunctions');
            },

            resolveType: (uri, position, type, kind) => {
                return this.connection.sendCustomRequest('serenata/deprecated/resolveType', {
                    type: type,
                    kind: kind,
                    uri: this.container.getPhpInvoker().normalizePlatformAndRuntimePath(uri),
                    position: {
                        line: position.row,
                        character: position.column
                    }
                });
            },

            localizeType: (uri, position, type, kind) => {
                return this.connection.sendCustomRequest('serenata/deprecated/localizeType', {
                    type: type,
                    kind: kind,
                    uri: this.container.getPhpInvoker().normalizePlatformAndRuntimePath(uri),
                    position: {
                        line: position.row,
                        character: position.column
                    }
                });
            },

            determineCurrentClassName: (editor, bufferPosition) => {
                return new Promise((resolve, reject) => {
                    const path = editor.getPath();

                    if (path === null) {
                        reject();
                        return;
                    }

                    const successHandler = (classesInFile) => {
                        let bestMatch = null;

                        for (let name in classesInFile) {
                            const classInfo = classesInFile[name];

                            if ((bufferPosition.row >= classInfo.range.start.line) &&
                                (bufferPosition.row < classInfo.range.end.line)
                            ) {
                                bestMatch = name;
                            }
                        }

                        resolve(bestMatch);
                    };

                    const failureHandler = () => {
                        reject();
                    };

                    return this.getLegacyServiceShim().getClassListForFile('file://' + path).then(
                        successHandler,
                        failureHandler
                    );
                });
            },
        };
    }

    registerCommands() {
        atom.commands.add('atom-workspace', { 'php-ide-serenata:test-configuration': () => {
            this.testConfig();
        }});

        atom.commands.add('atom-workspace', { 'php-ide-serenata:restart': () => {
            this.restartAllServers();
        }});

        atom.commands.add('atom-workspace', { 'php-ide-serenata:index-project': () => {
            // Restarting the server will automatically start an index. There is no LSP standard for requesting a
            // reindex anyway.
            this.restartAllServers();
        }});

        atom.commands.add('atom-workspace', { 'php-ide-serenata:set-up-current-project': () => {
            const paths = atom.project.getPaths();

            if (paths.length === 0) {
                atom.notifications.addError('Serenata - No Active Project', {
                    description: 'Please ensure the folder of your project is already active in the tree view before ' +
                        'attempting to set it up.'
                });

                return;
            }

            try {
                this.container.getProjectManager().setUpProject(paths[0]);
            } catch (error) {
                atom.notifications.addError('Serenata - Could Not Set Up Project', {
                    description: error.message
                });

                return;
            }

            atom.notifications.addSuccess('Success - Project Set Up', {
                description: 'Your active project has been set up for Serenata. Indexing will now commence.'
            });

            this.container.getProjectManager().load(paths[0]);

            this.restartAllServers();
        }});

        atom.commands.add('atom-workspace', { 'php-ide-serenata:sort-use-statements': () => {
            const activeTextEditor = atom.workspace.getActiveTextEditor();

            if (activeTextEditor !== null) {
                this.container.getUseStatementHelper().sortUseStatements(activeTextEditor);
            }
        }});
    }

    testConfig() {
        const ConfigTester = require('./ConfigTester');
        const configTester = new ConfigTester(this.container.getPhpInvoker());

        atom.notifications.addInfo('Serenata - Testing Configuration', {
            dismissable: true,
            description: 'Testing your configuration... \n \n' +

                `If you're running through containers, this may take a while the first time as the container ` +
                `image has to be fetched first.`
        });

        const callback = async () => {
            const wasSuccessful = await configTester.test();

            if (!wasSuccessful) {
                const errorMessage =
                    `PHP is not configured correctly. Please visit the settings screen to correct this error. ` +
                    `If you are using a relative path to PHP, make sure it is in your PATH variable.`;

                atom.notifications.addError('Serenata - Failure', {dismissable: true, description: errorMessage});
            } else {
                atom.notifications.addSuccess('Serenata - Success', {
                    dismissable: true,
                    description: 'Your configuration is working correctly.'
                });
            }
        };

        return this.busySignalService.reportBusyWhile('Testing...', callback, {
            waitingFor    : 'computer',
            revealTooltip : false
        });
    }
};
