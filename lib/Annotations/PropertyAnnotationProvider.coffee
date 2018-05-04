{Range} = require 'atom'

AbstractAnnotationProvider = require './AbstractAnnotationProvider'

module.exports =

##*
# Provides annotations for member properties that are overrides.
##
class MethodProvider extends AbstractAnnotationProvider
    ###*
     * @inheritdoc
    ###
    registerAnnotations: (editor) ->
        path = editor.getPath()

        return null if not path

        successHandler = (classInfo) =>
            return null if not classInfo

            for name, property of classInfo.properties
                continue if not property.override
                continue if property.declaringStructure.fqcn != classInfo.fqcn

                range = new Range([property.startLine - 1, 0], [property.startLine, -1])

                @placeAnnotation(editor, range, @extractAnnotationInfo(property))

        failureHandler = () =>
            # Just do nothing.

        getClassListHandler = (classesInEditor) =>
            promises = []

            for fqcn, classInfo of classesInEditor
                promises.push @service.getClassInfo(fqcn).then(successHandler, failureHandler)

            return Promise.all(promises)

        return @service.getClassListForFile(path).then(getClassListHandler, failureHandler)

    ###*
     * Fetches annotation info for the specified context.
     *
     * @param {Object} context
     *
     * @return {Object}
    ###
    extractAnnotationInfo: (context) ->
        # NOTE: We deliberately show the declaring class here, not the structure (which could be a trait). However,
        # if the method is overriding a trait method from the *same* class, we show the trait name, as it would be
        # strange to put an annotation in "Foo" saying "Overrides method from Foo".
        overriddenFromFqcn = context.override.declaringClass.fqcn

        if overriddenFromFqcn == context.declaringClass.fqcn
            overriddenFromFqcn = context.override.declaringStructure.fqcn

        return {
            lineNumberClass : 'override'
            tooltipText     : 'Overrides property from ' + overriddenFromFqcn
            extraData       : context.override
        }

    ###*
     * @inheritdoc
    ###
    handleMouseClick: (event, editor, annotationInfo) ->
        # 'filename' can be false for overrides of members from PHP's built-in classes (e.g. Exception).
        if annotationInfo.extraData.declaringStructure.filename
            atom.workspace.open(annotationInfo.extraData.declaringStructure.filename, {
                initialLine    : annotationInfo.extraData.declaringStructure.startLineMember - 1,
                searchAllPanes : true
            })

    ###*
     * @inheritdoc
    ###
    removePopover: () ->
        if @attachedPopover
            @attachedPopover.dispose()
            @attachedPopover = null
