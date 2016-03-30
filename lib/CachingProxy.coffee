md5 = require 'md5'

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
     * @inherited
    ###
    constructor: (@config) ->
        super(@config)

        @cache = {}

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
            if cacheKey not of @cache
                @cache[cacheKey] = CachingProxy.__super__[parentMethodName].apply(this, parameters)

            return @cache[cacheKey]

        else if cacheKey of @cache
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
    getClassListForFile: (file, async = false) ->
        return @wrapCachedRequestToParent("getClassListForFile-#{file}", 'getClassListForFile', arguments, async)

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
    resolveType: (file, line, type, async = false) ->
        return @wrapCachedRequestToParent("resolveType-#{file}-#{line}-#{type}", 'resolveType', arguments, async)

    ###*
     * @inherited
    ###
    semanticLint: (file, source, async = false) ->
        # md5 may sound expensive, but it's not as expensive as spawning an extra process that parses PHP code.
        sourceKey = if source then md5(source) else null

        return @wrapCachedRequestToParent("semanticLint-#{file}-#{sourceKey}", 'semanticLint', arguments, async)

    ###*
     * @inherited
    ###
    getAvailableVariables: (file, source, offset, async = false) ->
        sourceKey = if source then md5(source) else null

        return @wrapCachedRequestToParent("getAvailableVariables-#{file}-#{sourceKey}-#{offset}", 'getAvailableVariables', arguments, async)

    ###*
     * @inherited
    ###
    getVariableType: (name, file, source, offset, async = false) ->
        sourceKey = if source then md5(source) else null

        return @wrapCachedRequestToParent("getVariableType-#{name}-#{file}-#{sourceKey}-#{offset}", 'getVariableType', arguments, async)

    ###*
     * @inherited
    ###
    deduceType: (parts, file, source, offset, async = false) ->
        sourceKey = if source then md5(source) else null

        partsKey = ''

        for part in parts
            partsKey += part

        return @wrapCachedRequestToParent("deduceType-#{partsKey}#{file}-#{sourceKey}-#{offset}", 'deduceType', arguments, async)

    ###*
     * @inherited
    ###
    reindex: (path, source, progressStreamCallback) ->
        return super(path, source, progressStreamCallback).then (output) =>
            @clearCache()

            return output
