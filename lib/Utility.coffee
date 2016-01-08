
module.exports =
    ###*
     * Escapes slashes for the specified text.
     *
     * @param {string} text
     *
     * @return {string}
    ###
    escapeSeparators: (text) ->
        return '' if not text

        return text.replace(/\\/g, '\\\\')

    ###*
     * Normalizes separators to forward slashes.
     *
     * @param {string} text
     *
     * @return {string}
    ###
    normalizeSeparators: (text) ->
        return '' if not text

        return text.replace(/\\/g, '/')

    ###*
     * Escapes the specified path by replacing spaces with a backslash.
     *
     * @param {string} text
     *
     * @return {string}
    ###
    escapePath: (text) ->
      return '' if not text

      return text.replace(/\ /g, '\\ ')
