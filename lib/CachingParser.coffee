Parser = require './Parser'

module.exports =

##*
# Parser that applies caching on top of its functionality.
##
class CachingParser extends Parser
    ###*
     * The cache.
    ###
    cache: null

    ###*
     * @inherited
    ###
    constructor: (@proxy) ->
        super(@proxy)

        @cache = {}

    ###*
     * Clears the cache for the specified file.
     *
     * @param {string} filePath
    ###
    clearCache: (filePath) ->
        @cache[filePath] = {}

    ###*
     * Internal convenience method that wraps a call to a parent method.
     *
     * @param {string}  path
     * @param {string}  cacheKey
     * @param {string}  parentMethodName
     * @param {array}   parameters
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    wrapCachedRequestToParent: (path, cacheKey, parentMethodName, parameters, async) ->
        if not @cache[path]
            @cache[path] = {}

        if not async
            if not @cache[path][cacheKey]?
                @cache[path][cacheKey] = CachingParser.__super__[parentMethodName].apply(this, parameters)

            return @cache[path][cacheKey]

        else if @cache[path][cacheKey]?
            return new Promise (resolve, reject) =>
                resolve(@cache[path][cacheKey])

        else
            return (CachingParser.__super__[parentMethodName].apply(this, parameters)).then (output) =>
                @cache[path][cacheKey] = output

                return output

    ###*
     * @inherited
    ###
    determineFullClassName: (editor, className = null) ->
        return @wrapCachedRequestToParent(editor.getPath(), "determineFullClassName-#{className}", 'determineFullClassName', arguments, false)
