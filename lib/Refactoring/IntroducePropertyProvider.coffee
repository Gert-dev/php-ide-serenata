{Point} = require 'atom'

AbstractProvider = require './AbstractProvider'

module.exports =

##*
# Provides property generation for non-existent properties.
##
class IntroducePropertyProvider extends AbstractProvider
    ###*
     * The docblock builder.
    ###
    docblockBuilder: null

    ###*
     * @param {Object} docblockBuilder
    ###
    constructor: (@docblockBuilder) ->

    ###*
     * @inheritdoc
    ###
    getIntentionProviders: () ->
        return [{
            grammarScopes: ['variable.other.property.php']
            getIntentions: ({textEditor, bufferPosition}) =>
                nameRange = textEditor.bufferRangeForScopeAtCursor('variable.other.property')

                return if not nameRange?
                return [] if not @getCurrentProjectPhpVersion()?

                name = textEditor.getTextInBufferRange(nameRange)

                return @getIntentions(textEditor, bufferPosition, name)
        }]

    ###*
     * @param {TextEditor} editor
     * @param {Point}      triggerPosition
     * @param {String}     name
    ###
    getIntentions: (editor, triggerPosition, name) ->
        failureHandler = () =>
            return []

        successHandler = (currentClassName) =>
            return [] if not currentClassName?

            nestedSuccessHandler = (classInfo) =>
                intentions = []

                return intentions if not classInfo

                if name not of classInfo.properties
                    intentions.push({
                        priority : 100
                        icon     : 'gear'
                        title    : 'Introduce New Property'

                        selected : () =>
                            @introducePropertyFor(editor, classInfo, name)
                    })

                return intentions

            @service.getClassInfo(currentClassName).then(nestedSuccessHandler, failureHandler)

        @service.determineCurrentClassName(editor, triggerPosition).then(successHandler, failureHandler)

    ###*
     * @param {TextEditor} editor
     * @param {Object}     classData
     * @param {String}     name
    ###
    introducePropertyFor: (editor, classData, name) ->
        indentationLevel = editor.indentationForBufferRow(classData.startLine - 1) + 1

        tabText = editor.getTabText().repeat(indentationLevel)

        docblock = @docblockBuilder.buildForProperty(
            'mixed',
            false,
            tabText
        )

        property = "#{tabText}protected $#{name};\n\n"

        point = @findLocationToInsertProperty(editor, classData)

        editor.getBuffer().insert(point, docblock + property)


    ###*
     * @param {TextEditor} editor
     * @param {Object}     classData
     *
     * @return {Point}
    ###
    findLocationToInsertProperty: (editor, classData) ->
        startLine = null

        # Try to place the new property underneath the existing properties.
        for name,propertyData of classData.properties
            if propertyData.declaringStructure.name == classData.name
                startLine = propertyData.endLine + 1

        if not startLine?
            # Ensure we don't end up somewhere in the middle of the class definition if it spans multiple lines.
            lineCount = editor.getLineCount()

            for line in [classData.startLine .. lineCount]
                lineText = editor.lineTextForBufferRow(line)

                continue if not lineText?

                for i in [0 .. lineText.length - 1]
                    if lineText[i] == '{'
                        startLine = line + 1
                        break

                break if startLine?

        if not startLine?
            startLine = classData.startLine + 1

        return new Point(startLine, -1)
