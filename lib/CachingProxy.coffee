md5 = require 'md5'

Proxy = require './Proxy'

module.exports =

##*
# Proxy that applies caching on top of its functionality.
##
class CachingProxy extends Proxy
    ###*
     * @var {Object}
    ###
    cache: null

    ###*
     * @var {Object}
    ###
    pendingPromises: null

    ###*
     * @inherited
    ###
    constructor: (@config) ->
        super(@config)

        @cache = {}
        @pendingPromises = {}

    ###*
     * Clears the cache.
    ###
    clearCache: () ->
        @cache = {}
        @pendingPromises = {}

    ###*
     * Internal convenience method that wraps a call to a parent method.
     *
     * @param {String}  cacheKey
     * @param {String}  parentMethodName
     * @param {Array}   parameters
     *
     * @return {Promise|Object}
    ###
    wrapCachedRequestToParent: (cacheKey, parentMethodName, parameters) ->
        if cacheKey of @cache
            return new Promise (resolve, reject) =>
                resolve(@cache[cacheKey])

        else if cacheKey of @pendingPromises
            # If a query with the same parameters (promise) is still running, don't start another one but just await
            # the results of the existing promise.
            return @pendingPromises[cacheKey]

        else
            successHandler = (output) =>
                delete @pendingPromises[cacheKey]

                @cache[cacheKey] = output

                return output

            @pendingPromises[cacheKey] = CachingProxy.__super__[parentMethodName].apply(this, parameters).then(successHandler)

            return @pendingPromises[cacheKey]

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
    getNamespaceList: () ->
        return @wrapCachedRequestToParent("getNamespaceList", 'getNamespaceList', arguments)

    ###*
     * @inherited
    ###
    getNamespaceListForFile: (file) ->
        return @wrapCachedRequestToParent("getNamespaceListForFile-#{file}", 'getNamespaceListForFile', arguments)

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
    resolveType: (file, line, type, kind = 'classlike') ->
        return @wrapCachedRequestToParent("resolveType-#{file}-#{line}-#{type}-#{kind}", 'resolveType', arguments)

    ###*
     * @inherited
    ###
    localizeType: (file, line, type, kind = 'classlike') ->
        return @wrapCachedRequestToParent("localizeType-#{file}-#{line}-#{type}-#{kind}", 'localizeType', arguments)

    ###*
     * @inherited
    ###
    lint: (file, source, options) ->
        # md5 may sound expensive, but it's not as expensive as spawning an extra process that parses PHP code.
        sourceKey = if source? then md5(source) else null

        optionsKey = JSON.stringify(options)

        return @wrapCachedRequestToParent("lint-#{file}-#{sourceKey}-#{optionsKey}", 'lint', arguments)

    ###*
     * @inherited
    ###
    getAvailableVariables: (file, source, offset) ->
        sourceKey = if source? then md5(source) else null

        return @wrapCachedRequestToParent("getAvailableVariables-#{file}-#{sourceKey}-#{offset}", 'getAvailableVariables', arguments)

    ###*
     * @inherited
    ###
    deduceTypes: (expression, file, source, offset, ignoreLastElement) ->
        sourceKey = if source? then md5(source) else null

        return @wrapCachedRequestToParent("deduceTypes-#{expression}-#{file}-#{sourceKey}-#{offset}-#{ignoreLastElement}", 'deduceTypes', arguments)

    ###*
     * @inherited
    ###
    autocomplete: (offset, file, source) ->
        sourceKey = if source? then md5(source) else null

        return @wrapCachedRequestToParent("autocomplete-#{offset}-#{file}-#{sourceKey}", 'autocomplete', arguments)

    ###*
     * @inherited
    ###
    tooltip: (file, source, offset) ->
        sourceKey = if source? then md5(source) else null

        return @wrapCachedRequestToParent("tooltip-#{file}-#{sourceKey}-#{offset}", 'tooltip', arguments)

    ###*
     * @inherited
    ###
    signatureHelp: (file, source, offset) ->
        sourceKey = if source? then md5(source) else null

        return @wrapCachedRequestToParent("signatureHelp-#{file}-#{sourceKey}-#{offset}", 'signatureHelp', arguments)

    ###*
     * @inherited
    ###
    gotoDefinition: (file, source, offset) ->
        sourceKey = if source? then md5(source) else null

        return @wrapCachedRequestToParent("gotoDefinition-#{file}-#{sourceKey}-#{offset}", 'gotoDefinition', arguments)

    ###*
     * @inherited
    ###
    initialize: () ->
        return super().then (output) =>
            @clearCache()

            return output

    ###*
     * @inherited
    ###
    vacuum: () ->
        return super().then (output) =>
            @clearCache()

            return output

    ###*
     * @inherited
    ###
    test: () ->
        return super()

    ###*
     * @inherited
    ###
    reindex: (path, source, progressStreamCallback, excludedPaths, fileExtensionsToIndex) ->
        return super(path, source, progressStreamCallback, excludedPaths, fileExtensionsToIndex).then (output) =>
            @clearCache()

            return output
