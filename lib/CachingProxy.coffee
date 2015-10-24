Proxy = require './Proxy.coffee'

module.exports =

##*
# Proxy that applies caching on top of its functionality.
##
class CachingProxy extends Proxy
    ###*
     * The cache.
    ###
    cache: {}

    ###*
     * Clears the cache.
    ###
    clearCache: () ->
        @cache = {}

    ###*
     * @inherited
    ###
    getConstants: () ->
        cacheKey = 'constants'

        if not @cache[cacheKey]?
            @cache[cacheKey] = super()

        return @cache[cacheKey]

    ###*
     * @inherited
    ###
    getGlobalFunctions: () ->
        cacheKey = 'functions'

        if not @cache[cacheKey]?
            @cache[cacheKey] = super()

        return @cache[cacheKey]

    ###*
     * @inherited
    ###
    getClassInfo: (className) ->
        cacheKey = "members-#{className}"

        if not @cache[cacheKey]?
            @cache[cacheKey] = super(className)

        return @cache[cacheKey]

    ###*
     * @inherited
    ###
    autocomplete: (className, name) ->
        cacheKey = "autocompletion-#{className}-#{name}"

        if not @cache[cacheKey]?
            @cache[cacheKey] = super(className, name)

        return @cache[cacheKey]

    ###*
     * @inherited
    ###
    getDocParams: (className, functionName) ->
        cacheKey = "doc-params-#{className}-#{functionName}"

        if not @cache[cacheKey]?
            @cache[cacheKey] = super(className, functionName)

        return @cache[cacheKey]
