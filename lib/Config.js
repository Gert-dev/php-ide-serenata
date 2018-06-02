/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let Config;
const fs = require('fs');

module.exports =

//#*
// Abstract base class for managing configurations.
//#
(Config = (function() {
    Config = class Config {
        static initClass() {
            /**
             * Raw configuration object.
            */
            this.prototype.data = null;

            /**
             * Array of change listeners.
            */
            this.prototype.listeners = null;
        }

        /**
         * Constructor.
        */
        constructor() {
            this.listeners = {};

            this.data = {
                core: {
                    phpExecutionType        : 'host',
                    phpCommand              : null,
                    memoryLimit             : 512,
                    additionalDockerVolumes : []
                },

                general: {
                    indexContinuously       : true,
                    additionalIndexingDelay : 200
                },

                datatips: {
                    enable : true
                },

                signatureHelp: {
                    enable : true
                },

                gotoDefintion: {
                    enable : true
                },

                autocompletion: {
                    enable : true
                },

                annotations: {
                    enable : true
                },

                refactoring: {
                    enable : true
                },

                linting: {
                    enable                      : true,
                    showUnknownClasses          : true,
                    showUnknownMembers          : true,
                    showUnknownGlobalFunctions  : true,
                    showUnknownGlobalConstants  : true,
                    showUnusedUseStatements     : true,
                    showMissingDocs             : true,
                    validateDocblockCorrectness : true
                },

                // See also http://www.phpdoc.org/docs/latest/index.html .
                phpdoc_base_url : {
                    prefix: 'http://www.phpdoc.org/docs/latest/references/phpdoc/tags/',
                    suffix: '.html'
                },

                // See also https://secure.php.net/urlhowto.php .
                php_documentation_base_urls : {
                    root      : 'https://secure.php.net/',
                    classes   : 'https://secure.php.net/class.',
                    functions : 'https://secure.php.net/function.'
                }
            };
        }

        /**
         * Loads the configuration.
        */
        load() {
            throw new Error('This method is abstract and must be implemented!');
        }

        /**
         * Registers a listener that is invoked when the specified property is changed.
        */
        onDidChange(name, callback) {
            if (!(name in this.listeners)) {
                this.listeners[name] = [];
            }

            return this.listeners[name].push(callback);
        }

        /**
         * Retrieves the config setting with the specified name.
         *
         * @return {mixed}
        */
        get(name) {
            return this.data[name];
        }

        /**
         * Retrieves the config setting with the specified name.
         *
         * @param {String} name
         * @param {mixed}  value
        */
        set(name, value) {
            this.data[name] = value;

            if (name in this.listeners) {
                return this.listeners[name].map((listener) => {
                    listener(value, name);
                });
            }
        }
    };
    Config.initClass();
    return Config;
})());
