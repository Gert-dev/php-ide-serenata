/* global atom */

'use strict';

const path = require('path');
const process = require('process');
const mkdirp = require('mkdirp');

const Config = require('./Config');

module.exports =

class AtomConfig extends Config
{
    /**
     * @inheritdoc
    */
    constructor(packageName) {
        super();

        this.packageName = packageName;
        this.configurableProperties = [
            'core.phpExecutionType',
            'core.phpCommand',
            'core.memoryLimit',
            'core.additionalDockerVolumes',
            'general.doNotAskForSupport',
            'general.doNotShowProjectChangeMessage',
            'general.projectOpenCount',
            'refactoring.enable',
        ];

        this.attachListeners();
    }

    /**
     * @inheritdoc
    */
    load() {
        this.set('storagePath', this.getPathToStorageFolderInRidiculousWay());

        this.configurableProperties.forEach((property) => {
            this.set(property, atom.config.get(`${this.packageName}.${property}`));
        });
    }

    /**
     * @inheritdoc
    */
    set(name, value) {
        super.set(name, value);

        atom.config.set(`${this.packageName}.${name}`, value);
    }

    /**
     * Attaches listeners to listen to Atom configuration changes.
    */
    attachListeners() {
        this.configurableProperties.forEach((property) => {
            atom.config.onDidChange(`${this.packageName}.${property}`, (data) => {
                this.set(property, data.newValue);
            });
        });
    }

    /**
     * @return {String}
    */
    getPathToStorageFolderInRidiculousWay() {
        // NOTE: Apparently process.env.ATOM_HOME is not always set for whatever reason and this ridiculous workaround
        // is needed to fetch an OS-compliant location to store application data.
        let baseFolder = null;

        if (process.env.APPDATA) {
            baseFolder = process.env.APPDATA;
        } else if (process.platform === 'darwin') {
            baseFolder = process.env.HOME + '/Library/Preferences';
        } else {
            baseFolder = process.env.HOME + '/.cache';
        }

        const packageFolder = baseFolder + path.sep + this.packageName;

        mkdirp.sync(packageFolder);

        return packageFolder;
    }
};
