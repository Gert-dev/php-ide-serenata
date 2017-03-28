module.exports =

##*
# Promise that can be canceled (externally).
#
# The promise or operation is not actually canceled, the promise just automatically fails and will not resolve anymore.
##
class CancelablePromise
    ###*
     * @var {Promise} promise
    ###
    promise: null

    ###*
     * @var {Boolean}
    ###
    isCanceled: false

    ###*
     * @param {Promise} promise
    ###
    constructor: (@promise) ->

    ###*
     * @param {Callback} successHandler
     * @param {Callback} failureHandler
     *
     * @return {Promise}
    ###
    then: (successHandler, failureHandler) ->
        return new Promise (resolve, reject) =>
            wrappingSuccessHandler = (args...) =>
                if not @isCanceled
                    resolve(successHandler(args...))

            wrappingFailureHandler = (args...) =>
                if not @isCanceled
                    reject(failureHandler(args...))

            return @promise.then(wrappingSuccessHandler, wrappingFailureHandler)

    ###*
     * @param {Callback} failureHandler
     *
     * @return {Promise}
    ###
    catch: (failureHandler) ->
        return @promise.catch(failureHandler)

    ###*
     *
    ###
    cancel: () ->
        @isCanceled = true
