/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let Proxy;
const fs            = require('fs');
const net           = require('net');
const path          = require('path');
const mkdirp        = require('mkdirp');
const stream        = require('stream');
const child_process = require('child_process');
const sanitize      = require('sanitize-filename');

const CancellablePromise = require('./CancellablePromise');

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
             * The name (without path or extension) of the database file to use.
             *
             * @var {Object}
            */
            this.prototype.indexDatabaseName = null;

            /**
             * @var {Boolean}
            */
            this.prototype.isActive = false;

            /**
             * @var {String}
            */
            this.prototype.serverPath = null;

            /**
             * @var {Object}
            */
            this.prototype.phpServer = null;

            /**
             * @var {CancellablePromise}
            */
            this.prototype.phpServerPromise = null;

            /**
             * @var {Object}
            */
            this.prototype.client = null;

            /**
             * @var {Object}
            */
            this.prototype.requestQueue = null;

            /**
             * @var {Number}
            */
            this.prototype.nextRequestId = 1;

            /**
             * @var {Object}
            */
            this.prototype.response = null;

            /**
             * @var {String}
            */
            this.prototype.HEADER_DELIMITER = '\r\n';

            /**
             * @var {Number}
            */
            this.prototype.FATAL_SERVER_ERROR = -32000;
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

            this.resetResponseState();
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
                //'-d zend_extension=/usr/lib/php/modules/xdebug.so'
                //'-d xdebug.profiler_enable=On',
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

                process.on('error', error => {
                    console.error('An error ocurred whilst invoking PHP', error);
                    return reject();
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

                    this.closeServerConnection();

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
         * Closes the socket connection to the server.
        */
        closeServerConnection() {
            this.rejectAllOpenRequests();

            if (!this.client) { return; }

            this.client.destroy();
            this.client = null;

            return this.resetResponseState();
        }

        /**
         * Rejects all currently open requests.
        */
        rejectAllOpenRequests() {
            for (let id in this.requestQueue) {
                const request = this.requestQueue[id];
                request.promise.reject('Socket connection encountered invalid state or was closed, please resend request');
            }

            return this.requestQueue = {};
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
                    this.client.on('data', this.onDataReceived.bind(this));
                    return this.client.on('close', this.onConnectionClosed.bind(this));
                });
            });
        }

        /**
         * @param {String} data
        */
        onDataReceived(data) {
            try {
                return this.processDataBuffer(data);

            } catch (error) {
                console.warn('Encountered some invalid data, resetting state. Error: ', error);

                return this.resetResponseState();
            }
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
         * @param {Boolean} hadError
        */
        onConnectionClosed(hadError) {
            return this.closeServerConnection();
        }

        /**
         * @param {Buffer} dataBuffer
        */
        processDataBuffer(dataBuffer) {
            let bytesRead;
            if ((this.response.length == null)) {
                const contentLengthHeader = this.readRawHeader(dataBuffer);
                this.response.length = this.getLengthFromContentLengthHeader(contentLengthHeader);

                bytesRead = contentLengthHeader.length + this.HEADER_DELIMITER.length;

            } else if (!this.response.wasBoundaryFound) {
                const header = this.readRawHeader(dataBuffer);

                if (header.length === 0) {
                    this.response.wasBoundaryFound = true;
                }

                bytesRead = header.length + this.HEADER_DELIMITER.length;

            } else {
                bytesRead = Math.min(dataBuffer.length, this.response.length - this.response.bytesRead);

                this.response.content = Buffer.concat([this.response.content, dataBuffer.slice(0, bytesRead)]);
                this.response.bytesRead += bytesRead;

                if (this.response.bytesRead === this.response.length) {
                    const jsonRpcResponse = this.getJsonRpcResponseFromResponseBuffer(this.response.content);

                    this.processJsonRpcResponse(jsonRpcResponse);

                    this.resetResponseState();
                }
            }

            dataBuffer = dataBuffer.slice(bytesRead);

            if (dataBuffer.length > 0) {
                return this.processDataBuffer(dataBuffer);
            }
        }

        /**
         * @param {Object} jsonRpcResponse
        */
        processJsonRpcResponse(jsonRpcResponse) {
            if (jsonRpcResponse.id != null) {
                const jsonRpcRequest = this.requestQueue[jsonRpcResponse.id];

                if ((jsonRpcRequest == null)) {
                    console.warn('Received response for request that was already removed from the queue', jsonRpcResponse);
                    return;
                }

                this.processJsonRpcResponseForRequest(jsonRpcResponse, jsonRpcRequest);

                return delete this.requestQueue[jsonRpcResponse.id];

            } else {
                return this.processNotificationJsonRpcResponse(jsonRpcResponse);
            }
        }

        /**
         * @param {Object} jsonRpcResponse
         * @param {Object} jsonRpcRequest
        */
        processJsonRpcResponseForRequest(jsonRpcResponse, jsonRpcRequest) {
            if (jsonRpcResponse.error != null) {
                jsonRpcRequest.promise.reject({
                    request  : jsonRpcRequest,
                    response : jsonRpcResponse,
                    error    : jsonRpcResponse.error
                });

                if (jsonRpcResponse.error.code === this.FATAL_SERVER_ERROR) {
                    return this.showFatalServerError(jsonRpcResponse.error);
                }

            } else {
                return jsonRpcRequest.promise.resolve(jsonRpcResponse.result);
            }
        }

        /**
         * @param {Object} jsonRpcResponse
         * @param {Object} jsonRpcRequest
        */
        processNotificationJsonRpcResponse(jsonRpcResponse) {
            if ((jsonRpcResponse.result == null)) {
                console.warn('Received a server notification without a result', jsonRpcResponse);
                return;
            }

            if (jsonRpcResponse.result.type === 'reindexProgressInformation') {
                if ((jsonRpcResponse.result.requestId == null)) {
                    console.warn('Received progress information without a request ID to go with it', jsonRpcResponse);
                    return;
                }

                const relatedJsonRpcRequest = this.requestQueue[jsonRpcResponse.result.requestId];

                if ((relatedJsonRpcRequest == null)) {
                    console.warn(
                        'Received progress information for request that doesn\'t exist or was already finished',
                        jsonRpcResponse
                    );
                    return;

                } else if ((relatedJsonRpcRequest.streamCallback == null)) {
                    console.warn('Received progress information for a request that isn\'t interested in it', jsonRpcResponse);
                    return;
                }

                return relatedJsonRpcRequest.streamCallback(jsonRpcResponse.result.progress);

            } else {
                return console.warn('Received a server notification with an unknown type', jsonRpcResponse);
            }
        }

        /**
         * @param {Buffer} dataBuffer
         *
         * @return {Object}
        */
        getJsonRpcResponseFromResponseBuffer(dataBuffer) {
            const jsonRpcResponseString = dataBuffer.toString();

            return this.getJsonRpcResponseFromResponseContent(jsonRpcResponseString);
        }

        /**
         * @param {String} content
         *
         * @return {Object}
        */
        getJsonRpcResponseFromResponseContent(content) {
            return JSON.parse(content);
        }

        /**
         * @param {Buffer} dataBuffer
         *
         * @throws {Error}
         *
         * @return {String}
        */
        readRawHeader(dataBuffer) {
            const end = dataBuffer.indexOf(this.HEADER_DELIMITER);

            if (end === -1) {
                throw new Error('Header delimiter not found');
            }

            return dataBuffer.slice(0, end).toString();
        }

        /**
         * @param {String} rawHeader
         *
         * @throws {Error}
         *
         * @return {Number}
        */
        getLengthFromContentLengthHeader(rawHeader) {
            const parts = rawHeader.split(':');

            if (parts.length !== 2) {
                throw new Error('Unexpected amount of header parts found');
            }

            const contentLength = parseInt(parts[1]);

            if ((contentLength == null)) {
                throw new Error('Content length header does not have an integer as value');
            }

            return contentLength;
        }

        /**
         * Resets the current response's state.
        */
        resetResponseState() {
            return this.response = {
                length           : null,
                wasBoundaryFound : false,
                bytesRead        : 0,
                content          : new Buffer([])
            };
        }

        /**
         * Performs an asynchronous request to the PHP side.
         *
         * @param {Number}   id
         * @param {String}   method
         * @param {Object}   parameters
         * @param {Callback} streamCallback
         *
         * @return {CancellablePromise}
        */
        performJsonRpcRequest(id, method, parameters, streamCallback = null) {
            const executor = (resolve, reject) => {
                if (!this.getIsActive()) {
                    reject('The proxy is not yet active, the server may be in the process of being downloaded');
                    return;
                }

                const jsonRpcRequest = {
                    jsonrpc : 2.0,
                    id,
                    method,
                    params  : parameters
                };

                this.requestQueue[id] = {
                    id,
                    streamCallback,
                    request        : jsonRpcRequest,

                    promise: {
                        resolve,
                        reject
                    }
                };

                const content = this.getContentForJsonRpcRequest(jsonRpcRequest);

                return this.writeRawRequest(content);
            };

            const cancelHandler = () => {
                return this.performRequest('cancelRequest', {id});
            };

            return new CancellablePromise(executor, cancelHandler);
        }

        /**
         * @param {Object} request
         *
         * @return {String}
        */
        getContentForJsonRpcRequest(request) {
            return JSON.stringify(request);
        }

        /**
         * Writes a raw request to the connection.
         *
         * This may not happen immediately if the connection is not available yet. In that case, the request will be
         * dispatched as soon as the connection becomes available.
         *
         * @param {String} content The content (body) of the request.
        */
        writeRawRequest(content) {
            return this.getSocketConnection().then(connection => {
                const lengthInBytes = (new TextEncoder('utf-8').encode(content)).length;

                connection.write(`Content-Length: ${lengthInBytes}${this.HEADER_DELIMITER}`);
                connection.write(this.HEADER_DELIMITER);
                return connection.write(content);
            });
        }

        /**
         * @param {Object} error
        */
        showFatalServerError(error) {
            let notification;
            const detail =
                'You\'ve likely hit a snag in the Serenata server. Feel free to report it on its bug tracker. ' +
                'If you do, please include the information printed below.\n \n' +

                'Please do *not* report this to the issue tracker of this package on GitHub as it is not a bug here.\n \n' +

                'The server will attempt to restart itself.\n \n' +

                error.data.backtrace;

            return notification = atom.notifications.addError('Serenata - Darn, we\'ve crashed!', {
                dismissable : true,
                detail,

                buttons: [
                    {
                        text: 'Open issue tracker',
                        onDidClick() {
                            const {shell} = require('electron');
                            return shell.openExternal('https://gitlab.com/Serenata/Serenata/issues');
                        }
                    },

                    {
                        text: 'Dismiss',
                        onDidClick() {
                            return notification.dismiss();
                        }
                    }
                ]
            });
        }

        /**
         * @param {String}      method
         * @param {Object}      parameters
         * @param {Callback}    streamCallback A method to invoke each time streaming data is received.
         * @param {String|null} stdinData      The data to pass to STDIN.
         *
         * @return {CancellablePromise}
        */
        performRequest(method, parameters, streamCallback = null, stdinData = null) {
            if (stdinData != null) {
                parameters.stdin = true;
                parameters.stdinData = stdinData;
            }

            const requestId = this.nextRequestId++;

            return this.performJsonRpcRequest(requestId, method, parameters, streamCallback);
        }

        /**
         * Retrieves a list of available classes in the specified file.
         *
         * @param {String} file
         *
         * @return {Promise}
        */
        getClassListForFile(file) {
            if (!file) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No file passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath(),
                file     : this.phpInvoker.normalizePlatformAndRuntimePath(file)
            };

            return this.performRequest('classList', parameters);
        }

        /**
         * Retrieves a list of available global constants.
         *
         * @return {Promise}
        */
        getGlobalConstants() {
            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath()
            };

            return this.performRequest('globalConstants', parameters);
        }

        /**
         * Retrieves a list of available global functions.
         *
         * @return {Promise}
        */
        getGlobalFunctions() {
            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath()
            };

            return this.performRequest('globalFunctions', parameters);
        }

        /**
         * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
         *
         * @param {String} className
         *
         * @return {Promise}
        */
        getClassInfo(className) {
            if (!className) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No class name passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath(),
                name     : className
            };

            return this.performRequest('classInfo', parameters);
        }

        /**
         * Resolves a local type in the specified file, based on use statements and the namespace.
         *
         * @param {String}  file
         * @param {Number}  line The line the type is located at. The first line is 1, not 0.
         * @param {String}  type
         * @param {String}  kind The kind of element. Either 'classlike', 'constant' or 'function'.
         *
         * @return {Promise}
        */
        resolveType(file, line, type, kind) {
            if (kind == null) { kind = 'classlike'; }
            if (!file) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No file passed!');
                });
            }

            if (!line) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No line passed!');
                });
            }

            if (!type) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No type passed!');
                });
            }

            if (!kind) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No kind passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath(),
                file     : this.phpInvoker.normalizePlatformAndRuntimePath(file),
                line,
                type,
                kind
            };

            return this.performRequest('resolveType', parameters);
        }

        /**
         * Localizes a type to the specified file, making it relative to local use statements, if possible. If not possible,
         * null is returned.
         *
         * @param {String}  file
         * @param {Number}  line The line the type is located at. The first line is 1, not 0.
         * @param {String}  type
         * @param {String}  kind The kind of element. Either 'classlike', 'constant' or 'function'.
         *
         * @return {Promise}
        */
        localizeType(file, line, type, kind) {
            if (kind == null) { kind = 'classlike'; }
            if (!file) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No file passed!');
                });
            }

            if (!line) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No line passed!');
                });
            }

            if (!type) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No type passed!');
                });
            }

            if (!kind) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No kind passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath(),
                file     : this.phpInvoker.normalizePlatformAndRuntimePath(file),
                line,
                type,
                kind
            };

            return this.performRequest('localizeType', parameters);
        }

        /**
         * Lints the specified file.
         *
         * @param {String}      file
         * @param {String|null} source  The source code of the file to index. May be null if a directory is passed instead.
         * @param {Object}      options Additional options to set. Boolean properties noUnknownClasses, noUnknownMembers,
         *                              noUnknownGlobalFunctions, noUnknownGlobalConstants, noDocblockCorrectness,
         *                              noUnusedUseStatements and noMissingDocumentation are supported.
         *
         * @return {CancellablePromise}
        */
        lint(file, source, options) {
            if (options == null) { options = {}; }
            if (!file) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No file passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath(),
                file     : this.phpInvoker.normalizePlatformAndRuntimePath(file),
                stdin    : true
            };

            if (options.noUnknownClasses === true) {
                parameters['no-unknown-classes'] = true;
            }

            if (options.noUnknownMembers === true) {
                parameters['no-unknown-members'] = true;
            }

            if (options.noUnknownGlobalFunctions === true) {
                parameters['no-unknown-global-functions'] = true;
            }

            if (options.noUnknownGlobalConstants === true) {
                parameters['no-unknown-global-constants'] = true;
            }

            if (options.noDocblockCorrectness === true) {
                parameters['no-docblock-correctness'] = true;
            }

            if (options.noUnusedUseStatements === true) {
                parameters['no-unused-use-statements'] = true;
            }

            if (options.noMissingDocumentation === true) {
                parameters['no-missing-documentation'] = true;
            }

            return this.performRequest('lint', parameters, null, source);
        }

        /**
         * Fetches all available variables at a specific location.
         *
         * @param {String}      file   The path to the file to examine. May be null if the source parameter is passed.
         * @param {String|null} source The source code to search. May be null if a file is passed instead.
         * @param {Number}      offset The character offset into the file to examine.
         *
         * @return {Promise}
        */
        getAvailableVariables(file, source, offset) {
            if (!file) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No file passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database   : this.getIndexDatabasePath(),
                offset,
                charoffset : true,
                file       : this.phpInvoker.normalizePlatformAndRuntimePath(file)
            };

            return this.performRequest('availableVariables', parameters, null, source);
        }

        /**
         * Fetches the contents of the tooltip to display at the specified offset.
         *
         * @param {String}      file   The path to the file to examine.
         * @param {String|null} source The source code to search. May be null if a file is passed instead.
         * @param {Number}      offset The character offset into the file to examine.
         *
         * @return {CancellablePromise}
        */
        tooltip(file, source, offset) {
            if ((file == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Either a path to a file or source code must be passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database   : this.getIndexDatabasePath(),
                offset,
                charoffset : true,
                file       : this.phpInvoker.normalizePlatformAndRuntimePath(file)
            };

            return this.performRequest('tooltip', parameters, null, source);
        }

        /**
         * Fetches signature help for a method or function call.
         *
         * @param {String}      file   The path to the file to examine.
         * @param {String|null} source The source code to search. May be null if a file is passed instead.
         * @param {Number}      offset The character offset into the file to examine.
         *
         * @return {CancellablePromise}
        */
        signatureHelp(file, source, offset) {
            if ((file == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Either a path to a file or source code must be passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database   : this.getIndexDatabasePath(),
                offset,
                charoffset : true,
                file       : this.phpInvoker.normalizePlatformAndRuntimePath(file)
            };

            return this.performRequest('signatureHelp', parameters, null, source);
        }

        /**
         * Fetches definition information for code navigation purposes of the structural element at the specified location.
         *
         * @param {String}      file   The path to the file to examine.
         * @param {String|null} source The source code to search. May be null if a file is passed instead.
         * @param {Number}      offset The character offset into the file to examine.
         *
         * @return {CancellablePromise}
        */
        gotoDefinition(file, source, offset) {
            if ((file == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Either a path to a file or source code must be passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database   : this.getIndexDatabasePath(),
                offset,
                charoffset : true,
                file       : this.phpInvoker.normalizePlatformAndRuntimePath(file)
            };

            return this.performRequest('gotoDefinition', parameters, null, source);
        }

        /**
         * Deduces the resulting types of an expression.
         *
         * @param {String|null} expression        The expression to deduce the type of, e.g. '$this->foo()'. If null, the
         *                                        expression just before the specified offset will be used.
         * @param {String}      file              The path to the file to examine.
         * @param {String|null} source            The source code to search. May be null if a file is passed instead.
         * @param {Number}      offset            The character offset into the file to examine.
         * @param {bool}        ignoreLastElement Whether to remove the last element or not, this is useful when the user
         *                                        is still writing code, e.g. "$this->foo()->b" would normally return the
         *                                        type (class) of 'b', as it is the last element, but as the user is still
         *                                        writing code, you may instead be interested in the type of 'foo()'
         *                                        instead.
         *
         * @return {Promise}
        */
        deduceTypes(expression, file, source, offset, ignoreLastElement) {
            if ((file == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('A path to a file must be passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database   : this.getIndexDatabasePath(),
                offset,
                charoffset : true
            };

            if (file != null) {
                parameters.file = this.phpInvoker.normalizePlatformAndRuntimePath(file);
            }

            if (ignoreLastElement) {
                parameters['ignore-last-element'] = true;
            }

            if (expression != null) {
                parameters.expression = expression;
            }

            return this.performRequest('deduceTypes', parameters, null, source);
        }

        /**
         * Retrieves autocompletion suggestions for a specific location.
         *
         * @param {Number}      offset            The character offset into the file to examine.
         * @param {String}      file              The path to the file to examine.
         * @param {String|null} source            The source code to search. May be null if a file is passed instead.
         *
         * @return {CancellablePromise}
        */
        autocomplete(offset, file, source) {
            if ((file == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('A path to a file must be passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database   : this.getIndexDatabasePath(),
                offset,
                charoffset : true
            };

            if (file != null) {
                parameters.file = this.phpInvoker.normalizePlatformAndRuntimePath(file);
            }

            return this.performRequest('autocomplete', parameters, null, source);
        }

        /**
         * Retrieves a list of document symbols.
         *
         * @param {String} file
         *
         * @return {CancellablePromise}
        */
        getDocumentSymbols(file) {
            if (file == null) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('A path to a file must be passed!');
                });
            }

            if (this.getIndexDatabasePath() == null) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath(),
                file     : this.phpInvoker.normalizePlatformAndRuntimePath(file)
            };

            return this.performRequest('documentSymbols', parameters);
        }

        /**
         * Initializes a project.
         *
         * @return {Promise}
        */
        initialize() {
            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath()
            };

            return this.performRequest('initialize', parameters, null, null);
        }

        /**
         * Shuts the server down entirely.
        */
        exit() {
            const handler = () => {};
            // Ignore promise rejection.

            this.performRequest('exit', {}, null, null).then(handler, handler);

        }

        /**
         * Vacuums a project, cleaning up the index database (e.g. pruning files that no longer exist).
         *
         * @return {Promise}
        */
        vacuum() {
            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath()
            };

            return this.performRequest('vacuum', parameters, null, null);
        }

        /**
         * Tests a project, to see if it is in a properly usable state.
         *
         * @return {Promise}
        */
        test() {
            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            const parameters = {
                database : this.getIndexDatabasePath()
            };

            return this.performRequest('test', parameters, null, null);
        }

        /**
         * Refreshes the specified file or folder. This method is asynchronous and will return immediately.
         *
         * @param {String|Array}  path                   The full path to the file  or folder to refresh. Alternatively,
         *                                              this can be a list of items to index at the same time.
         * @param {String|null}   source                 The source code of the file to index. May be null if a directory is
         *                                              passed instead.
         * @param {Callback|null} progressStreamCallback A method to invoke each time progress streaming data is received.
         * @param {Array}         excludedPaths          A list of paths to exclude from indexing.
         * @param {Array}         fileExtensionsToIndex  A list of file extensions (without leading dot) to index.
         *
         * @return {CancellablePromise}
        */
        reindex(path, source, progressStreamCallback, excludedPaths, fileExtensionsToIndex) {
            let pathsToIndex;
            if (typeof path === 'string') {
                pathsToIndex = [];

                if (path) {
                    pathsToIndex.push(path);
                }

            } else {
                pathsToIndex = path;
            }

            if (path.length === 0) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('No filename passed!');
                });
            }

            if ((this.getIndexDatabasePath() == null)) {
                return new CancellablePromise(function(resolve, reject) {
                    return reject('Request aborted as there is no project active (yet)');
                });
            }

            let progressStreamCallbackWrapper = null;

            const parameters = {
                database : this.getIndexDatabasePath()
            };

            if (progressStreamCallback != null) {
                progressStreamCallbackWrapper = progressStreamCallback;
            }

            pathsToIndex = pathsToIndex.map(path => {
                return this.phpInvoker.normalizePlatformAndRuntimePath(path);
            });

            parameters.source = pathsToIndex;
            parameters.exclude = excludedPaths;
            parameters.extension = fileExtensionsToIndex;

            return this.performRequest('reindex', parameters, progressStreamCallbackWrapper, source);
        }

        /**
         * Sets the name (without path or extension) of the database file to use.
         *
         * @param {String} name
        */
        setIndexDatabaseName(name) {
            return this.indexDatabaseName = sanitize(name);
        }

        /**
         * Retrieves the full path to the database file to use.
         *
         * @return {String|null}
        */
        getIndexDatabasePath() {
            if ((this.indexDatabaseName == null)) {
                return null;
            }

            const folder = this.config.get('storagePath') + path.sep + 'databases';

            mkdirp.sync(folder);

            return this.phpInvoker.normalizePlatformAndRuntimePath(
                folder + path.sep + this.indexDatabaseName + '.sqlite'
            );
        }

        /**
         * @param {String} serverPath
        */
        setServerPath(serverPath) {
            this.serverPath = serverPath;
        }

        /**
         * @return {Boolean}
        */
        getIsActive() {
            return this.isActive;
        }

        /**
         * @param {Boolean} isActive
        */
        setIsActive(isActive) {
            return this.isActive = isActive;
        }
    };
    Proxy.initClass();
    return Proxy;
})());
