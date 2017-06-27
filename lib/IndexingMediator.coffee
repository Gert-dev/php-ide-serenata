Popover         = require './Widgets/Popover'

module.exports =

##*
# A mediator that mediates between classes that need to do indexing and keep updated about the results.
##
class IndexingMediator
    ###*
     * The proxy to use to contact the PHP side.
    ###
    proxy: null

    ###*
     * The emitter to use to emit indexing events.
    ###
    indexingEventEmitter: null

    ###*
     * Constructor.
     *
     * @param {CachingProxy} proxy
     * @param {Emitter}      indexingEventEmitter
    ###
    constructor: (@proxy, @indexingEventEmitter) ->

    ###*
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
    ###
    reindex: (path, source, excludedPaths, fileExtensionsToIndex) ->
        return new Promise (resolve, reject) =>
            @indexingEventEmitter.emit('php-integrator-base:indexing-started', {
                path : path
            })

            successHandler = (output) =>
                @indexingEventEmitter.emit('php-integrator-base:indexing-finished', {
                    output : output
                    path   : path
                    source : source
                })

                resolve(output)

            failureHandler = (error) =>
                @indexingEventEmitter.emit('php-integrator-base:indexing-failed', {
                    error  : error
                    path   : path
                    source : source
                })

                reject(error)

            progressStreamCallback = (progress) =>
                progress = parseFloat(progress)

                if not isNaN(progress)
                    @indexingEventEmitter.emit('php-integrator-base:indexing-progress', {
                        path       : path
                        percentage : progress
                    })

            return @proxy.reindex(
                path,
                source,
                progressStreamCallback,
                excludedPaths,
                fileExtensionsToIndex
            ).then(successHandler, failureHandler)

    ###*
     * Initializes the project.
     *
     * @return {Promise}
    ###
    initialize: () ->
        return @proxy.initialize()

    ###*
     * Vacuums the project.
     *
     * @return {Promise}
    ###
    vacuum: () ->
        return @proxy.vacuum()

    ###*
     * Attaches a callback to indexing started event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains a 'path' property.
     *
     * @return {Disposable}
    ###
    onDidStartIndexing: (callback) ->
        @indexingEventEmitter.on('php-integrator-base:indexing-started', callback)

    ###*
     * Attaches a callback to indexing progress event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains a 'path' and a 'percentage' property.
     *
     * @return {Disposable}
    ###
    onDidIndexingProgress: (callback) ->
        @indexingEventEmitter.on('php-integrator-base:indexing-progress', callback)

    ###*
     * Attaches a callback to indexing finished event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains an 'output' and a 'path' property.
     *
     * @return {Disposable}
    ###
    onDidFinishIndexing: (callback) ->
        @indexingEventEmitter.on('php-integrator-base:indexing-finished', callback)

    ###*
     * Attaches a callback to indexing failed event. The returned disposable can be used to detach your event handler.
     *
     * @param {Callback} callback A callback that takes one parameter which contains an 'error' and a 'path' property.
     *
     * @return {Disposable}
    ###
    onDidFailIndexing: (callback) ->
        @indexingEventEmitter.on('php-integrator-base:indexing-failed', callback)
