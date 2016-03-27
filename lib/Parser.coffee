{Point, Range} = require 'atom'

module.exports =

##*
# Parser that handles all kinds of tasks related to parsing PHP code.
##
class Parser
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
        didStartedInsideString = (editor.scopeDescriptorForBufferPosition([line, i]).getScopeChain().indexOf('.meta.string-contents.quoted.double') != -1)

        range = if backwards then [bufferPosition.row .. -1] else [bufferPosition.row .. editor.getLineCount()]

        for line in range
            lineText = editor.lineTextForBufferRow(line)

            continue if not lineText

            if line != bufferPosition.row
                lineRange = if backwards then [(lineText.length - 1) .. 0] else [0 .. (lineText.length - 1)]

            else
                lineRange = if backwards then [(bufferPosition.column - 1) .. 0] else [bufferPosition.column .. (lineText.length - 1)]

            for i in lineRange
                scopeDescriptorList = editor.scopeDescriptorForBufferPosition([line, i])
                scopeDescriptor = scopeDescriptorList.getScopeChain()

                if scopeDescriptor.indexOf('.comment') != -1 or (not not didStartedInsideString and scopeDescriptor.indexOf('.string-contents') != -1)
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
