'use strict';

const {AutoLanguageClient} = require('atom-languageclient');

module.exports =

class SerenataClient extends AutoLanguageClient
{
    constructor(proxy) {
        super();

        this.proxy = proxy;
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

    async startServerProcess() {
        this.socket = await this.proxy.getSocketConnection();

        return this.proxy.phpServer;
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
};
