os = require 'os'

module.exports =
    ###*
     * Escapes the specified parameter for use on the command line.
     *
     * @param {string} parameter
     *
     * @return {string}
    ###
    escapeShellParameter: (parameter) ->
        return parameter if not parameter

        # See also https://github.com/Gert-dev/php-integrator-base/pull/53.
        #if os.type() == "Windows_NT"
            #parameter = '"' + parameter.replace(/"/g, '""') + '"'

        #else
        if os.type() != "Windows_NT"
            # parameter = parameter.replace(/\\/g, '\\\\')
            # TODO: Could be that this is no longer necessary and causes problems.
            parameter = parameter.replace(/\ /g, '\\ ')

        return parameter
