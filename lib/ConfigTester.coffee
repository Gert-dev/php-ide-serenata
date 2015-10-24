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
     * Tests the user's PHP and Composer configuration.
    ###
    test: () ->
        response = child_process.spawnSync(@config.get('phpCommand'), ["-v"])

        if response.status = null or response.status != 0
            return false

        # Test Composer.
        response = child_process.spawnSync(@config.get('phpCommand'), [@config.get('composerCommand'), "--version"])

        if response.status = null or response.status != 0
            response = child_process.spawnSync(@config.get('composerCommand'), ["--version"])

            # Try executing Composer directly.
            if response.status = null or response.status != 0
                return false

        return true
