/* global atom */

'use strict';

const {AutoLanguageClient} = require('atom-languageclient');
const MethodAnnotationProvider = require('./Annotations/MethodAnnotationProvider');
const PropertyAnnotationProvider = require('./Annotations/PropertyAnnotationProvider');

module.exports =

class SerenataClient extends AutoLanguageClient
{
    constructor(proxy, projectManager, phpInvoker, config) {
        super();

        this.proxy = proxy;
        this.config = config;
        this.connection = null;
        this.annotationProviders = [];
        this.refactoringProviders = [];
        this.projectManager = projectManager;
        this.phpInvoker = phpInvoker;
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
        this.registerConfigListeners();

        // Necessary up front as we can only send intention providers once. If we wait until refactoring is activated,
        // the call will already have happened.
        this.setupRefactoringProviders();

        atom.project.onDidChangePaths(this.onChangeActiveProjectPaths.bind(this));

        this.onChangeActiveProjectPaths(atom.project.getPaths());

        super.activate();
    }

    deactivate() {
        this.deactivateAnnotations();
        this.deactivateRefactoring();

        return super.deactivate();
    }

    async startServerProcess() {
        this.socket = await this.proxy.getSocketConnection();

        return this.proxy.phpServer;
    }

    preInitialization(connection) {
        this.connection = connection;

        connection.onCustom('serenata/didProgressIndexing', this.onDidProgressIndexing.bind(this));

        if (this.config.get('annotations.enable')) {
            this.activateAnnotations();
        }

        if (this.config.get('refactoring.enable')) {
            this.activateRefactoring();
        }
    }

    registerConfigListeners() {
        this.config.onDidChange('annotations.enable', (value) => {
            if (value) {
                this.activateAnnotations();
            } else {
                this.deactivateAnnotations();
            }
        });

        this.config.onDidChange('refactoring.enable', (value) => {
            if (value) {
                this.activateRefactoring();
            } else {
                this.deactivateRefactoring();
            }
        });
    }

    activateAnnotations() {
        this.annotationProviders = [];
        this.annotationProviders.push(new MethodAnnotationProvider());
        this.annotationProviders.push(new PropertyAnnotationProvider());

        this.annotationProviders.map((provider) => {
            provider.activate(this.getLegacyServiceShim());
        });
    }

    deactivateAnnotations() {
        for (const provider of this.annotationProviders) {
            provider.deactivate();
        }

        this.annotationProviders = [];
    }

    setupRefactoringProviders() {
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
        this.refactoringProviders.push(new ConstructorGenerationProvider(typeHelper, functionBuilder, docblockBuilder));
        this.refactoringProviders.push(new OverrideMethodProvider(docblockBuilder, functionBuilder));
        this.refactoringProviders.push(new StubAbstractMethodProvider(docblockBuilder, functionBuilder));
        this.refactoringProviders.push(new StubInterfaceMethodProvider(docblockBuilder, functionBuilder));
    }

    activateRefactoring() {
        return this.refactoringProviders.map((provider) => {
            provider.activate(this.getLegacyServiceShim());
        });
    }

    deactivateRefactoring() {
        for (const provider of this.refactoringProviders) {
            provider.deactivate();
        }

        this.refactoringProviders = null;
    }

    provideIntentions() {
        let intentionProviders = [];

        for (const provider of this.refactoringProviders) {
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
        return this.refactoringProviders.map((provider) => {
            provider.setSnippetManager(snippetManager);
        });
    }

    provideAutocomplete() {
        const provider = super.provideAutocomplete();

        // Keep language-php suggestions from showing up.
        provider.inclusionPriority = 1;
        provider.excludeLowerPriority = true;

        // The server does filtering by itself.
        provider.filterSuggestions = false;

        return provider;
    }

    shouldStartForEditor(editor) {
        if (!super.shouldStartForEditor(editor)) {
            return false;
        }

        const projectPath = this._serverManager.determineProjectPath(editor);

        if (projectPath === null) {
            return false;
        }

        return this.projectManager.shouldStartForProject(projectPath);
    }

    filterChangeWatchedFiles(filePath) {
        // Prevent changes to the index file from spamming change events.
        return filePath.indexOf('/.serenata/') === -1;
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

    onDidProgressIndexing(data) {
        if (!this.indexingProgressBusyMessage) {
            this.indexingProgressBusyMessage = this.busySignalService.reportBusy('Indexing (scanning)', {
                waitingFor    : 'computer',
                revealTooltip : true
            });
        }

        this.indexingProgressBusyMessage.setTitle('Indexing (' + data.progressPercentage.toFixed(2) + ' %)');

        if (data.progressPercentage === 100) {
            this.indexingProgressBusyMessage.dispose();
            this.indexingProgressBusyMessage = null;
        }
    }

    onChangeActiveProjectPaths(projectPaths) {
        if (projectPaths.length === 0) {
            return;
        }

        this.projectManager.tryLoad(projectPaths[0]);

        // TODO
        // this.notifyAboutSponsoringUnobtrusively();
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
                    uri: this.phpInvoker.normalizePlatformAndRuntimePath(uri),
                });
            },

            deduceTypesAt: (expression, editor, bufferPosition) => {
                const parameters = {
                    uri: this.phpInvoker.normalizePlatformAndRuntimePath('file://' + editor.getPath()),
                    position: {
                        line      : bufferPosition.row,
                        character : bufferPosition.column
                    }
                }

                if (expression != null) {
                    parameters.expression = expression;
                }

                return this.connection.sendCustomRequest('serenata/deprecated/deduceTypes', parameters);
            },

            getCurrentProjectSettings: () => {
                return this.projectManager.getCurrentProjectSettings();
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
                    uri: this.phpInvoker.normalizePlatformAndRuntimePath(uri),
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
                    uri: this.phpInvoker.normalizePlatformAndRuntimePath(uri),
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
};
