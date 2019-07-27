/* global atom */

'use strict';

const fs = require('fs');
const mkdirp = require('mkdirp');

module.exports =

class ProjectManager
{
    /**
     * @param {Object} proxy
    */
    constructor(proxy) {
        this.proxy = proxy;
        this.activeProject = null;
    }

    /**
     * Sets up the specified project for usage with the Serenata server.
     *
     * @param {String} mainFolder
    */
    setUpProject(mainFolder) {
        process = require 'process';

        // // Great, URI's are supposed to be file:// + host (optional) + path, which on UNIX systems becomes something
        // // like file:///my/folder if the host is omitted. Microsoft decided to do it differently and does
        // // file:///c:/my/path instead of file://C:/my/path, so add an additional slash and lower case the drive letter.
        // if (process.platform === 'win32') {
        //     mainFolder = '/' + mainFolder.substr(0, 1).toLowerCase() + mainFolder.substr(1);
        // }

        const configFileFolderPath = mainFolder + '/.serenata';
        const configFilePath = configFileFolderPath + '/config.json';

        if (fs.existsSync(configFilePath)) {
            throw new Error(
                'The currently active project was already initialized. To prevent existing settings from being ' +
                'lost, the request has been aborted.'
            );
        }

        const template =
`{
    "uris": [
        "file://${mainFolder}"
    ],
    "indexDatabaseUri": "file://${mainFolder}/.serenata/index.sqlite",
    "phpVersion": 7.3,
    "excludedPathExpressions": [],
    "fileExtensions": [
        "php"
    ]
}`;

        mkdirp.sync(configFileFolderPath);
        fs.writeFileSync(configFilePath, template);
    }

    /**
     * @param {String} projectFolder
     *
     * @return {Boolean}
     */
    shouldStartForProject(projectFolder) {
        return fs.existsSync(this.getConfigFilePath(projectFolder));
    }

    /**
     * @param {String} projectFolder
     */
    tryLoad(projectFolder) {
        if (!this.shouldStartForProject(projectFolder)) {
            return;
        }

        this.load(projectFolder);
    }

    /**
     * @param {String} projectFolder
     */
    load(projectFolder) {
        const path = this.getConfigFilePath(projectFolder);

        try {
            this.activeProject = JSON.parse(fs.readFileSync(path));
        } catch (e) {
            const message =
                'Loading project configuration in **' + path + '** failed. It likely contains syntax errors. \n \n' +

                'The error message returned was:\n \n' +

                '```' + e + '```';

            atom.notifications.addError('Serenata - Loading Project Failed', {
                description: message,
                dismissable: true
            });

            throw new Error('Loading project at "' + path + '" failed due to it not being valid JSON');
        }
    }

    /**
     * @param {String} projectFolder
     *
     * @return {String}
     */
    getConfigFilePath(projectFolder) {
        return projectFolder + '/.serenata/config.json';
    }

    /**
     * @return {Object|null}
    */
    getCurrentProjectSettings() {
        return this.activeProject;
    }
};
