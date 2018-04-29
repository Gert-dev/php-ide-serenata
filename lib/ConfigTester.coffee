child_process = require "child_process"

module.exports =

##*
# Tests the user's PHP setup to see if it is properly usable.
##
class ConfigTester
    ###*
     * Constructor.
     *
     * @param {PhpInvoker} phpInvoker
    ###
    constructor: (@phpInvoker) ->

    ###*
     * @return {Promise}
    ###
    test: () ->
        return new Promise (resolve, reject) =>
            process = @phpInvoker.invoke(['-v'])
            process.on 'close', (code) =>
                if code == 0
                    resolve(true)

                resolve(false)
