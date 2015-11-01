Proxy = require './Proxy'

module.exports =

##*
# Proxy that applies caching on top of its functionality.
##
class CachingProxy extends Proxy
    ###*
     * The cache.
    ###
    cache: null

    ###*
     * Clears the cache.
    ###
    clearCache: () ->
        @cache = {}

    ###*
     * Internal convenience method that wraps a call to a parent method.
     *
     * @param {string}  cacheKey
     * @param {string}  parentMethodName
     * @param {array}   parameters
     * @param {boolean} async
     *
     * @return {Promise|Object}
    ###
    wrapCachedRequestToParent: (cacheKey, parentMethodName, parameters, async) ->
        if not async
            if not @cache[cacheKey]?
                @cache[cacheKey] = CachingProxy.__super__[parentMethodName].apply(this, parameters)

            return @cache[cacheKey]

        else if @cache[cacheKey]?
            return new Promise (resolve, reject) =>
                resolve(@cache[cacheKey])

        else
            return (CachingProxy.__super__[parentMethodName].apply(this, parameters)).then (output) =>
                @cache[cacheKey] = output

                return output

    ###*
     * @inherited
    ###
    getClassList: (async = false) ->
        return @wrapCachedRequestToParent("getClassList", 'getClassList', arguments, async)

    ###*
     * @inherited
    ###
    getGlobalConstants: (async = false) ->
        return @wrapCachedRequestToParent("getGlobalConstants", 'getGlobalConstants', arguments, async)

    ###*
     * @inherited
    ###
    getGlobalFunctions: (async = false) ->
        return @wrapCachedRequestToParent("getGlobalFunctions", 'getGlobalFunctions', arguments, async)

    ###*
     * @inherited
    ###
    getClassInfo: (className, async = false) ->
        return @wrapCachedRequestToParent("getClassInfo-#{className}", 'getClassInfo', arguments, async)

    ###*
     * @inherited
    ###
    autocomplete: (className, name, async = false) ->
        return @wrapCachedRequestToParent("autocomplete-#{className}-#{name}", 'autocomplete', arguments, async)

    ###*
     * @inherited
    ###
    getDocParams: (className, functionName, async = false) ->
        return @wrapCachedRequestToParent("getDocParams-#{className}-#{functionName}", 'getDocParams', arguments, async)

    ###*
     * @inherited
    ###
    reindex: (filename) ->
        return super(filename).then (output) =>
            @clearCache()

            return output
