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

        if os.type() == "Windows_NT"
            parameter = '"' + parameter.replace(/"/g, '""') + '"'

        else
            parameter = parameter.replace(/\\/g, '\\\\')
            parameter = parameter.replace(/\ /g, '\\ ')

        return parameter
