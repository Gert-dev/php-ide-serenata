/* global atom */

'use strict';

const net = require('net');

module.exports =

class Proxy {
    /**
     * Constructor.
     *
     * @param {Config}     config
     * @param {PhpInvoker} phpInvoker
    */
    constructor(config, phpInvoker) {
        this.serverPath = null;
        this.config = config;
        this.phpInvoker = phpInvoker;
        this.port = this.generateRandomServerPort();
    }

    /**
     * Spawns the PHP socket server process.
     *
     * @param {Number} port
     *
     * @return {Promise}
    */
    async spawnPhpServer(port) {
        const memoryLimit = this.config.get('core.memoryLimit');
        const socketHost = this.config.get('core.phpExecutionType') === 'host' ? '127.0.0.1' : '0.0.0.0';

        const parameters = [
            // Enable this to debug or profile using Xdebug. You will also need to allow Xdebug below.
            //'-d zend_extension=/usr/lib/php/modules/xdebug.so',
            //'-d xdebug.profiler_enable=On',
            // // '-d xdebug.gc_stats_enable=On',
            //'-d xdebug.profiler_output_dir=/tmp',

            `-d memory_limit=${memoryLimit}M`,
            this.phpInvoker.normalizePlatformAndRuntimePath(this.serverPath) + 'distribution.phar',
            `--uri=tcp://${socketHost}:${port}`
        ];

        const additionalDockerRunParameters = [
            '-p', `127.0.0.1:${port}:${port}`
        ];

        const options = {
            // Enable this to debug or profile as well.
            // env: {
            //     SERENATA_ALLOW_XDEBUG: 1
            // }
        };

        const process = this.phpInvoker.invoke(parameters, additionalDockerRunParameters, options);

        return new Promise((resolve, reject) => {
            process.stdout.on('data', data => {
                const message = data.toString();

                console.debug('The PHP server has something to say:', message);

                if (message.indexOf('Starting server bound') !== -1) {
                    // Assume the server has successfully spawned the moment it says it's listening.
                    resolve(process);
                }
            });

            process.stderr.on('data', data => {
                console.error('The PHP server has errors to report:', data.toString());
            });

            process.on('close', (code) => {
                if (code === 2) {
                    console.error(`Port ${port} is already taken`);
                } else if (code !== 0 && code !== null) {
                    const detail =
                        'Serenata unexpectedly closed. Either something caused the process to stop, it crashed, ' +
                        'or the socket closed. In case of the first two, you should see additional output ' +
                        'indicating this is the case and you can report a bug. If there is no additional output, ' +
                        'you may be missing the right dependencies or extensions or the server may have run out ' +
                        'of memory (you can increase it via the settings screen).';

                    console.error(detail);
                }

                reject();
            });
        });
    }

    /**
     * @return {Number}
    */
    generateRandomServerPort() {
        const minPort = 10000;
        const maxPort = 40000;

        return Math.floor((Math.random() * (maxPort - minPort)) + minPort);
    }

    /**
     * @return {Promise}
    */
    async getSocketConnection() {
        const phpServer = await this.spawnPhpServer(this.port);

        return new Promise((resolve) => {
            const client = net.createConnection({port: this.port}, () => {
                resolve([client, phpServer]);
            });

            client.setNoDelay(true);
            client.on('error', this.onSocketError.bind(this));
        });
    }

    /**
     * @param {Object} error
    */
    onSocketError(error) {
        // Do nothing here, this should silence socket errors such as ECONNRESET. After this is called, the socket
        // will be closed and all handling is performed there.
        console.debug(
            'The socket connection notified us of an error (this is normal if the server is shutdown).',
            error
        );
    }

    /**
     * @param {String} serverPath
    */
    setServerPath(serverPath) {
        this.serverPath = serverPath;
    }
};
