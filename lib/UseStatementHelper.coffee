module.exports =

##*
# Contains convenience methods for dealing with use statements.
##
class UseStatementHelper
    ###*
     * Regular expression that will search for a structure (class, interface, trait, ...).
     *
     * @var {RegExp}
    ###
    structureStartRegex : /(?:abstract class|class|trait|interface)\s+(\w+)/

    ###*
     * Regular expression that will search for a use statement.
     *
     * @var {RegExp}
    ###
    useStatementRegex   : /(?:use)(?:[^\w\\])([\w\\]+)(?![\w\\])(?:(?:[ ]+as[ ]+)(\w+))?(?:;)/

    ###*
     * Whether to allow adding additional newlines to attempt to group use statements.
     *
     * @var {Boolean}
    ###
    allowAdditionalNewlines : true

    ###*
     * @param {Boolean} allowAdditionalNewlines
    ###
    constructor: (@allowAdditionalNewlines) ->

    ###*
     * @param {Boolean} allowAdditionalNewlines
    ###
    setAllowAdditionalNewlines: (@allowAdditionalNewlines) ->

    ###*
     * Add the use for the given class if not already added.
     *
     * @param {TextEditor} editor    Atom text editor.
     * @param {String}     className Name of the class to add.
     *
     * @return {Number} The amount of lines added (including newlines), so you can reliably and easily offset your rows.
     #                  This could be zero if a use statement was already present.
    ###
    addUseClass: (editor, className) ->
        bestUseRow = 0
        placeBelow = true
        doNewLine = true
        lineCount = editor.getLineCount()
        previousMatchThatSharedNamespacePrefixRow = null

        # First see if the use statement is already present. The next loop stops early (and can't do this).
        for i in [0 .. lineCount - 1]
            line = editor.lineTextForBufferRow(i).trim()

            continue if line.length == 0

            scopeDescriptor = editor.scopeDescriptorForBufferPosition([i, line.length]).getScopeChain()

            if scopeDescriptor.indexOf('.comment') >= 0
                continue

            break if line.match(@structureStartRegex)

            if (matches = @useStatementRegex.exec(line))
                if matches[1] == className or (matches[1][0] == '\\' and matches[1].substr(1) == className)
                    return 0

        # Determine an appropriate location to place the use statement.
        for i in [0 .. lineCount - 1]
            line = editor.lineTextForBufferRow(i).trim()

            continue if line.length == 0

            scopeDescriptor = editor.scopeDescriptorForBufferPosition([i, line.length]).getScopeChain()

            if scopeDescriptor.indexOf('.comment') >= 0
                continue

            break if line.match(@structureStartRegex)

            if line.indexOf('namespace ') >= 0
                bestUseRow = i

            if (matches = @useStatementRegex.exec(line))
                bestUseRow = i

                placeBelow = true
                shareCommonNamespacePrefix = @doShareCommonNamespacePrefix(className, matches[1])

                doNewLine = not shareCommonNamespacePrefix

                if @scoreClassName(className, matches[1]) <= 0
                    placeBelow = false

                    # Normally we keep going until the sorting indicates we should stop, and then place the use
                    # statement above the 'incorrect' match, but if the previous use statement was a use statement
                    # that has the same namespace, we want to ensure we stick close to it instead of creating additional
                    # newlines (which the item from the same namespace already placed).
                    if previousMatchThatSharedNamespacePrefixRow?
                        placeBelow = true
                        doNewLine = false
                        bestUseRow = previousMatchThatSharedNamespacePrefixRow

                    break

                previousMatchThatSharedNamespacePrefixRow = if shareCommonNamespacePrefix then i else null

        # Insert the use statement itself.
        lineEnding = editor.getBuffer().lineEndingForRow(0)

        if not @allowAdditionalNewlines
            doNewLine = false

        if not lineEnding
            lineEnding = "\n"

        textToInsert = ''

        if doNewLine and placeBelow
            textToInsert += lineEnding

        textToInsert += "use #{className};" + lineEnding

        if doNewLine and not placeBelow
            textToInsert += lineEnding

        lineToInsertAt = bestUseRow + (if placeBelow then 1 else 0)
        editor.setTextInBufferRange([[lineToInsertAt, 0], [lineToInsertAt, 0]], textToInsert)

        return (1 + (if doNewLine then 1 else 0))

    ###*
     * Returns a boolean indicating if the specified class names share a common namespace prefix.
     *
     * @param {String} firstClassName
     * @param {String} secondClassName
     *
     * @return {Boolean}
    ###
    doShareCommonNamespacePrefix: (firstClassName, secondClassName) ->
        firstClassNameParts = firstClassName.split('\\')
        secondClassNameParts = secondClassName.split('\\')

        firstClassNameParts.pop()
        secondClassNameParts.pop()

        return if firstClassNameParts.join('\\') == secondClassNameParts.join('\\') then true else false

    ###*
     * Scores the first class name against the second, indicating how much they 'match' each other. This can be used
     * to e.g. find an appropriate location to place a class in an existing list of classes.
     *
     * @param {String} firstClassName
     * @param {String} secondClassName
     *
     * @return {Number} A floating point number that represents the score.
    ###
    scoreClassName: (firstClassName, secondClassName) ->
        maxLength = 0
        totalScore = 0

        firstClassNameParts = firstClassName.split('\\')
        secondClassNameParts = secondClassName.split('\\')

        maxLength = Math.min(firstClassNameParts.length, secondClassNameParts.length)

        collator = new Intl.Collator

        # At this point, both FQSEN's share a common namespace, e.g. A\B and A\B\C\D, or XMLElement and XMLDocument.
        # The one with the most namespace parts ends up last.
        if firstClassNameParts.length < secondClassNameParts.length
            return -1

        else if firstClassNameParts.length > secondClassNameParts.length
            return 1

        if maxLength >= 2
            for i in [0 .. maxLength - 1]
                if firstClassNameParts[i] != secondClassNameParts[i]
                    if firstClassNameParts[i].length == secondClassNameParts[i].length
                        return collator.compare(firstClassNameParts[i], secondClassNameParts[i])

                    return firstClassNameParts[i].length > secondClassNameParts[i].length ? 1 : -1

        if firstClassName.length == secondClassName.length
            return collator.compare(firstClassName, secondClassName)

        # Both items have share the same namespace, sort from shortest to longest last word (class, interface, ...).
        return firstClassName.length > secondClassName.length ? 1 : -1

    ###*
     * Sorts the use statements in the specified file according to the same algorithm used by 'addUseClass'.
     *
     * @param {TextEditor} editor
    ###
    sortUseStatements: (editor) ->
        endLine = null
        startLine = null
        useStatements = []

        for i in [0 .. editor.getLineCount()]
            lineText = editor.lineTextForBufferRow(i)

            endLine = i

            if not lineText or lineText.trim() == ''
                continue

            else if (matches = @useStatementRegex.exec(lineText))
                if not startLine
                    startLine = i

                text = matches[1]

                if matches[2]?
                    text += ' as ' + matches[2]

                useStatements.push(text);

            # We still do the regex check here to prevent continuing when there are no use statements at all.
            else if startLine or @structureStartRegex.test(lineText)
                break

        return if useStatements.length == 0

        editor.transact () =>
            editor.setTextInBufferRange([[startLine, 0], [endLine, 0]], '')

            for useStatement in useStatements
                # The leading slash is unnecessary, not recommended, and messes up sorting, take it out.
                if useStatement[0] == '\\'
                    useStatement = useStatement.substr(1)

                @addUseClass(editor, useStatement, @allowAdditionalNewlines)
