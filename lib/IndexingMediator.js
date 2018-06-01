/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let IndexingMediator;
const Popover = require('./Widgets/Popover');

const CancellablePromise = require('./CancellablePromise');

module.exports =

//#*
// A mediator that mediates between classes that need to do indexing and keep updated about the results.
//#
(IndexingMediator = (function() {
    IndexingMediator = class IndexingMediator {
        static initClass() {
            /**
             * The proxy to use to contact the PHP side.
            */
            this.prototype.proxy = null;
    
            /**
             * The emitter to use to emit indexing events.
            */
            this.prototype.indexingEventEmitter = null;
        }

        /**
         * Constructor.
         *
         * @param {CachingProxy} proxy
         * @param {Emitter}      indexingEventEmitter
        */
        constructor(proxy, indexingEventEmitter) {
            this.proxy = proxy;
            this.indexingEventEmitter = indexingEventEmitter;
        }

        /**
         * Refreshes the specified file or folder. This method is asynchronous and will return immediately.
         *
         * @param {String|Array}  path                   The full path to the file  or folder to refresh. Alternatively,
         *                                              this can be a list of items to index at the same time.
         * @param {String|null}   source                 The source code of the file to index. May be null if a directory is
         *                                              passed instead.
         * @param {Array}         excludedPaths          A list of paths to exclude from indexing.
         * @param {Array}         fileExtensionsToIndex  A list of file extensions (without leading dot) to index.
         *
         * @return {Promise}
        */
        reindex(path, source, excludedPaths, fileExtensionsToIndex) {
            let reindexCancellablePromise = null;

            const cancelHandler = () => {
                if ((reindexCancellablePromise == null)) { return; }

                return reindexCancellablePromise.cancel();
            };

            const executor = (resolve, reject) => {
                this.indexingEventEmitter.emit('php-ide-serenata:indexing-started', {
                    path
                });

                const successHandler = output => {
                    this.indexingEventEmitter.emit('php-ide-serenata:indexing-finished', {
                        output,
                        path,
                        source
                    });

                    return resolve(output);
                };

                const failureHandler = error => {
                    this.indexingEventEmitter.emit('php-ide-serenata:indexing-failed', {
                        error,
                        path,
                        source
                    });

                    return reject(error);
                };

                const progressStreamCallback = progress => {
                    progress = parseFloat(progress);

                    if (!isNaN(progress)) {
                        return this.indexingEventEmitter.emit('php-ide-serenata:indexing-progress', {
                            path,
                            percentage : progress
                        });
                    }
                };

                reindexCancellablePromise = this.proxy.reindex(
                    path,
                    source,
                    progressStreamCallback,
                    excludedPaths,
                    fileExtensionsToIndex
                );

                return reindexCancellablePromise.then(successHandler, failureHandler);
            };

            return new CancellablePromise(executor, cancelHandler);
        }

        /**
         * Initializes the project.
         *
         * @return {Promise}
        */
        initialize() {
            return this.proxy.initialize();
        }

        /**
         * Vacuums the project.
         *
         * @return {Promise}
        */
        vacuum() {
            return this.proxy.vacuum();
        }

        /**
         * Attaches a callback to indexing started event. The returned disposable can be used to detach your event handler.
         *
         * @param {Callback} callback A callback that takes one parameter which contains a 'path' property.
         *
         * @return {Disposable}
        */
        onDidStartIndexing(callback) {
            return this.indexingEventEmitter.on('php-ide-serenata:indexing-started', callback);
        }

        /**
         * Attaches a callback to indexing progress event. The returned disposable can be used to detach your event handler.
         *
         * @param {Callback} callback A callback that takes one parameter which contains a 'path' and a 'percentage' property.
         *
         * @return {Disposable}
        */
        onDidIndexingProgress(callback) {
            return this.indexingEventEmitter.on('php-ide-serenata:indexing-progress', callback);
        }

        /**
         * Attaches a callback to indexing finished event. The returned disposable can be used to detach your event handler.
         *
         * @param {Callback} callback A callback that takes one parameter which contains an 'output' and a 'path' property.
         *
         * @return {Disposable}
        */
        onDidFinishIndexing(callback) {
            return this.indexingEventEmitter.on('php-ide-serenata:indexing-finished', callback);
        }

        /**
         * Attaches a callback to indexing failed event. The returned disposable can be used to detach your event handler.
         *
         * @param {Callback} callback A callback that takes one parameter which contains an 'error' and a 'path' property.
         *
         * @return {Disposable}
        */
        onDidFailIndexing(callback) {
            return this.indexingEventEmitter.on('php-ide-serenata:indexing-failed', callback);
        }
    };
    IndexingMediator.initClass();
    return IndexingMediator;
})());
