child_process = require "child_process"

module.exports =

##*
# Tests configurations to see if they are properly usable.
##
class ConfigTester
    ###*
     * Constructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->

    ###*
     * Tests the user's configuration.
     *
     * @return {boolean}
    ###
    test: () ->
        response = child_process.spawnSync(@config.get('core.phpCommand'), ["-v"])

        if response.status = null or response.status != 0
            return false

        return true
