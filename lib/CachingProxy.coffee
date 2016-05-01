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
     *
     * @return {Promise|Object}
    ###
    wrapCachedRequestToParent: (cacheKey, parentMethodName, parameters) ->
        if cacheKey of @cache
            return new Promise (resolve, reject) =>
                resolve(@cache[cacheKey])

        else
            return (CachingProxy.__super__[parentMethodName].apply(this, parameters)).then (output) =>
                @cache[cacheKey] = output

                return output

    ###*
     * @inherited
    ###
    getClassList: () ->
        return @wrapCachedRequestToParent("getClassList", 'getClassList', arguments)

    ###*
     * @inherited
    ###
    getClassListForFile: (file) ->
        return @wrapCachedRequestToParent("getClassListForFile-#{file}", 'getClassListForFile', arguments)

    ###*
     * @inherited
    ###
    getGlobalConstants: () ->
        return @wrapCachedRequestToParent("getGlobalConstants", 'getGlobalConstants', arguments)

    ###*
     * @inherited
    ###
    getGlobalFunctions: () ->
        return @wrapCachedRequestToParent("getGlobalFunctions", 'getGlobalFunctions', arguments)

    ###*
     * @inherited
    ###
    getClassInfo: (className) ->
        return @wrapCachedRequestToParent("getClassInfo-#{className}", 'getClassInfo', arguments)

    ###*
     * @inherited
    ###
    resolveType: (file, line, type) ->
        return @wrapCachedRequestToParent("resolveType-#{file}-#{line}-#{type}", 'resolveType', arguments)

    ###*
     * @inherited
    ###
    localizeType: (file, line, type) ->
        return @wrapCachedRequestToParent("localizeType-#{file}-#{line}-#{type}", 'localizeType', arguments)

    ###*
     * @inherited
    ###
    semanticLint: (file, source, options) ->
        # md5 may sound expensive, but it's not as expensive as spawning an extra process that parses PHP code.
        sourceKey = if source? then md5(source) else null

        optionsKey = JSON.stringify(options)

        return @wrapCachedRequestToParent("semanticLint-#{file}-#{sourceKey}-#{optionsKey}", 'semanticLint', arguments)

    ###*
     * @inherited
    ###
    getAvailableVariables: (file, source, offset) ->
        sourceKey = if source? then md5(source) else null

        return @wrapCachedRequestToParent("getAvailableVariables-#{file}-#{sourceKey}-#{offset}", 'getAvailableVariables', arguments)

    ###*
     * @inherited
    ###
    getVariableType: (name, file, source, offset) ->
        sourceKey = if source? then md5(source) else null

        return @wrapCachedRequestToParent("getVariableType-#{name}-#{file}-#{sourceKey}-#{offset}", 'getVariableType', arguments)

    ###*
     * @inherited
    ###
    deduceType: (parts, file, source, offset) ->
        sourceKey = if source? then md5(source) else null

        partsKey = ''

        for part in parts
            partsKey += part

        return @wrapCachedRequestToParent("deduceType-#{partsKey}#{file}-#{sourceKey}-#{offset}", 'deduceType', arguments)

    ###*
     * @inherited
    ###
    reindex: (path, source, progressStreamCallback) ->
        return super(path, source, progressStreamCallback).then (output) =>
            @clearCache()

            return output
