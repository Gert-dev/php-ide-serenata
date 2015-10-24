
module.exports =

##*
# Parser that handles all kinds of tasks related to parsing PHP code.
##
class Parser
    ###*
     * Regular expression that will search for a structure (class, interface, trait, ...).
    ###
    structureStartRegex : /(?:abstract class|class|trait|interface)\s+(\w+)/

    ###*
     * Regular expression that will search for a use statement.
    ###
    useStatementRegex   : /(?:use)(?:[^\w\\])([\w\\]+)(?![\w\\])(?:(?:[ ]+as[ ]+)(\w+))?(?:;)/

    ###*
     * Simple cache to avoid duplicate computation.
     *
     * @todo Needs refactoring.
    ###
    cache: []

    ###*
     * The proxy that can be used to query information about the code.
    ###
    proxy: null

    ###*
     * Constructor.
     *
     * @param {Proxy} proxy
    ###
    constructor: (@proxy) ->

    ###*
     * Retrieves the class the specified member (method or property) is being invoked on.
     *
     * @param  {TextEditor} editor         The text editor to use.
     * @param  {Point}      bufferPosition The cursor location of the member, this should be at the operator :: or ->
     *                                      (but anywhere inside the name of the member itself is fine too).
     *
     * @return {string|null}
     *
     * @example Invoking it on MyMethod::foo()->bar() will ask what class 'bar' is invoked on, which will whatever type
     *          foo returns.
    ###
    getCalledClass: (editor, bufferPosition) ->
        fullCall = @retrieveSanitizedCallStackAt(editor, bufferPosition)

        return null if not fullCall or fullCall.length == 0

        return @getClassNameFromCallStack(editor, bufferPosition, fullCall)

    ###*
     * Retrieves contextual information about the class member at the specified location in the editor.
     *
     * @param {TextEditor} editor         The text editor to use.
     * @param {Point}      bufferPosition The cursor location of the member.
     * @param {string}     name           The name of the member to retrieve information about.
     *
     * @return {Object|null}
    ###
    getClassMember: (editor, bufferPosition, name) ->
        calledClass = @getCalledClass(editor, bufferPosition)

        return null unless calledClass

        response = @proxy.getClassInfo(calledClass)

        return if not response or (response.error? and response.error != '') or response.names.indexOf(name) == -1

        members = response.values[name]

        # If there are multiple matches, just select the first one.
        if members instanceof Array and members.length > 0
            return members[0]

        return members

    ###*
     * Retrieves all variables that are available at the specified buffer position.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     *
     * @return {array}
    ###
    getAvailableVariables: (editor, bufferPosition) ->
        # TODO: This needs refactoring and will not properly skip variables that are outside of the current function's
        # scope.

        isInFunction = @isInFunction(editor, bufferPosition)

        startPosition = null

        if isInFunction
            startPosition = @cache['functionPosition']

        else
            startPosition = [0, 0]

        text = editor.getTextInBufferRange([startPosition, [bufferPosition.row, bufferPosition.column-1]])
        regex = /(\$[a-zA-Z_]+)/g

        matches = text.match(regex)
        return [] if not matches?

        if isInFunction
            matches.push "$this"

        return matches

    ###*
     * Determines the full class name (without leading slash) of the specified class in the specified editor. If no
     * class name is passed, the full class name of the class defined in the current file is returned instead.
     *
     * @param {TextEditor}  editor    The editor that contains the class (needed to resolve relative class names).
     * @param {String|null} className The (local) name of the class to resolve.
     *
     * @return {string|null}
     *
     * @example In a file with namespace A\B, determining C will lead to A\B\C.
    ###
    determineFullClassName: (editor, className = null) ->
        if className == null
            className = ''

        if className and className[0] == "\\"
            return className.substr(1) # FQCN, not subject to any further context.

        # TODO: Move these regular expressions to class members.
        usePattern = /(?:use)(?:[^\w\\\\])([\w\\\\]+)(?![\w\\\\])(?:(?:[ ]+as[ ]+)(\w+))?(?:;)/
        namespacePattern = /(?:namespace)(?:[^\w\\\\])([\w\\\\]+)(?![\w\\\\])(?:;)/
        definitionPattern = /(?:abstract class|class|trait|interface)\s+(\w+)/

        text = editor.getText()

        # TODO: It is not necessary to split the text, we can use lineTextForBufferRow instead.
        lines = text.split('\n')
        fullClass = className

        found = false

        for line,i in lines
            matches = line.match(namespacePattern)

            if matches
                fullClass = matches[1] + '\\' + className

            else if className
                matches = line.match(usePattern)

                if matches
                    classNameParts = className.split('\\')
                    importNameParts = matches[1].split('\\')

                    isAliasedImport = if matches[2] then true else false

                    if className == matches[1]
                        fullClass = className # Already a complete name

                        break

                    else if (isAliasedImport and matches[2] == classNameParts[0]) or (!isAliasedImport and importNameParts[importNameParts.length - 1] == classNameParts[0])
                        found = true

                        fullClass = matches[1]

                        classNameParts = classNameParts[1 .. classNameParts.length]

                        if (classNameParts.length > 0)
                            fullClass += '\\' + classNameParts.join('\\')

                        break

            matches = line.match(definitionPattern)

            if matches
                if not className
                    found = true
                    fullClass += matches[1]

                break

        # In the class map, classes never have a leading slash. The leading slash only indicates that import rules of
        # the file don't apply, but it's useless after that.
        if fullClass and fullClass[0] == '\\'
            fullClass = fullClass.substr(1)

        if not found
            # At this point, this could either be a class name relative to the current namespace or a full class name
            # without a leading slash. For example, Foo\Bar could also be relative (e.g. My\Foo\Bar), in which case its
            # absolute path is determined by the namespace and use statements of the file containing it.
            info = @proxy.getClassInfo(fullClass)

            if not info?.filename
                # The class, e.g. My\Foo\Bar, didn't exist. We can only assume its an absolute path, using a namespace
                # set up in composer.json, without a leading slash.
                fullClass = className

        return fullClass

    ###*
     * Add the use for the given class if not already added.
     *
     * @param {TextEditor} editor                  Atom text editor.
     * @param {string}     className               Name of the class to add.
     * @param {boolean}    allowAdditionalNewlines Whether to allow adding additional newlines to attempt to group use
     *                                             statements.
     *
     * @return {int}       The amount of lines added (including newlines), so you can reliably and easily offset your
     *                     rows. This could be zero if a use statement was already present.
    ###
    addUseClass: (editor, className, allowAdditionalNewlines) ->
        if className.split('\\').length == 1 or className.indexOf('\\') == 0
            return null

        bestUse = 0
        bestScore = 0
        placeBelow = true
        doNewLine = true
        lineCount = editor.getLineCount()

        # Determine an appropriate location to place the use statement.
        for i in [0 .. lineCount - 1]
            line = editor.lineTextForBufferRow(i).trim()

            if line.length == 0
                continue

            scopeDescriptor = editor.scopeDescriptorForBufferPosition([i, line.length]).getScopeChain()

            if scopeDescriptor.indexOf('.comment') >= 0
                continue

            if line.match(@structureStartRegex)
                break

            if line.indexOf('namespace ') >= 0
                bestUse = i

            matches = @useStatementRegex.exec(line)

            if matches? and matches[1]?
                if matches[1] == className
                    return 0

                score = @scoreClassName(className, matches[1])

                if score >= bestScore
                    bestUse = i
                    bestScore = score

                    if @doShareCommonNamespacePrefix(className, matches[1])
                        doNewLine = false
                        placeBelow = if className.length >= matches[1].length then true else false

                    else
                        doNewLine = true
                        placeBelow = true

        # Insert the use statement itself.
        lineEnding = editor.getBuffer().lineEndingForRow(0)

        if not allowAdditionalNewlines
            doNewLine = false

        if not lineEnding
            lineEnding = "\n"

        textToInsert = ''

        if doNewLine and placeBelow
            textToInsert += lineEnding

        textToInsert += "use #{className};" + lineEnding

        if doNewLine and not placeBelow
            textToInsert += lineEnding

        lineToInsertAt = bestUse + (if placeBelow then 1 else 0)
        editor.setTextInBufferRange([[lineToInsertAt, 0], [lineToInsertAt, 0]], textToInsert)

        return (1 + (if doNewLine then 1 else 0))

    ###*
     * Returns a boolean indicating if the specified class names share a common namespace prefix.
     *
     * @param {string} firstClassName
     * @param {string} secondClassName
     *
     * @return {boolean}
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
     * @param {string} firstClassName
     * @param {string} secondClassName
     *
     * @return {float}
    ###
    scoreClassName: (firstClassName, secondClassName) ->
        firstClassNameParts = firstClassName.split('\\')
        secondClassNameParts = secondClassName.split('\\')

        maxLength = 0

        if firstClassNameParts.length > secondClassNameParts.length
            maxLength = secondClassNameParts.length

        else
            maxLength = firstClassNameParts.length

        totalScore = 0

        # NOTE: We don't score the last part.
        for i in [0 .. maxLength - 2]
            if firstClassNameParts[i] == secondClassNameParts[i]
                totalScore += 2

        if @doShareCommonNamespacePrefix(firstClassName, secondClassName)
            if firstClassName.length == secondClassName.length
                totalScore += 2

            else
                # Stick closer to items that are smaller in length than items that are larger in length.
                totalScore -= 0.001 * Math.abs(secondClassName.length - firstClassName.length)

        return totalScore

    ###*
     * Indicates if the specified name is the name of a structure (class, interface, ...) or not.
     *
     * @param {string} name
     *
     * @return {boolean}
    ###
    isClassType: (name) ->
        return name.substr(0,1).toUpperCase() + name.substr(1) == name

    ###*
     * Checks if the specified location is inside a function or method or not.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     *
     * @return {boolean}
    ###
    isInFunction: (editor, bufferPosition) ->
        text = editor.getTextInBufferRange([[0, 0], bufferPosition])

        # If last request was the same
        if @cache[text]?
          return @cache[text]

        # Reinitialize current cache
        @cache = []

        row = bufferPosition.row
        rows = text.split('\n')

        openedBlocks = 0
        closedBlocks = 0

        result = false

        # for each row
        while row != -1
            line = rows[row]

            # issue #61
            if not line
                row--
                continue

            character = 0
            lineLength = line.length
            lastChain = null

            # Scan the entire line, fetching the scope for each character position as one line can contain both a scope start
            # and end such as "} elseif (true) {". Here the scope descriptor will differ for different character positions on
            # the line.
            while character <= line.length
                # Get chain of all scopes
                chain = editor.scopeDescriptorForBufferPosition([row, character]).getScopeChain()

                # NOTE: Atom quirk: both line.length and line.length - 1 return the same scope descriptor, BUT you can't skip
                # scanning line.length as sometimes line.length - 1 does not return a scope descriptor at all.
                if not (character == line.length and chain == lastChain)
                    # }
                    if chain.indexOf("scope.end") != -1
                        closedBlocks++
                    # {
                    else if chain.indexOf("scope.begin") != -1
                        openedBlocks++

                lastChain = chain
                character++

            # Get chain of all scopes
            chain = editor.scopeDescriptorForBufferPosition([row, line.length]).getScopeChain()

            # function
            if chain.indexOf("function") != -1
                # If more openedblocks than closedblocks, we are in a function. Otherwise, could be a closure, continue looking.
                if openedBlocks > closedBlocks
                    result = true
                    @cache["functionPosition"] = [row, 0]

                    break

            row--

        @cache[text] = result
        return result

    ###*
     * Does exactly the same as {@see retrieveSanitizedCallStack}, but will automatically retrieve the relevant code
     * of the call at the specified location in the buffer.
     *
     * @param  {TextEditor} editor
     * @param  {Point}      bufferPosition
     *
     * @return {Object}
    ###
    retrieveSanitizedCallStackAt: (editor, bufferPosition) ->
        return unless bufferPosition?

        line = bufferPosition.row

        finished = false
        parenthesesOpened = 0
        parenthesesClosed = 0
        squiggleBracketsOpened = 0
        squiggleBracketsClosed = 0

        while line > 0
            lineText = editor.lineTextForBufferRow(line)

            if line != bufferPosition.row
                i = (lineText.length - 1)

            else
                i = bufferPosition.column - 1

            while i >= 0
                if lineText[i] == '('
                    ++parenthesesOpened

                    # Ticket #164 - We're walking backwards, if we find an opening paranthesis that hasn't been closed
                    # anywhere, we know we must stop.
                    if parenthesesOpened > parenthesesClosed
                        ++i
                        finished = true
                        break

                else if lineText[i] == ')'
                    ++parenthesesClosed

                else if lineText[i] == '{'
                    ++squiggleBracketsOpened

                    # Same as above.
                    if squiggleBracketsOpened > squiggleBracketsClosed
                        ++i
                        finished = true
                        break

                else if lineText[i] == '}'
                    ++squiggleBracketsClosed

                # These will not be the same if, for example, we've entered a closure.
                else if parenthesesOpened == parenthesesClosed and squiggleBracketsOpened == squiggleBracketsClosed
                    # Variable definition.
                    if lineText[i] == '$'
                        finished = true
                        break

                    else if lineText[i] == ';' or lineText[i] == '='
                        ++i
                        finished = true
                        break

                    else
                        scopeDescriptor = editor.scopeDescriptorForBufferPosition([line, i]).getScopeChain()

                        # Language constructs, such as echo and print, don't require parantheses.
                        if scopeDescriptor.indexOf('.function.construct') > 0 or scopeDescriptor.indexOf('.comment') > 0
                            ++i
                            finished = true
                            break

                --i

            if finished
                break

            --line

        # Fetch everything we ran through up until the location we started from.
        textSlice = editor.getTextInBufferRange([[line, i], bufferPosition]).trim()

        return @retrieveSanitizedCallStack(textSlice)

    ###*
     * Removes content inside parantheses (including nested parantheses).
     *
     * @param {string} text String to analyze.
     *
     * @return {string}
    ###
    stripParenthesesContent: (text) ->
        i = 0
        openCount = 0
        closeCount = 0
        startIndex = -1

        while i < text.length
            if text[i] == '('
                ++openCount

                if openCount == 1
                    startIndex = i

            else if text[i] == ')'
                ++closeCount

                if closeCount == openCount
                    originalLength = text.length
                    text = text.substr(0, startIndex + 1) + text.substr(i, text.length);

                    i -= (originalLength - text.length)

                    openCount = 0
                    closeCount = 0

            ++i

        return text

    ###*
     * Takes a call stack and turns it into an array of sanitized elements, which are easier to process.
     *
     * @example Passing A::b(complex_arguments)->c will retrieve ['A', 'b()', 'c'].
     *
     * @param {string} text
     *
     * @return {array}
    ###
    retrieveSanitizedCallStack: (text) ->
        # Remove singe line comments
        regx = /\/\/.*\n/g
        text = text.replace regx, (match) =>
            return ''

        # Remove multi-line comments
        regx = /\/\*[^(\*\/)]*\*\//g
        text = text.replace regx, (match) =>
            return ''

        # Remove content inside parantheses (including nested parantheses).
        text = @stripParenthesesContent(text)

        # Get the full text
        return [] if not text

        elements = text.split(/(?:\-\>|::)/)

        # Remove parentheses and whitespace.
        for key, element of elements
            element = element.replace /^\s+|\s+$/g, ""

            if element[0] == '{' or element[0] == '(' or element[0] == '['
                element = element.substring(1)

            else if element.indexOf('return ') == 0
                element = element.substring('return '.length)

            elements[key] = element

        return elements

    ###*
     * Retrieves the type of a variable, relative to the context at the specified buffer location.
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     * @param {string}     name
    ###
    getVariableType: (editor, bufferPosition, name) ->
        element = name

        if element.replace(/[\$][a-zA-Z0-9_]+/g, "").trim().length > 0
            return null

        if element.trim().length == 0
            return null

        bestMatch = null
        bestMatchRow = null

        # Regex variable definition
        regexElement = new RegExp("\\#{element}[\\s]*=[\\s]*([^;]+);", "g")
        regexNewInstance = new RegExp("\\#{element}[\\s]*=[\\s]*new[\\s]*\\\\?([A-Z][a-zA-Z_\\\\]*)+(?:(.+)?);", "g")
        regexCatch = new RegExp("catch[\\s]*\\([\\s]*([A-Za-z0-9_\\\\]+)[\\s]+\\#{element}[\\s]*\\)", "g")

        lineNumber = bufferPosition.row - 1

        while lineNumber > 0
            line = editor.lineTextForBufferRow(lineNumber)

            if not bestMatch
                # Check for $x = new XXXXX()
                matchesNew = regexNewInstance.exec(line)

                if null != matchesNew
                    bestMatchRow = lineNumber
                    bestMatch = @determineFullClassName(editor, matchesNew[1])

            if not bestMatch
                # Check for catch(XXX $xxx)
                matchesCatch = regexCatch.exec(line)

                if null != matchesCatch
                    bestMatchRow = lineNumber
                    bestMatch = @determineFullClassName(editor, matchesCatch[1])

            if not bestMatch
                # Check for a variable assignment $x = ...
                matches = regexElement.exec(line)

                if null != matches
                    value = matches[1]
                    elements = @retrieveSanitizedCallStack(value)
                    elements.push("") # Push one more element to get fully the last class

                    newPosition =
                        row : lineNumber
                        column: bufferPosition.column

                    # NOTE: bestMatch could now be null, but this line is still the closest match. The fact that we
                    # don't recognize the class name is irrelevant.
                    bestMatchRow = lineNumber
                    bestMatch = @getClassNameFromCallStack(editor, newPosition, elements)

            if not bestMatch
                # Check for function or closure parameter type hints and the docblock.
                regexFunction = new RegExp("function(?:[\\s]+([a-zA-Z]+))?[\\s]*[\\(](?:(?![a-zA-Z\\_\\\\]*[\\s]*\\#{element}).)*[,\\s]?([a-zA-Z\\_\\\\]*)[\\s]*\\#{element}[a-zA-Z0-9\\s\\$\\\\,=\\\"\\\'\(\)]*[\\s]*[\\)]", "g")
                matches = regexFunction.exec(line)

                if null != matches
                    typeHint = matches[2]

                    if typeHint.length > 0
                        return @determineFullClassName(editor, typeHint)

                    funcName = matches[1]

                    # Can be empty for closures.
                    if funcName and funcName.length > 0
                        params = @proxy.getDocParams(@determineFullClassName(editor), funcName)

                        if params.params? and params.params[element]?
                            return @determineFullClassName(editor, params.params[element])

            chain = editor.scopeDescriptorForBufferPosition([lineNumber, line.length]).getScopeChain()

            # Annotations in comments can optionally override the variable type.
            if chain.indexOf("comment") != -1
                # Check if the line before contains a /** @var FooType */, which overrides the type of the variable
                # immediately below it. This will not evaluate to /** @var FooType $someVar */ (see below for that).
                if bestMatchRow and lineNumber == (bestMatchRow - 1)
                    regexVar = /\@var[\s]+([a-zA-Z_\\]+)(?![\w]+\$)/g
                    matches = regexVar.exec(line)

                    if null != matches
                        return @determineFullClassName(editor, matches[1])

                # Check if there is an PHPStorm-style type inline docblock present /** @var FooType $someVar */.
                regexVarWithVarName = new RegExp("\\@var[\\s]+([a-zA-Z_\\\\]+)[\\s]+\\#{element}", "g")
                matches = regexVarWithVarName.exec(line)

                if null != matches
                    return @determineFullClassName(editor, matches[1])

                # Check if there is an IntelliJ-style type inline docblock present /** @var $someVar FooType */.
                regexVarWithVarName = new RegExp("\\@var[\\s]+\\#{element}[\\s]+([a-zA-Z_\\\\]+)", "g")
                matches = regexVarWithVarName.exec(line)

                if null != matches
                    return @determineFullClassName(editor, matches[1])

            # We've reached the function definition, other variables don't apply to this scope.
            if chain.indexOf("function") != -1
                break

            --lineNumber

        return bestMatch

    ###*
     * Parses all elements from the given call stack to return the last class name (if any).
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     * @param {array}      callStack
     *
     * @return {string|null}
    ###
    getClassNameFromCallStack: (editor, bufferPosition, callStack) ->
        loop_index = 0
        className  = null
        if not callStack?
            return

        for element in callStack
            # $this keyword
            if loop_index == 0
                if element[0] == '$'
                    className = @getVariableType(editor, bufferPosition, element)

                    # NOTE: The type of $this can also be overridden locally by a docblock.
                    if element == '$this' and not className
                        className = @determineFullClassName(editor)

                    loop_index++
                    continue

                else if element == 'static' or element == 'self'
                    className = @determineFullClassName(editor)
                    loop_index++
                    continue

                else if element == 'parent'
                    className = @getParentClass(editor)
                    loop_index++
                    continue

                else
                    className = @determineFullClassName(editor, element)
                    loop_index++
                    continue

            # Last element
            if loop_index >= callStack.length - 1
                break

            if className == null
                break

            methods = @proxy.autocomplete(className, element)

            # Element not found or no return value
            if not methods.class? or not @isClassType(methods.class)
                className = null
                break

            className = methods.class
            loop_index++

        # If no data or a valid end of line, OK
        if callStack.length > 0 and (callStack[callStack.length-1].length == 0 or callStack[callStack.length-1].match(/([a-zA-Z0-9]$)/g))
            return className

        return null

    ###*
     * Gets the correct selector when a class or namespace is clicked.
     *
     * @param {jQuery.Event} event
     *
     * @return {object|null} A selector to be used with jQuery.
    ###
    getClassSelectorFromEvent: (event) ->
        selector = event.currentTarget

        $ = require 'jquery'

        if $(selector).hasClass('builtin') or $(selector).children('.builtin').length > 0
            return null

        if $(selector).parent().hasClass('function argument')
            return $(selector).parent().children('.namespace, .class:not(.operator):not(.constant)')

        if $(selector).prev().hasClass('namespace') && $(selector).hasClass('class')
            return $([$(selector).prev()[0], selector])

        if $(selector).next().hasClass('class') && $(selector).hasClass('namespace')
           return $([selector, $(selector).next()[0]])

        if $(selector).prev().hasClass('namespace') || $(selector).next().hasClass('inherited-class')
            return $(selector).parent().children('.namespace, .inherited-class')

        return selector
