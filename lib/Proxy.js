/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let Proxy;
const net = require('net');

module.exports =

//#*
// Proxy that handles communicating with the PHP side.
//#
(Proxy = (function() {
    Proxy = class Proxy {
        static initClass() {
            /**
             * The config to use.
             *
             * @var {Object}
            */
            this.prototype.config = null;

            /**
             * @var {Object}
            */
            this.prototype.phpInvoker = null;

            /**
             * @var {String}
            */
            this.prototype.serverPath = null;

            /**
             * @var {Object}
            */
            this.prototype.phpServer = null;

            /**
             * @var {Promise}
            */
            this.prototype.phpServerPromise = null;

            /**
             * @var {Object}
            */
            this.prototype.client = null;
        }

        /**
         * Constructor.
         *
         * @param {Config}     config
         * @param {PhpInvoker} phpInvoker
        */
        constructor(config, phpInvoker) {
            this.config = config;
            this.phpInvoker = phpInvoker;
            this.requestQueue = {};
            this.port = this.getRandomServerPort();
        }

        /**
         * Spawns the PHP socket server process.
         *
         * @param {Number} port
         *
         * @return {Promise}
        */
        spawnPhpServer(port) {
            const memoryLimit = this.config.get('core.memoryLimit');
            const socketHost = this.config.get('core.phpExecutionType') === 'host' ? '127.0.0.1' : '0.0.0.0';

            const parameters = [
                // Enable this to debug or profile using Xdebug. You will also need to allow Xdebug below.
                //'-d zend_extension=/usr/lib/php/modules/xdebug.so',
                //'-d xdebug.profiler_enable=On',
                // // '-d xdebug.gc_stats_enable=On',
                //'-d xdebug.profiler_output_dir=/tmp',

                `-d memory_limit=${memoryLimit}M`,
                this.phpInvoker.normalizePlatformAndRuntimePath(this.serverPath) + '/bin/console',
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
                        return resolve(process);
                    }
                });

                process.stderr.on('data', data => {
                    return console.warn('The PHP server has errors to report:', data.toString());
                });

                return process.on('close', code => {
                    if (code === 2) {
                        console.error(`Port ${port} is already taken`);
                        return;

                    } else if (code !== 0) {
                        const detail =
                            'Serenata unexpectedly closed. Either something caused the process to stop, it crashed, ' +
                            'or the socket closed. In case of the first two, you should see additional output ' +
                            'indicating this is the case and you can report a bug. If there is no additional output, ' +
                            'you may be missing the right dependencies or extensions or the server may have run out ' +
                            'of memory (you can increase it via the settings screen).';

                        console.error(detail);
                    }

                    // this.closeServerConnection();

                    this.phpServer = null;
                    return reject();
                });
            });
        }

        /**
         * @return {Number}
        */
        getRandomServerPort() {
            const minPort = 10000;
            const maxPort = 40000;

            return Math.floor((Math.random() * (maxPort - minPort)) + minPort);
        }

        /**
         * Spawns the PHP socket server process.
         *
         * @param {Number} port
         *
         * @return {Promise}
        */
        spawnPhpServerIfNecessary(port) {
            if (this.phpServer) {
                this.phpServerPromise = null;

                return new Promise((resolve, reject) => {
                    return resolve(this.phpServer);
                });

            } else if (this.phpServerPromise) {
                return this.phpServerPromise;
            }

            const successHandler = phpServer => {
                this.phpServer = phpServer;

                return phpServer;
            };

            const failureHandler = () => {
                return this.phpServerPromise = null;
            };

            this.phpServerPromise = this.spawnPhpServer(port).then(successHandler, failureHandler);

            return this.phpServerPromise;
        }

        /**
         * @return {Promise}
        */
        getSocketConnection() {
            return new Promise((resolve, reject) => {
                return this.spawnPhpServerIfNecessary(this.port).then(() => {
                    if (this.client != null) {
                        resolve(this.client);
                        return;
                    }

                    this.client = net.createConnection({port: this.port}, () => {
                        return resolve(this.client);
                    });

                    this.client.setNoDelay(true);
                    this.client.on('error', this.onSocketError.bind(this));
                });
            });
        }

        /**
         * @param {Object} error
        */
        onSocketError(error) {
            // Do nothing here, this should silence socket errors such as ECONNRESET. After this is called, the socket will
            // be closed and all handling is performed there.
            return console.warn('The socket connection notified us of an error', error);
        }

        /**
         * @param {String} serverPath
        */
        setServerPath(serverPath) {
            this.serverPath = serverPath;
        }
    };
    Proxy.initClass();
    return Proxy;
})());
