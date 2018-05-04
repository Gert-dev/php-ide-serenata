module.exports =

class FunctionBuilder
    ###*
     * The access modifier (null if none).
    ###
    accessModifier: null

    ###*
     * Whether the method is static or not.
    ###
    isStatic: false

    ###*
     * Whether the method is abstract or not.
    ###
    isAbstract: null

    ###*
     * The name of the function.
    ###
    name: null

    ###*
     * The return type of the function. This could be set when generating PHP >= 7 methods.
    ###
    returnType: null

    ###*
     * The parameters of the function (a list of objects).
    ###
    parameters: null

    ###*
     * A list of statements to place in the body of the function.
    ###
    statements: null

    ###*
     * The tab text to insert on each line.
    ###
    tabText: ''

    ###*
     * The indentation level.
    ###
    indentationLevel: null

    ###*
     * The indentation level.
     *
     * @var {Number|null}
    ###
    maxLineLength: null

    ###*
     * Constructor.
    ###
    constructor: () ->
        @parameters = []
        @statements = []

    ###*
     * Makes the method public.
     *
     * @return {FunctionBuilder}
    ###
    makePublic: () ->
        @accessModifier = 'public'
        return this

    ###*
     * Makes the method private.
     *
     * @return {FunctionBuilder}
    ###
    makePrivate: () ->
        @accessModifier = 'private'
        return this

    ###*
     * Makes the method protected.
     *
     * @return {FunctionBuilder}
    ###
    makeProtected: () ->
        @accessModifier = 'protected'
        return this

    ###*
     * Makes the method global (i.e. no access modifier is added).
     *
     * @return {FunctionBuilder}
    ###
    makeGlobal: () ->
        @accessModifier = null
        return this

    ###*
     * Sets whether the method is static or not.
     *
     * @param {bool} isStatic
     *
     * @return {FunctionBuilder}
    ###
    setIsStatic: (@isStatic) ->
        return this

    ###*
     * Sets whether the method is abstract or not.
     *
     * @param {bool} isAbstract
     *
     * @return {FunctionBuilder}
    ###
    setIsAbstract: (@isAbstract) ->
        return this

    ###*
     * Sets the name of the function.
     *
     * @param {String} name
     *
     * @return {FunctionBuilder}
    ###
    setName: (@name) ->
        return this

    ###*
     * Sets the return type.
     *
     * @param {String|null} returnType
     *
     * @return {FunctionBuilder}
    ###
    setReturnType: (@returnType) ->
        return this

    ###*
     * Sets the parameters to add.
     *
     * @param {Array} parameters
     *
     * @return {FunctionBuilder}
    ###
    setParameters: (@parameters) ->
        return this

    ###*
     * Adds a parameter to the parameter list.
     *
     * @param {Object} parameter
     *
     * @return {FunctionBuilder}
    ###
    addParameter: (parameter) ->
        @parameters.push(parameter)
        return this

    ###*
     * Sets the statements to add.
     *
     * @param {Array} statements
     *
     * @return {FunctionBuilder}
    ###
    setStatements: (@statements) ->
        return this

    ###*
     * Adds a statement to the body of the function.
     *
     * @param {String} statement
     *
     * @return {FunctionBuilder}
    ###
    addStatement: (statement) ->
        @statements.push(statement)
        return this

    ###*
     * Sets the tab text to prepend to each line.
     *
     * @param {String} tabText
     *
     * @return {FunctionBuilder}
    ###
    setTabText: (@tabText) ->
        return this

    ###*
     * Sets the indentation level to use. The tab text is repeated this many times for each line.
     *
     * @param {Number} indentationLevel
     *
     * @return {FunctionBuilder}
    ###
    setIndentationLevel: (@indentationLevel) ->
        return this

    ###*
     * Sets the maximum length a single line may occupy. After this, text will wrap.
     *
     * This primarily influences parameter lists, which will automatically be split over multiple lines if the parameter
     * list would otherwise exceed the maximum length.
     *
     * @param {Number|null} maxLineLength The length or null to disable the maximum.
     *
     * @return {FunctionBuilder}
    ###
    setMaxLineLength: (@maxLineLength) ->
        return this

    ###*
     * Sets the parameters of the builder based on raw method data from the base service.
     *
     * @param {Object} data
     *
     * @return {FunctionBuilder}
    ###
    setFromRawMethodData: (data) ->
        if data.isPublic
            @makePublic()

        else if data.isProtected
            @makeProtected()

        else if data.isPrivate
            @makePrivate()

        else
            @makeGlobal()

        @setName(data.name)
        @setIsStatic(data.isStatic)
        @setIsAbstract(data.isAbstract)
        @setReturnType(data.returnTypeHint)

        parameters = []

        for parameter in data.parameters
            parameters.push({
                name         : '$' + parameter.name
                typeHint     : parameter.typeHint
                isVariadic   : parameter.isVariadic
                isReference  : parameter.isReference
                defaultValue : parameter.defaultValue
            })

        @setParameters(parameters)

        return this

    ###*
     * Builds the method using the preconfigured settings.
     *
     * @return {String}
    ###
    build: () =>
        output = ''

        signature = @buildSignature(false)

        if @maxLineLength? and signature.length > @maxLineLength
            output += @buildSignature(true)
            output += " {\n"

        else
            output += signature + "\n"
            output += @buildLine('{')

        for statement in @statements
            output += @tabText + @buildLine(statement)

        output += @buildLine('}')

        return output

    ###*
     * @param {Boolean} isMultiLine
     *
     * @return {String}
    ###
    buildSignature: (isMultiLine) ->
        signatureLine = ''

        if @isAbstract
            signatureLine += 'abstract '

        if @accessModifier?
            signatureLine += "#{@accessModifier} "

        if @isStatic
            signatureLine += 'static '

        signatureLine += "function #{@name}("

        parameters = []

        for parameter in @parameters
            parameterText = ''

            if parameter.typeHint?
                parameterText += "#{parameter.typeHint} "

            if parameter.isVariadic
                parameterText += '...'

            if parameter.isReference
                parameterText += '&'

            parameterText += "#{parameter.name}"

            if parameter.defaultValue?
                parameterText += " = #{parameter.defaultValue}"

            parameters.push(parameterText)

        if not isMultiLine
            signatureLine += parameters.join(', ')
            signatureLine += ')'

            signatureLine = @addTabText(signatureLine)

        else
            signatureLine = @buildLine(signatureLine)

            for i, parameter of parameters
                if i < (parameters.length - 1)
                    parameter += ','

                signatureLine += @buildLine(parameter, @indentationLevel + 1)

            signatureLine += @addTabText(')')

        if @returnType?
            signatureLine += ": #{@returnType}"

        return signatureLine

    ###*
     * @param {String}      content
     * @param {Number|null} indentationLevel
     *
     * @return {String}
    ###
    buildLine: (content, indentationLevel = null) ->
        return @addTabText(content, indentationLevel) + "\n"

    ###*
     * @param {String}      content
     * @param {Number|null} indentationLevel
     *
     * @return {String}
    ###
    addTabText: (content, indentationLevel = null) ->
        if not indentationLevel?
            indentationLevel = @indentationLevel

        tabText = @tabText.repeat(indentationLevel)

        return "#{tabText}#{content}"
