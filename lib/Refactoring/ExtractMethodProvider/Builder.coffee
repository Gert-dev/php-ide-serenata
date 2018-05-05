{Range} = require 'atom'

module.exports =

class Builder
    ###*
     * The body of the new method that will be shown in the preview area.
     *
     * @type {String}
    ###
    methodBody: ''

    ###*
     * The tab string that is used by the current editor.
     *
     * @type {String}
    ###
    tabText: ''

    ###*
     * @type {Number}
    ###
    indentationLevel: null

    ###*
     * @type {Number}
    ###
    maxLineLength: null

    ###*
     * The php-ide-serenata service.
     *
     * @type {Service}
    ###
    service: null

    ###*
     * A range of the selected/highlighted area of code to analyse.
     *
     * @type {Range}
    ###
    selectedBufferRange: null

    ###*
     * The text editor to be analysing.
     *
     * @type {TextEditor}
    ###
    editor: null

    ###*
     * The parameter parser that will work out the parameters the
     * selectedBufferRange will need.
     *
     * @type {Object}
    ###
    parameterParser: null

    ###*
     * All the variables to return
     *
     * @type {Array}
    ###
    returnVariables: null

    ###*
     * @type {Object}
    ###
    docblockBuilder: null

    ###*
     * @type {Object}
    ###
    functionBuilder: null

    ###*
     * @type {Object}
    ###
    typeHelper: null

    ###*
     * Constructor.
     *
     * @param  {Object} parameterParser
     * @param  {Object} docblockBuilder
     * @param  {Object} functionBuilder
     * @param  {Object} typeHelper
    ###
    constructor: (@parameterParser, @docblockBuilder, @functionBuilder, @typeHelper) ->

    ###*
     * Sets the method body to use in the preview.
     *
     * @param {String} text
    ###
    setMethodBody: (text) ->
        @methodBody = text

    ###*
     * The tab string to use when generating the new method.
     *
     * @param {String} tab
    ###
    setTabText: (tab) ->
        @tabText = tab

    ###*
     * @param {Number} indentationLevel
    ###
    setIndentationLevel: (indentationLevel) ->
        @indentationLevel = indentationLevel

    ###*
     * @param {Number} maxLineLength
    ###
    setMaxLineLength: (maxLineLength) ->
        @maxLineLength = maxLineLength

    ###*
     * Set the php-ide-serenata service to be used.
     *
     * @param {Service} service
    ###
    setService: (service) ->
        @service = service
        @parameterParser.setService(service)

    ###*
     * Set the selectedBufferRange to analyse.
     *
     * @param {Range} range [description]
    ###
    setSelectedBufferRange: (range) ->
        @selectedBufferRange = range

    ###*
     * Set the TextEditor to be used when analysing the selectedBufferRange
     *
     * @param {TextEditor} editor [description]
    ###
    setEditor: (editor) =>
        @editor = editor
        @setTabText(editor.getTabText())
        @setIndentationLevel(1)
        @setMaxLineLength(atom.config.get('editor.preferredLineLength', editor.getLastCursor().getScopeDescriptor()))
        @setSelectedBufferRange(editor.getSelectedBufferRange())

    ###*
     * Builds the new method from the selectedBufferRange and settings given.
     *
     * The settings parameter should be an object with these properties:
     *   - methodName (string)
     *   - visibility (string) ['private', 'protected', 'public']
     *   - tabs (boolean)
     *   - generateDocs (boolean)
     *   - arraySyntax (string) ['word', 'brackets']
     *   - generateDocPlaceholders (boolean)
     *
     * @param  {Object} settings
     *
     * @return {Promise}
    ###
    buildMethod: (settings) =>
        successHandler = (parameters) =>
            if @returnVariables == null
                @returnVariables = @workOutReturnVariables @parameterParser.getVariableDeclarations()

            tabText = if settings.tabs then @tabText else ''
            totalIndentation = tabText.repeat(@indentationLevel)

            statements = []

            for statement in @methodBody.split('\n')
                newStatement = statement.substr(totalIndentation.length)

                statements.push(newStatement)

            returnTypeHintSpecification = 'void'
            returnStatement = @buildReturnStatement(@returnVariables, settings.arraySyntax)

            if returnStatement?
                if @returnVariables.length == 1
                    returnTypeHintSpecification = @returnVariables[0].types.join('|')

                else
                    returnTypeHintSpecification = 'array'

                returnStatement = returnStatement.substr(totalIndentation.length)

                statements.push('')
                statements.push(returnStatement)

            functionParameters = parameters.map (parameter) =>
                typeHintInfo = @typeHelper.getTypeHintForDocblockTypes(parameter.types)

                return {
                    name         : parameter.name
                    typeHint     : if typeHintInfo? and settings.typeHinting    then typeHintInfo.typeHint else null
                    defaultValue : if typeHintInfo? and typeHintInfo.isNullable then 'null' else null
                }

            docblockParameters = parameters.map (parameter) =>
                typeSpecification = @typeHelper.buildTypeSpecificationFromTypes(parameter.types)

                return {
                    name : parameter.name
                    type : if typeSpecification.length > 0 then typeSpecification else '[type]'
                }

            @functionBuilder
                .setIsStatic(false)
                .setIsAbstract(false)
                .setName(settings.methodName)
                .setReturnType(@typeHelper.getReturnTypeHintForTypeSpecification(returnTypeHintSpecification))
                .setParameters(functionParameters)
                .setStatements(statements)
                .setIndentationLevel(@indentationLevel)
                .setTabText(tabText)
                .setMaxLineLength(@maxLineLength)

            if settings.visibility == 'public'
                @functionBuilder.makePublic()

            else if settings.visibility == 'protected'
                @functionBuilder.makeProtected()

            else if settings.visibility == 'private'
                @functionBuilder.makePrivate()

            else
                 @functionBuilder.makeGlobal()

            docblockText = ''

            if settings.generateDocs
                returnType = 'void'

                if @returnVariables != null && @returnVariables.length > 0
                    returnType = '[type]'

                    if @returnVariables.length > 1
                        returnType = 'array'

                    else if @returnVariables.length == 1 and @returnVariables[0].types.length > 0
                        returnType = @typeHelper.buildTypeSpecificationFromTypes(@returnVariables[0].types)

                docblockText = @docblockBuilder.buildForMethod(
                    docblockParameters,
                    returnType,
                    settings.generateDescPlaceholders,
                    totalIndentation
                )

            return docblockText + @functionBuilder.build()

        failureHandler = () ->
            return null

        return @parameterParser.findParameters(@editor, @selectedBufferRange).then(successHandler, failureHandler)

    ###*
     * Build the line that calls the new method and the variable the method
     * to be assigned to.
     *
     * @param  {String} methodName
     * @param  {String} variable   [Optional]
     *
     * @return {Promise}
    ###
    buildMethodCall: (methodName, variable) =>
        successHandler = (parameters) =>
            parameterNames = parameters.map (item) ->
                return item.name

            methodCall = "$this->#{methodName}(#{parameterNames.join ', '});"

            if variable != undefined
                methodCall = "$#{variable} = #{methodCall}"
            else
                if @returnVariables != null
                    if @returnVariables.length == 1
                        methodCall = "#{@returnVariables[0].name} = #{methodCall}"
                    else if @returnVariables.length > 1
                        variables = @returnVariables.reduce (previous, current) ->
                            if typeof previous != 'string'
                                previous = previous.name

                            return previous + ', ' + current.name

                        methodCall = "list(#{variables}) = #{methodCall}"

            return methodCall

        failureHandler = () ->
            return null

        @parameterParser.findParameters(@editor, @selectedBufferRange).then(successHandler, failureHandler)

    ###*
     * Performs any clean up needed with the builder.
    ###
    cleanUp: ->
        @returnVariables = null
        @parameterParser.cleanUp()

    ###*
     * Works out which variables need to be returned from the new method.
     *
     * @param  {Array} variableDeclarations
     *
     * @return {Array}
    ###
    workOutReturnVariables: (variableDeclarations) ->
        startPoint = @selectedBufferRange.end
        scopeRange = @parameterParser.getRangeForCurrentScope(@editor, startPoint)

        lookupRange = new Range(startPoint, scopeRange.end)

        textAfterExtraction = @editor.getTextInBufferRange lookupRange
        allVariablesAfterExtraction = textAfterExtraction.match /\$[a-zA-Z0-9]+/g

        return null if allVariablesAfterExtraction == null

        variableDeclarations = variableDeclarations.filter (variable) =>
            return true if variable.name in allVariablesAfterExtraction
            return false

        return variableDeclarations

    ###*
     * Builds the return statement for the new method.
     *
     * @param {Array}  variableDeclarations
     * @param {String} arrayType ['word', 'brackets']
     *
     * @return {String|null}
    ###
    buildReturnStatement: (variableDeclarations, arrayType = 'word') ->
        if variableDeclarations?
            if variableDeclarations.length == 1
                return "#{@tabText}return #{variableDeclarations[0].name};"

            else if variableDeclarations.length > 1
                variables = variableDeclarations.reduce (previous, current) ->
                    if typeof previous != 'string'
                        previous = previous.name

                    return previous + ', ' + current.name

                if arrayType == 'brackets'
                    variables = "[#{variables}]"

                else
                    variables = "array(#{variables})"

                return "#{@tabText}return #{variables};"

        return null

    ###*
     * Checks if the new method will be returning any values.
     *
     * @return {Boolean}
    ###
    hasReturnValues: ->
        return @returnVariables != null && @returnVariables.length > 0

    ###*
     * Returns if there are multiple return values.
     *
     * @return {Boolean}
    ###
    hasMultipleReturnValues: ->
        return @returnVariables != null && @returnVariables.length > 1
