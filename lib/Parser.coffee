{Point, Range} = require 'atom'

module.exports =

##*
# Parser that handles all kinds of tasks related to parsing PHP code.
##
class Parser
    ###*
     * A string that can be inserted into regular expressions that will match a class name, including its namespace,
     * if present.
    ###
    classRegexPart = '\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*'

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
     * Indicates if the specifiec location is a property usage or not. If it is not, it is most likely a method call.
     * This is useful to distinguish between properties and methods with the same name.
     *
     * @example When querying "$this->test", using a position inside 'test' will return true.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     *
     * @return {boolean}
    ###
    isUsingProperty: (editor, bufferPosition) ->
        scopeDescriptor = editor.scopeDescriptorForBufferPosition(bufferPosition).getScopeChain()

        return (scopeDescriptor.indexOf('.property') != -1)

    ###*
     * Indicates if the specified type is a basic type (e.g. int, array, object, etc.).
     *
     * @param {string} type
     *
     * @return {boolean}
    ###
    isBasicType: (type) ->
        return /^(array|object|bool|string|static|null|boolean|void|int|integer|mixed|callable)$/i.test(type)

    ###*
     * Convenience function that resolves types using {@see resolveType}, automatically determining the correct
     * parameters for the editor and buffer position.
     *
     * @param {TextEditor} editor         The editor.
     * @param {Point}      bufferPosition The location of the type.
     * @param {string}     type           The (local) type to resolve.
     *
     * @return {string|null}
     *
     * @example In a file with namespace A\B, determining C could lead to A\B\C.
    ###
    resolveTypeAt: (editor, bufferPosition, type) ->
        return @proxy.resolveType(editor.getPath(), bufferPosition.row + 1, type)

    ###*
     * Determines the current class' FQCN based on the specified buffer position.
     *
     * @param {TextEditor} editor         The editor that contains the class (needed to resolve relative class names).
     * @param {Point}      bufferPosition
     * @param {boolean}    async
     *
     * @return {Promise|string|null}
    ###
    determineCurrentClassName: (editor, bufferPosition, async = false) ->
        path = editor.getPath()

        if not async
            classesInFile = @proxy.getClassListForFile(editor.getPath())

            for name,classInfo of classesInFile
                if bufferPosition.row >= classInfo.startLine and bufferPosition.row <= classInfo.endLine
                    return name

            return null

        return new Promise (resolve, reject) =>
            path = editor.getPath()

            if not path?
                reject()
                return

            return @proxy.getClassListForFile(path, true).then (classesInFile) =>
                for name,classInfo of classesInFile
                    if bufferPosition.row >= classInfo.startLine and bufferPosition.row <= classInfo.endLine
                        resolve(name)

                resolve(null)

    ###*
     * Retrieves an array of ranges that contain code that apply to the function the specified buffer position is in.
     * In other words, all these ranges are "inside this function's scope". If not currently inside a function, the
     * global scope is analyzed instead.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     *
     * @return {array}
    ###
    getFunctionScopeListAt: (editor, bufferPosition) ->
        openedScopes = 0
        closedScopes = 0

        currentScopeFunctionStart = null

        # First walk back until we find the actual start of the current scope.
        for row in [bufferPosition.row .. 0]
            line = editor.lineTextForBufferRow(row)

            continue if not line

            lastIndex = line.length - 1

            if row == bufferPosition.row
                lastIndex = bufferPosition.column

            # Scan the entire line, fetching the scope for each character position as one line can contain both a scope
            # start and end such as "} elseif (true) {". Here the scope descriptor will differ for different character
            # positions on the line.
            for i in [lastIndex .. 0]
                chain = editor.scopeDescriptorForBufferPosition([row, i]).getScopeChain()

                continue if chain.indexOf('comment') != -1 or chain.indexOf('.string-contents') != -1

                # }
                if line[i] == '}'
                    ++closedScopes
                # {
                if line[i] == '{'
                    ++openedScopes

                if chain.indexOf(".storage.type.function") != -1
                    # If openedScopes == closedScopes at this point, we're probably in a closure or nested function.
                    if openedScopes > closedScopes
                        currentScopeFunctionStart = new Point(row, i + 1)
                        break

                    # This can only happen if we're somewhere between the keyword and opening paranthesis of a function
                    # (e.g. in the parameter list, name, ...)
                    else if openedScopes == 0
                        return []

            break if currentScopeFunctionStart?

        beganInFunction = false

        if not currentScopeFunctionStart?
            currentScopeFunctionStart = new Point(0, 0)

        else
            beganInFunction = true

        # Now start scanning the range to find the actual scopes.
        ranges = []
        isInFunction = false
        parenthesesOpened = 0
        lastStart = currentScopeFunctionStart

        for row in [currentScopeFunctionStart.row .. bufferPosition.row]
            line = editor.lineTextForBufferRow(row)

            continue if not line

            startIndex = 0
            lastIndex = line.length - 1

            if row == currentScopeFunctionStart.row
                startIndex = currentScopeFunctionStart.column

            if row == bufferPosition.row
                lastIndex = bufferPosition.column

            for i in [startIndex .. lastIndex]
                chain = editor.scopeDescriptorForBufferPosition([row, i]).getScopeChain()

                if chain.indexOf('comment') != -1 or chain.indexOf('.string-contents') != -1
                    continue

                else if not isInFunction and chain.indexOf('.storage.type.function') != -1
                    ranges.push(new Range(lastStart, new Point(row, i)))

                    isInFunction = true

                else if line[i] == '{'
                    if isInFunction
                        ++parenthesesOpened

                else if line[i] == '}'
                    if isInFunction
                        --parenthesesOpened

                        if parenthesesOpened == 0
                            lastStart = new Point(row, i)
                            isInFunction = false

        ranges.push(new Range(lastStart, bufferPosition))

        if beganInFunction
            ranges[0].start.column -= 'function'.length

        return ranges

    ###*
     * Starts at the specified buffer position, and walks backwards or forwards to find the end of an expression.
     *
     * @param  {TextEditor} editor
     * @param  {Point}      bufferPosition
     * @param  {boolean}    backwards      Whether to walk backwards from the buffer position or forwards.
     *
     * @return {Point}
    ###
    determineBoundaryOfExpression: (editor, bufferPosition, backwards = true) ->
        finishedOn = null
        parenthesesOpened = 0
        parenthesesClosed = 0
        squareBracketsOpened = 0
        squareBracketsClosed = 0
        squiggleBracketsOpened = 0
        squiggleBracketsClosed = 0

        lastCharacter = null
        startedKeyword = false
        startedStaticClassName = false

        range = if backwards then [bufferPosition.row .. -1] else [bufferPosition.row .. editor.getLineCount()]

        for line in range
            lineText = editor.lineTextForBufferRow(line)

            continue if not lineText

            if line != bufferPosition.row
                lineRange = if backwards then [(lineText.length - 1) .. 0] else [0 .. (lineText.length - 1)]

            else
                lineRange = if backwards then [(bufferPosition.column - 1) .. 0] else [bufferPosition.column .. (lineText.length - 1)]

            for i in lineRange
                scopeDescriptor = editor.scopeDescriptorForBufferPosition([line, i]).getScopeChain()

                if scopeDescriptor.indexOf('.comment') != -1 or scopeDescriptor.indexOf('.string-contents') != -1
                    # Do nothing, we just keep parsing. (Comments can occur inside call stacks.)

                else if lineText[i] == '('
                    ++parenthesesOpened

                    # Ticket #164 - We're walking backwards, if we find an opening paranthesis that hasn't been closed
                    # anywhere, we know we must stop.
                    if backwards and parenthesesOpened > parenthesesClosed
                        finishedOn = true
                        break

                else if lineText[i] == ')'
                    ++parenthesesClosed

                    if not backwards and parenthesesClosed > parenthesesOpened
                        finishedOn = true
                        break

                else if lineText[i] == '['
                    ++squareBracketsOpened

                    # Same as above.
                    if backwards and squareBracketsOpened > squareBracketsClosed
                        finishedOn = true
                        break

                else if lineText[i] == ']'
                    ++squareBracketsClosed

                    if not backwards and squareBracketsClosed > squareBracketsOpened
                        finishedOn = true
                        break

                else if lineText[i] == '{'
                    ++squiggleBracketsOpened

                    # Same as above.
                    if backwards and squiggleBracketsOpened > squiggleBracketsClosed
                        finishedOn = true
                        break

                else if lineText[i] == '}'
                    ++squiggleBracketsClosed

                    if not backwards and squiggleBracketsClosed > squiggleBracketsOpened
                        finishedOn = true
                        break

                    if parenthesesOpened == parenthesesClosed
                        # Subscopes can only exist when e.g. a closure is embedded as an argument to a function call,
                        # in which case they will be inside parentheses. If we find a subscope outside parentheses, it
                        # means we've moved beyond the call stack to e.g. the end of an if statement.
                        if scopeDescriptor.indexOf('.scope.end') != -1
                            ++i
                            finishedOn = true
                            break

                # These will not be the same if, for example, we've entered a closure.
                else if parenthesesOpened == parenthesesClosed and
                        squareBracketsOpened == squareBracketsClosed and
                        squiggleBracketsOpened == squiggleBracketsClosed
                    # Variable name.
                    if lineText[i] == '$'
                        if backwards
                            # NOTE: We don't break because dollar signs can be taken up in expressions such as
                            # static::$foo.
                            finishedOn = false

                    # Reached an operator that can never be part of the current statement.
                    else if lineText[i] == ',' || lineText[i] == '?'
                        finishedOn = true
                        break

                    else if lineText[i] == ':'
                        # Only double colons can be part of an expression (for static access), but not single colons,
                        # which are commonly used in ternary operators.
                        if backwards and lastCharacter != ':' and (i == 0 or (i > 0 and lineText[i - 1] != ':'))
                            finishedOn = true
                            break

                        else if not backwards and lastCharacter != ':' and (i == lineText.length or (i < lineText.length and lineText[i + 1] != ':'))
                            finishedOn = true
                            break

                    # Stop on keywords such as 'return' or 'echo'.
                    else if scopeDescriptor.indexOf('.keyword.control') != -1 or scopeDescriptor.indexOf('.support.function.construct') != -1
                        finishedOn = true
                        break

                    # All kinds of operators, such as the equals sign, the array key-value operator, ...
                    # (the -> and :: for addressing class members are allowed).
                    else if scopeDescriptor.indexOf('.keyword.operator') != -1 and scopeDescriptor.indexOf('.keyword.operator.class') == -1
                        finishedOn = true
                        break

                    # <?php open tag, semi-colon, array opening braces, ... (the \ for inheritance is allowed).
                    else if scopeDescriptor.indexOf('.punctuation') != -1 and scopeDescriptor.indexOf('.punctuation.separator.inheritance') == -1
                        finishedOn = true
                        break

                    # For static class names and things like the self and parent keywords, we won't know when to stop.
                    # These always appear the start of the call stack, so we know we can stop if we find them.
                    else if backwards and scopeDescriptor.indexOf('.support.class') != -1
                        startedStaticClassName = true

                    else if backwards and scopeDescriptor.indexOf('.storage.type') != -1
                        startedKeyword = true

                if startedStaticClassName and scopeDescriptor.indexOf('.support.class') == -1 and scopeDescriptor.indexOf('.support.other.namespace') == -1
                    finishedOn = true
                    break

                else if startedKeyword and scopeDescriptor.indexOf('.storage.type') == -1
                    finishedOn = true
                    break

                lastCharacter = lineText[i]

            if finishedOn?
                break

        if backwards and finishedOn == true
            ++i

        return new Point(line, i)

    ###*
     * Removes content inside the specified open and close character pairs (including nested pairs).
     *
     * @param {string} text           String to analyze.
     * @param {string} openCharacter  The character that opens the pair.
     * @param {string} closeCharacter The character that closes the pair.
     *
     * @return {string}
    ###
    stripPairContent: (text, openCharacter, closeCharacter) ->
        i = 0
        openCount = 0
        closeCount = 0
        startIndex = -1

        while i < text.length
            if text[i] == openCharacter
                ++openCount

                if openCount == 1
                    startIndex = i

            else if text[i] == closeCharacter
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
        text = text.trim()

        # Remove singe line comments
        regex = /\/\/.*\n/g
        text = text.replace regex, (match) =>
            return ''

        # Remove multi-line comments
        regex = /\/\*(.|\n)*?\*\//g
        text = text.replace regex, (match) =>
            return ''

        # The start of the call stack may be wrapped in parentheses, e.g. ""(new Foo())->test", unwrap them. Note that
        # "($this)->" is invalid (at least in PHP 5.6).
        regex = /^\(new\s+(.|\n)+?\)/g
        text = text.replace regex, (match) =>
            return match.substr(1, match.length - 2)

        if /function\s+([A-Za-z0-9_]\s*)?\(/.test(text)
            text = @stripPairContent(text, '{', '}')

        # Remove content inside parantheses (including nested parantheses).
        text = @stripPairContent(text, '(', ')')

        return [] if not text

        elements = text.split(/(?:\-\>|::)/)

        for key, element of elements
            elements[key] = element.trim()

        return elements

    ###*
     * Does exactly the same as {@see retrieveSanitizedCallStack}, but will automatically retrieve the relevant code
     * of the call at the specified location in the buffer.
     *
     * @param  {TextEditor} editor
     * @param  {Point}      bufferPosition
     * @param  {boolean}    backwards      Whether to walk backwards from the buffer position or forwards.
     *
     * @return {Object}
    ###
    retrieveSanitizedCallStackAt: (editor, bufferPosition, backwards = true) ->
        boundary = @determineBoundaryOfExpression(editor, bufferPosition, backwards)

        textSlice = editor.getTextInBufferRange([boundary, bufferPosition])

        return @retrieveSanitizedCallStack(textSlice)

    ###*
     * Retrieves the type of a variable, relative to the context at the specified buffer location. Class names will
     * be returned in their full form (full class name, but not necessarily with a leading slash).
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     * @param {string}     name
     *
     * @return {string|null}
    ###
    getVariableType: (editor, bufferPosition, name) ->
        element = name

        if element.replace(/\$[a-zA-Z0-9_]+/g, "").trim().length > 0
            return null

        if element.trim().length == 0
            return null

        elementForRegex = '\\' + element

        bestMatch = null

        scopeList = @getFunctionScopeListAt(editor, bufferPosition).reverse()

        for range in scopeList
            scanStartPosition = range.start

            # Check for a type annotation in the style of /** @var FooType $someVar */.
            regexTypeAnnotation = ///\/\*\*\s*@var\s+(#{classRegexPart}(?:\[\])?)\s+#{elementForRegex}\s*(\s.*)?\*\////

            editor.getBuffer().backwardsScanInRange regexTypeAnnotation, [scanStartPosition, bufferPosition], (matchInfo) =>
                bestMatch = @resolveTypeAt(editor, matchInfo.range.start, matchInfo.match[1])

                matchInfo.stop()

            return bestMatch if bestMatch # An annotation is definitive.

            # Check for a type annotation in the style of /** @var $someVar FooType */.
            regexReverseTypeAnnotation = ///\/\*\*\s*@var\s+#{elementForRegex}\s+(#{classRegexPart}(?:\[\])?)\s*(\s.*)?\*\////

            editor.getBuffer().backwardsScanInRange regexReverseTypeAnnotation, [scanStartPosition, bufferPosition], (matchInfo) =>
                bestMatch = @resolveTypeAt(editor, matchInfo.range.start, matchInfo.match[1])

                matchInfo.stop()

            return bestMatch if bestMatch # An annotation is definitive.

            # Check if there is a funtion definition with a type hint for the variable.
            regexFunction = ///function(?:\s+([a-zA-Z0-9_]+))?\s*\([^{]*?(?:(#{classRegexPart})\s+)?#{elementForRegex}[^{]*?\)///g

            editor.getBuffer().backwardsScanInRange regexFunction, [scanStartPosition, bufferPosition], (matchInfo) =>
                chain = editor.scopeDescriptorForBufferPosition(matchInfo.range.start).getScopeChain()

                return if chain.indexOf('comment') != -1 or chain.indexOf('.string-contents') != -1

                scanStartPosition = matchInfo.range.end

                typeHint = matchInfo.match[2]

                if typeHint?.length > 0
                    bestMatch = @resolveTypeAt(editor, matchInfo.range.start, typeHint)

                else
                    functionName = matchInfo.match[1]

                    # Can be empty for closures.
                    if functionName?.length > 0
                        try
                            currentClass = @determineCurrentClassName(editor, bufferPosition)

                            response = null
                            functionInfo = null

                            if currentClass
                                response = @proxy.getClassInfo(currentClass)
                                response = response.methods

                            else
                                response = @proxy.getGlobalFunctions()

                            if functionName of response
                                parameters = response[functionName].parameters

                                for param in parameters
                                    # NOTE: We compare without dollar sign.
                                    if param.name == element.substr(1)
                                        if param.fullType
                                            bestMatch = param.fullType

                                        break

                        catch error
                            # This data isn't useful.

                matchInfo.stop()

            # Check to see if we can find an assignment somewhere, this is the most common case.
            regexAssignment = ///#{elementForRegex}\s*=\s*///g

            editor.getBuffer().backwardsScanInRange regexAssignment, [scanStartPosition, bufferPosition], (matchInfo) =>
                chain = editor.scopeDescriptorForBufferPosition(matchInfo.range.start).getScopeChain()

                return if chain.indexOf('comment') != -1 or chain.indexOf('.string-contents') != -1

                boundary = @determineBoundaryOfExpression(editor, matchInfo.range.end, false)

                if boundary.row < bufferPosition.row or (boundary.row == bufferPosition.row and boundary.column <= bufferPosition.column)
                    scanStartPosition = boundary

                    textSlice = editor.getTextInBufferRange([matchInfo.range.end, boundary])

                    elements = @retrieveSanitizedCallStack(textSlice)

                    # NOTE: bestMatch could now be null, but this line is still the closest match. The fact that we
                    # don't recognize the class name is irrelevant.
                    try
                        bestMatch = @getResultingTypeFromCallStack(editor, matchInfo.range.start, elements)

                    catch error
                        bestMatch = null

                    matchInfo.stop()

            # Check if we can find a type hint from a catch statement that is more closely located to the requested
            # position (i.e. one that is a better match).
            regexCatch = ///catch\s*\(\s*(#{classRegexPart})\s+#{elementForRegex}\s*\)///g

            editor.getBuffer().backwardsScanInRange regexCatch, [scanStartPosition, bufferPosition], (matchInfo) =>
                chain = editor.scopeDescriptorForBufferPosition(matchInfo.range.start).getScopeChain()

                return if chain.indexOf('comment') != -1 or chain.indexOf('.string-contents') != -1

                scanStartPosition = matchInfo.range.end

                bestMatch = @resolveTypeAt(editor, matchInfo.range.start, matchInfo.match[1])

                matchInfo.stop()

            # Check if we can find an instanceof.
            regexInstanceof = ///if\s*\(\s*#{elementForRegex}\s+instanceof\s+(#{classRegexPart})\s*\)///g

            editor.getBuffer().backwardsScanInRange regexInstanceof, [scanStartPosition, bufferPosition], (matchInfo) =>
                chain = editor.scopeDescriptorForBufferPosition(matchInfo.range.start).getScopeChain()

                return if chain.indexOf('comment') != -1 or chain.indexOf('.string-contents') != -1

                scanStartPosition = matchInfo.range.end

                bestMatch = @getResultingTypeFromCallStack(editor, matchInfo.range.start, [matchInfo.match[1]])

                matchInfo.stop()

            # Check if we can find a foreach.
            # foreach\s+\((\$[a-zA-Z0-9_]+)\s+as\s+(?:(?:\$[a-zA-Z0-9_]+)\s*=>)?\s*(\$[a-zA-Z0-9_]+)\)
            regexForeach = ///(foreach\s+\(.+)\s+as\s+(?:(?:\$[a-zA-Z0-9_]+)\s*=>)?\s*(#{elementForRegex})\)///

            editor.getBuffer().backwardsScanInRange regexForeach, [scanStartPosition, bufferPosition], (matchInfo) =>
                chain = editor.scopeDescriptorForBufferPosition(matchInfo.range.start).getScopeChain()

                return if chain.indexOf('comment') != -1 or chain.indexOf('.string-contents') != -1

                scanStartPosition = matchInfo.range.end

                position = matchInfo.range.start
                position.column += matchInfo.match[1].length

                callStack = @retrieveSanitizedCallStackAt(editor, position)
                listType = @getResultingTypeFromCallStack(editor, bufferPosition, callStack)

                if listType? and listType.endsWith('[]')
                    bestMatch = listType.substr(0, listType.length - 2)

                    matchInfo.stop()

            break if bestMatch

        if not bestMatch and name == '$this'
            bestMatch = @determineCurrentClassName(editor, bufferPosition)

        return bestMatch

    ###*
     * Parses all elements from the given call stack to return the last type (if any). Returns null if the type of a
     * member could not be deduced (e.g. because it does not exist). This method can also deal with call stacks of one
     * element to fetch the type of a single item.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     * @param {array}      callStack
     *
     * @throws an error if one of the elements in the call stack does not exist.
     *
     * @return {string|null}
    ###
    getResultingTypeFromCallStack: (editor, bufferPosition, callStack) ->
        i = 0
        className = null

        return null if not callStack or callStack.length == 0

        firstElement = callStack.shift()
        propertyAccessNeedsDollarSign = false

        if firstElement[0] == '$'
            className = @getVariableType(editor, bufferPosition, firstElement)

        else if firstElement == 'static' or firstElement == 'self'
            propertyAccessNeedsDollarSign = true

            className = @determineCurrentClassName(editor, bufferPosition)

        else if firstElement == 'parent'
            propertyAccessNeedsDollarSign = true

            currentClassName = @determineCurrentClassName(editor, bufferPosition)

            if currentClassName?
                currentClassInfo = @proxy.getClassInfo(currentClassName)

                if currentClassInfo.parents.length > 0
                    className = currentClassInfo.parents[0]

        else if firstElement[0] == '['
            className = 'array'

        else if /^(0x)?\d+$/.test(firstElement)
            className = 'int'

        else if /^\d+.\d+$/.test(firstElement)
            className = 'float'

        else if /^(true|false)$/.test(firstElement)
            className = 'bool'

        else if /^"(.|\n)*"$/.test(firstElement)
            className = 'string'

        else if /^'(.|\n)*'$/.test(firstElement)
            className = 'string'

        else if /^array\s*\(/.test(firstElement)
            className = 'array'

        else if /^function\s*\(/.test(firstElement)
            className = '\\Closure'

        else if (matches = firstElement.match(///^new\s+(#{classRegexPart})(?:\(\))?///))
            className = @getResultingTypeFromCallStack(editor, bufferPosition, [matches[1]])

        else if (matches = firstElement.match(///^clone\s+(\$[a-zA-Z0-9_]+)///))
            className = @getResultingTypeFromCallStack(editor, bufferPosition, [matches[1]])

        else if (matches = firstElement.match(/^(.*?)\(\)$/))
            # Global PHP function.
            functions = @proxy.getGlobalFunctions()

            if matches[1] of functions
                className = functions[matches[1]].return.type

        else if ///#{classRegexPart}///.test(firstElement)
            propertyAccessNeedsDollarSign = true

            # Static class name.
            className = @resolveTypeAt(editor, bufferPosition, firstElement)

        else
            className = null # No idea what this is.

        return null if not className

        # We now know what class we need to start from, now it's just a matter of fetching the return types of members
        # in the call stack.
        for element in callStack
            if not @isBasicType(className)
                # className = @getTypeForMember(className, element)

                info = @proxy.getClassInfo(className)

                className = null

                if element.indexOf('()') != -1
                    element = element.replace('()', '')

                    if element of info.methods
                        className = info.methods[element].return.resolvedType

                else if element of info.constants
                    className = info.constants[element].return.resolvedType

                else
                    isValidPropertyAccess = false

                    if not propertyAccessNeedsDollarSign
                        isValidPropertyAccess = true

                    else if element.length > 0 and element[0] == '$'
                        element = element.substr(1)
                        isValidPropertyAccess = true

                    if isValidPropertyAccess and element of info.properties
                        className = info.properties[element].return.resolvedType

            else
                className = null
                break

            propertyAccessNeedsDollarSign = false

        return className

    ###*
     * Retrieves the call stack of the function or method that is being invoked at the specified position. This can be
     * used to fetch information about the function or method call the cursor is in.
     *
     * @param {TextEditor} editor
     * @param {Point}      bufferPosition
     *
     * @example "$this->test(1, function () {},| 2);" (where the vertical bar denotes the cursor position) will yield
     *          ['$this', 'test'].
     *
     * @return {Object|null} With elements 'callStack' (array), 'argumentIndex', which denotes the argument in the
     *                       parameter list the position is located at, and bufferPosition which denotes the buffer
     *                       position the invocation was found at. Returns 'null' if not in a method or function call.
    ###
    getInvocationInfoAt: (editor, bufferPosition) ->
        scopesOpened = 0
        scopesClosed = 0
        bracketsOpened = 0
        bracketsClosed = 0
        parenthesesOpened = 0
        parenthesesClosed = 0

        argumentIndex = 0

        # This is purely done for optimization. Fetching the scope descriptor for every character index is very
        # expensive and is what makes this function slow. By keeping this list we can fetch it only when necessary.
        interestingCharacters = [
            '{', '}',
            '[', ']',
            '(', ')',
            ';', ','
        ]

        for line in [bufferPosition.row .. 0]
            lineText = editor.lineTextForBufferRow(line)
            length = lineText.length

            if line == bufferPosition.row
                length = bufferPosition.column

            for i in [length - 1 .. 0]
                if lineText[i] in interestingCharacters
                    chain = editor.scopeDescriptorForBufferPosition([line, i]).getScopeChain()

                    if chain.indexOf('.comment') != -1 or chain.indexOf('.string-contents') != -1
                        continue

                    else if lineText[i] == '}'
                        ++scopesClosed

                    else if lineText[i] == '{'
                        ++scopesOpened

                        if scopesOpened > scopesClosed
                            return null # We reached the start of a block, we can never be in a method call.

                    else if lineText[i] == ']'
                        ++bracketsClosed

                    else if lineText[i] == '['
                        ++bracketsOpened

                        if bracketsOpened > bracketsClosed
                            # We must have been inside an array argument, reset.
                            argumentIndex = 0
                            --bracketsOpened

                    else if lineText[i] == ')'
                        ++parenthesesClosed

                    else if lineText[i] == '('
                        ++parenthesesOpened

                    else if scopesOpened == scopesClosed
                        if lineText[i] == ';'
                            return null # We've moved too far and reached another expression, stop here.

                        else if lineText[i] == ','
                            if parenthesesOpened == (parenthesesClosed + 1)
                                # Pretend the parentheses were closed, the user is probably inside an argument that
                                # contains parentheses.
                                ++parenthesesClosed

                            if bracketsOpened >= bracketsClosed and parenthesesOpened == parenthesesClosed
                                ++argumentIndex

                if scopesOpened == scopesClosed and parenthesesOpened == (parenthesesClosed + 1)
                    chain = editor.scopeDescriptorForBufferPosition([line, i]).getScopeChain()

                    isClassName = (chain.indexOf('.support.class') != -1)
                    isKeyword = (chain.indexOf('.storage.type') != -1)

                    if chain.indexOf('.function-call') != -1 or
                       chain.indexOf('.support.function') != -1 or
                       isClassName or
                       isKeyword
                        currentBufferPosition = new Point(line, i+1)

                        callStack = @retrieveSanitizedCallStackAt(editor, currentBufferPosition)

                        if not isKeyword or (isKeyword and (callStack[0] == 'self' or callStack[0] == 'static'))
                            return {
                                callStack      : callStack
                                type           : if (isClassName or isKeyword) then 'instantiation' else 'function'
                                argumentIndex  : argumentIndex
                                bufferPosition : currentBufferPosition
                            }

        return null
