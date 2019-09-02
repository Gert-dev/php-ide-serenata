'use strict';

module.exports =

/**
 * Abstract base class for managing configurations.
  */
class Config {
    constructor() {
        this.listeners = {};

        this.data = {
            'core.phpExecutionType'        : 'host',
            'core.phpCommand'              : null,
            'core.memoryLimit'             : 2048,
            'core.additionalDockerVolumes' : [],

            'general.doNotAskForSupport'            : false,
            'general.doNotShowProjectChangeMessage' : false,
            'general.projectOpenCount'              : 0,

            'refactoring.enable'           : true,
        };
    }

    load() {
        throw new Error('This method is abstract and must be implemented!');
    }

    onDidChange(name, callback) {
        if (!(name in this.listeners)) {
            this.listeners[name] = [];
        }

        this.listeners[name].push(callback);
    }

    get(name) {
        return this.data[name];
    }

    set(name, value) {
        this.data[name] = value;

        if (name in this.listeners) {
            this.listeners[name].map((listener) => {
                listener(value, name);
            });
        }
    }
};
