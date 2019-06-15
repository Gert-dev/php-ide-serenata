/* global atom */

'use strict';

module.exports =

/**
 * Invokes PHP.
 */
class PhpInvoker
{
    /**
     * Constructor.
     *
     * @param {Config} config
    */
    constructor(config) {
        this.config = config;
    }

    /**
     * Invokes PHP.
     *
     * NOTE: The composer:1.6.4 image uses the Alpine version of the "PHP 7.x" image of PHP, which at the time of
     * writing is PHP 7.2. The most important part is that the PHP version used for Composer installations is the same
     * as the one used for actually running the server to avoid outdated or too recent dependencies.
     *
     * @param {Array}  parameters
     * @param {Array}  additionalDockerRunParameters
     * @param {Object} options
     * @param {String} dockerImage
     *
     * @return {Process}
    */
    invoke(parameters, additionalDockerRunParameters = [], options = {}, dockerImage = 'composer:1.6.4') {
        const child_process = require('child_process');
        const executionType = this.config.get('core.phpExecutionType');

        if (executionType === 'host') {
            return child_process.spawn(this.config.get('core.phpCommand'), parameters, options);
        }

        let command = 'docker';

        if (executionType === 'podman') {
            additionalDockerRunParameters = ['-net=host'].concat(additionalDockerRunParameters);
        }

        let dockerParameters = this.getDockerRunParameters(dockerImage, additionalDockerRunParameters);

        dockerParameters = dockerParameters.concat(parameters);

        if (executionType === 'docker') {
            command = 'docker';
        } else if (executionType === 'docker-polkit') {
            dockerParameters = [command].concat(dockerParameters);
            command = 'pkexec';
        } else if (executionType === 'podman') {
            /*
                Add this to the PHP execution type config option in package.json to test this:

                {
                  "value": "podman",
                  "description": "Use a PHP container via Podman, avoiding privilege escalation entirely (experimental)"
                }

                Note that podman doesn't work yet because it doesn't support rootless port binding yet at the time of
                writing. I don't believe Serenata needs any special ports below 1024 or any other things that requires
                root, so if they implement support for this, we should be able to easily support it.
            */
            command = 'podman';
        } else {
            throw new Error('Unknown executionType "' + executionType + '" received');
        }

        const process = child_process.spawn(command, dockerParameters);

        console.debug(command, dockerParameters);

        // NOTE: Uncomment this to test failures
        process.stdout.on('data', data => {
            console.debug('STDOUT', data.toString());
        });

        process.stderr.on('data', data => {
            console.debug('STDERR', data.toString());
        });

        return process;
    }

    /**
     * @param {Array}  additionalDockerRunParameters
     *
     * @return {Array}
    */
    getDockerRunParameters(dockerImage, additionalDockerRunParameters) {
        const parameters = ['run', '--rm=true'];
        const object = this.getPathsToMountInDockerContainer();

        for (const src in object) {
            const dest = object[src];
            parameters.push('-v');
            parameters.push(src + ':' + dest);
        }

        return parameters.concat(additionalDockerRunParameters).concat([dockerImage, 'php']);
    }

    /**
     * @return {Object}
    */
    getPathsToMountInDockerContainer() {
        const paths = {};
        paths[this.config.get('storagePath')] = this.config.get('storagePath');

        for (const path of this.config.get('core.additionalDockerVolumes')) {
            const parts = path.split(':');

            paths[parts[0]] = parts[1];
        }

        for (const path of atom.project.getPaths()) {
            paths[path] = path;
        }

        return this.normalizeVolumePaths(paths);
    }

    /**
     * @param {Object} pathMap
     *
     * @return {Object}
    */
    normalizeVolumePaths(pathMap) {
        const newPathMap = {};

        for (let src in pathMap) {
            const dest = pathMap[src];
            newPathMap[this.normalizeVolumePath(src)] = this.normalizeVolumePath(dest);
        }

        return newPathMap;
    }

    /**
     * @param {String} path
     *
     * @return {String}
    */
    normalizeVolumePath(path) {
        const os = require('os');

        if (os.platform() !== 'win32') {
            return path;
        }

        const matches = path.match(/^([A-Z]+):(.+)$/);

        if (matches !== null) {
            // On Windows, paths for Docker volumes are specified as /c/Path/To/Item.
            path = `/${matches[1].toLowerCase()}${matches[2]}`;
        }

        return path.replace(/\\/g, '/');
    }

    /**
     * @param {String} path
     *
     * @return {String}
    */
    denormalizeVolumePath(path) {
        const os = require('os');

        if (os.platform() !== 'win32') {
            return path;
        }

        const matches = path.match(/^\/([a-z]+)\/(.+)$/);

        if (matches !== null) {
            // On Windows, paths for Docker volumes are specified as /c/Path/To/Item.
            path = matches[1].toUpperCase() + ':\\' + matches[2];
        }

        return path.replace(/\//g, '\\');
    }

    /**
     * Retrieves a path normalized for the current platform *and* runtime.
     *
     * On Windows, we still need UNIX paths if we are using Docker as runtime,
     * but not if we are using the host PHP.
     *
     * @param {String} path
     *
     * @return {String}
    */
    normalizePlatformAndRuntimePath(path) {
        if (this.config.get('core.phpExecutionType') === 'host') {
            return path;
        }

        return this.normalizeVolumePath(path);
    }

    /**
     * Retrieves a path denormalized for the current platform *and* runtime.
     *
     * On Windows, this converts Docker paths back to Windows paths.
     *
     * @param {String} path
     *
     * @return {String}
    */
    denormalizePlatformAndRuntimePath(path) {
        if (this.config.get('core.phpExecutionType') === 'host') {
            return path;
        }

        return this.denormalizeVolumePath(path);
    }
};
