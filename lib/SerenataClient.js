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
};
