
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
     *
     *
     * @param {string} text
     *
     * @return {string}
    ###
    escapePath: (text) ->
      return '' if not text
      
      return text.replace(' ','\\ ')
