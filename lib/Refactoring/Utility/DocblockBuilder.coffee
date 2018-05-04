module.exports =

class DocblockBuilder
    ###*
     * @param  {Array}       parameters
     * @param  {String|null} returnType
     * @param  {boolean}     generateDescriptionPlaceholders
     * @param  {String}      tabText
     *
     * @return {String}
    ###
    buildForMethod: (parameters, returnType, generateDescriptionPlaceholders = true, tabText = '') =>
        lines = []

        if generateDescriptionPlaceholders
            lines.push("[Short description of the method]")

        if parameters.length > 0
            descriptionPlaceholder = ""

            if generateDescriptionPlaceholders
                lines.push('')

                descriptionPlaceholder = " [Description]"

            # Determine the necessary padding.
            parameterTypeLengths = parameters.map (item) ->
                return if item.type then item.type.length else 0

            parameterNameLengths = parameters.map (item) ->
                return if item.name then item.name.length else 0

            longestTypeLength = Math.max(parameterTypeLengths...)
            longestNameLength = Math.max(parameterNameLengths...)

            # Generate parameter lines.
            for parameter in parameters
                typePadding     = longestTypeLength - parameter.type.length
                variablePadding = longestNameLength - parameter.name.length

                type     = parameter.type + ' '.repeat(typePadding)
                variable = parameter.name + ' '.repeat(variablePadding)

                lines.push("@param #{type} #{variable}#{descriptionPlaceholder}")

        if returnType? and returnType != 'void'
            if generateDescriptionPlaceholders or parameters.length > 0
                lines.push('')

            lines.push("@return #{returnType}")

        return @buildByLines(lines, tabText)

    ###*
     * @param  {String|null} type
     * @param  {boolean}     generateDescriptionPlaceholders
     * @param  {String}      tabText
     *
     * @return {String}
    ###
    buildForProperty: (type, generateDescriptionPlaceholders = true, tabText = '') =>
        lines = []

        if generateDescriptionPlaceholders
            lines.push("[Short description of the property]")
            lines.push('')

        lines.push("@var #{type}")

        return @buildByLines(lines, tabText)

    ###*
     * @param  {Array}  lines
     * @param  {String} tabText
     *
     * @return {String}
    ###
    buildByLines: (lines, tabText = '') =>
        docs = @buildLine("/**", tabText)

        if lines.length == 0
            # Ensure we always have at least one line.
            lines.push('')

        for line in lines
            docs += @buildDocblockLine(line, tabText)

        docs += @buildLine(" */", tabText)

        return docs

    ###*
     * @param {String} content
     * @param {String} tabText
     *
     * @return {String}
    ###
    buildDocblockLine: (content, tabText = '') ->
        content = " * #{content}"

        return @buildLine(content.trimRight(), tabText)

    ###*
     * @param {String}  content
     * @param {String}  tabText
     *
     * @return {String}
    ###
    buildLine: (content, tabText = '') ->
        return "#{tabText}#{content}\n"
