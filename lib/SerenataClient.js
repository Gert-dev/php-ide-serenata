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
        super.activate();

        this.registerConfigListeners();
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
    }

    registerConfigListeners() {
        this.config.onDidChange('annotations.enable', (value) => {
            if (value) {
                this.activateAnnotations();
            } else {
                this.deactivateAnnotations();
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
                    // TODO: Don't have the normalizer or invoker here yet.
                    uri: this.phpInvoker.normalizePlatformAndRuntimePath(uri),
                });
            },
        };
    }
};
