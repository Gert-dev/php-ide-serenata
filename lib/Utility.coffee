
module.exports =
    ###*
     * Escapes slashes for the specified text.
     *
     * @param {string} text
     *
     * @return {string}
    ###
    escapeSeparators: (text) ->
        return text.replace(/\\/g, '\\\\')

    ###*
     * Normalizes separators to forward slashes.
     *
     * @param {string} text
     *
     * @return {string}
    ###
    normalizeSeparators: (text) ->
        return text.replace(/\\/g, '/')
