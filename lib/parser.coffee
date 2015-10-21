proxy = require "./proxy.coffee"

module.exports =
    structureStartRegex: /(?:abstract class|class|trait|interface)\s+(\w+)/
    useStatementRegex: /(?:use)(?:[^\w\\])([\w\\]+)(?![\w\\])(?:(?:[ ]+as[ ]+)(\w+))?(?:;)/

    # Simple cache to avoid duplicate computation for each providers
    cache: []

    ###*
     * Retrieves the class the specified term (method or property) is being invoked on.
     *
     * @param  {TextEditor} editor         TextEditor to search for namespace of term.
     * @param  {string}     term           Term to search for.
     * @param  {Point}      bufferPosition The cursor location the term is at.
     *
     * @return {string}
     *
     * @example Invoking it on MyMethod::foo()->bar() will ask what class 'bar' is invoked on, which will whatever type
     *          foo returns.
    ###
    getCalledClass: (editor, term, bufferPosition) ->
        fullCall = @getStackClasses(editor, bufferPosition)

        if fullCall?.length == 0 or !term
            return

        return @parseElements(editor, bufferPosition, fullCall)

    ###*
     * Get all variables declared in the current function
     * @param {TextEdutir} editor         Atom text editor
     * @param {Range}      bufferPosition Position of the current buffer
    ###
    getAllVariablesInFunction: (editor, bufferPosition) ->
        # return if not @isInFunction(editor, bufferPosition)
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
     * Retrieves the full class name. If the class name is a FQCN (Fully Qualified Class Name), it already is a full
     * name and it is returned as is. Otherwise, the current namespace and use statements are scanned.
     *
     * @param {TextEditor}  editor    Text editor instance.
     * @param {string|null} className Name of the class to retrieve the full name of. If null, the current class will
     *                                be returned (if any).
     *
     * @return string
    ###
    getFullClassName: (editor, className = null) ->
        if className == null
            className = ''

        if className and className[0] == "\\"
            return className.substr(1) # FQCN, not subject to any further context.

        usePattern = /(?:use)(?:[^\w\\\\])([\w\\\\]+)(?![\w\\\\])(?:(?:[ ]+as[ ]+)(\w+))?(?:;)/
        namespacePattern = /(?:namespace)(?:[^\w\\\\])([\w\\\\]+)(?![\w\\\\])(?:;)/
        definitionPattern = /(?:abstract class|class|trait|interface)\s+(\w+)/

        text = editor.getText()

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
            methodsRequest = proxy.methods(fullClass)

            if not methodsRequest?.filename
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
     * Checks if the given name is a class or not
     * @param  {string}  name Name to check
     * @return {Boolean}
    ###
    isClass: (name) ->
        return name.substr(0,1).toUpperCase() + name.substr(1) == name

    ###*
     * Checks if the current buffer is in a functon or not
     * @param {TextEditor} editor         Atom text editor
     * @param {Range}      bufferPosition Position of the current buffer
     * @return bool
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
     * Retrieves the stack of elements in a stack of calls such as "self::xxx->xxxx".
     *
     * @param  {TextEditor} editor
     * @param  {Point}       position
     *
     * @return {Object}
    ###
    getStackClasses: (editor, position) ->
        return unless position?

        line = position.row

        finished = false
        parenthesesOpened = 0
        parenthesesClosed = 0
        squiggleBracketsOpened = 0
        squiggleBracketsClosed = 0

        while line > 0
            lineText = editor.lineTextForBufferRow(line)

            if line != position.row
                i = (lineText.length - 1)

            else
                i = position.column - 1

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
        textSlice = editor.getTextInBufferRange([[line, i], position]).trim()

        return @parseStackClass(textSlice)

    ###*
     * Removes content inside parantheses (including nested parantheses).
     * @param {string} text String to analyze.
     * @return String
    ###
    stripParanthesesContent: (text) ->
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
     * Parse stack class elements
     * @param {string} text String of the stack class
     * @return Array
    ###
    parseStackClass: (text) ->
        # Remove singe line comments
        regx = /\/\/.*\n/g
        text = text.replace regx, (match) =>
            return ''

        # Remove multi line comments
        regx = /\/\*[^(\*\/)]*\*\//g
        text = text.replace regx, (match) =>
            return ''

        # Remove content inside parantheses (including nested parantheses).
        text = @stripParanthesesContent(text)

        # Get the full text
        return [] if not text

        elements = text.split(/(?:\-\>|::)/)
        # elements = text.split("->")

        # Remove parenthesis and whitespaces
        for key, element of elements
            element = element.replace /^\s+|\s+$/g, ""
            if element[0] == '{' or element[0] == '(' or element[0] == '['
                element = element.substring(1)
            else if element.indexOf('return ') == 0
                element = element.substring('return '.length)

            elements[key] = element

        return elements

    ###*
     * Get the type of a variable
     *
     * @param {TextEditor} editor
     * @param {Range}      bufferPosition
     * @param {string}     element        Variable to search
    ###
    getVariableType: (editor, bufferPosition, element) ->
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
                    bestMatch = @getFullClassName(editor, matchesNew[1])

            if not bestMatch
                # Check for catch(XXX $xxx)
                matchesCatch = regexCatch.exec(line)

                if null != matchesCatch
                    bestMatchRow = lineNumber
                    bestMatch = @getFullClassName(editor, matchesCatch[1])

            if not bestMatch
                # Check for a variable assignment $x = ...
                matches = regexElement.exec(line)

                if null != matches
                    value = matches[1]
                    elements = @parseStackClass(value)
                    elements.push("") # Push one more element to get fully the last class

                    newPosition =
                        row : lineNumber
                        column: bufferPosition.column

                    # NOTE: bestMatch could now be null, but this line is still the closest match. The fact that we
                    # don't recognize the class name is irrelevant.
                    bestMatchRow = lineNumber
                    bestMatch = @parseElements(editor, newPosition, elements)

            if not bestMatch
                # Check for function or closure parameter type hints and the docblock.
                regexFunction = new RegExp("function(?:[\\s]+([a-zA-Z]+))?[\\s]*[\\(](?:(?![a-zA-Z\\_\\\\]*[\\s]*\\#{element}).)*[,\\s]?([a-zA-Z\\_\\\\]*)[\\s]*\\#{element}[a-zA-Z0-9\\s\\$\\\\,=\\\"\\\'\(\)]*[\\s]*[\\)]", "g")
                matches = regexFunction.exec(line)

                if null != matches
                    typeHint = matches[2]

                    if typeHint.length > 0
                        return @getFullClassName(editor, typeHint)

                    funcName = matches[1]

                    # Can be empty for closures.
                    if funcName and funcName.length > 0
                        params = proxy.docParams(@getFullClassName(editor), funcName)

                        if params.params? and params.params[element]?
                            return @getFullClassName(editor, params.params[element])

            chain = editor.scopeDescriptorForBufferPosition([lineNumber, line.length]).getScopeChain()

            # Annotations in comments can optionally override the variable type.
            if chain.indexOf("comment") != -1
                # Check if the line before contains a /** @var FooType */, which overrides the type of the variable
                # immediately below it. This will not evaluate to /** @var FooType $someVar */ (see below for that).
                if bestMatchRow and lineNumber == (bestMatchRow - 1)
                    regexVar = /\@var[\s]+([a-zA-Z_\\]+)(?![\w]+\$)/g
                    matches = regexVar.exec(line)

                    if null != matches
                        return @getFullClassName(editor, matches[1])

                # Check if there is an PHPStorm-style type inline docblock present /** @var FooType $someVar */.
                regexVarWithVarName = new RegExp("\\@var[\\s]+([a-zA-Z_\\\\]+)[\\s]+\\#{element}", "g")
                matches = regexVarWithVarName.exec(line)

                if null != matches
                    return @getFullClassName(editor, matches[1])

                # Check if there is an IntelliJ-style type inline docblock present /** @var $someVar FooType */.
                regexVarWithVarName = new RegExp("\\@var[\\s]+\\#{element}[\\s]+([a-zA-Z_\\\\]+)", "g")
                matches = regexVarWithVarName.exec(line)

                if null != matches
                    return @getFullClassName(editor, matches[1])

            # We've reached the function definition, other variables don't apply to this scope.
            if chain.indexOf("function") != -1
                break

            --lineNumber

        return bestMatch

    ###*
     * Retrieves contextual information about the class member at the specified location in the editor.
     *
     * @param {TextEditor} editor         TextEditor to search for namespace of term.
     * @param {string}     term           Term to search for.
     * @param {Point}      bufferPosition The cursor location the term is at.
     * @param {Object}     calledClass    Information about the called class (optional).
    ###
    getMemberContext: (editor, term, bufferPosition, calledClass) ->
        if not calledClass
            calledClass = @getCalledClass(editor, term, bufferPosition)

        if not calledClass
            return

        proxy = require '../services/php-proxy.coffee'
        methods = proxy.methods(calledClass)

        if not methods
            return

        if methods.error? and methods.error != ''
            atom.notifications.addError('Failed to get methods for ' + calledClass, {
                'detail': methods.error.message
            })

            return

        if methods.names.indexOf(term) == -1
            return

        value = methods.values[term]

        # If there are multiple matches, just select the first method.
        if value instanceof Array
            for val in value
                if val.isMethod
                    value = val
                    break

        return value

    ###*
     * Parse all elements from the given array to return the last className (if any)
     * @param  Array elements Elements to parse
     * @return string|null full class name of the last element
    ###
    parseElements: (editor, bufferPosition, elements) ->
        loop_index = 0
        className  = null
        if not elements?
            return

        for element in elements
            # $this keyword
            if loop_index == 0
                if element[0] == '$'
                    className = @getVariableType(editor, bufferPosition, element)

                    # NOTE: The type of $this can also be overridden locally by a docblock.
                    if element == '$this' and not className
                        className = @getFullClassName(editor)

                    loop_index++
                    continue

                else if element == 'static' or element == 'self'
                    className = @getFullClassName(editor)
                    loop_index++
                    continue

                else if element == 'parent'
                    className = @getParentClass(editor)
                    loop_index++
                    continue

                else
                    className = @getFullClassName(editor, element)
                    loop_index++
                    continue

            # Last element
            if loop_index >= elements.length - 1
                break

            if className == null
                break

            methods = proxy.autocomplete(className, element)

            # Element not found or no return value
            if not methods.class? or not @isClass(methods.class)
                className = null
                break

            className = methods.class
            loop_index++

        # If no data or a valid end of line, OK
        if elements.length > 0 and (elements[elements.length-1].length == 0 or elements[elements.length-1].match(/([a-zA-Z0-9]$)/g))
            return className

        return null

    ###*
     * Gets the full words from the buffer position given.
     * E.g. Getting a class with its namespace.
     * @param  {TextEditor}     editor   TextEditor to search.
     * @param  {BufferPosition} position BufferPosition to start searching from.
     * @return {string}  Returns a string of the class.
    ###
    getFullWordFromBufferPosition: (editor, position) ->
        foundStart = false
        foundEnd = false
        startBufferPosition = []
        endBufferPosition = []
        forwardRegex = /-|(?:\()[\w\[\$\(\\]|\s|\)|;|'|,|"|\|/
        backwardRegex = /\(|\s|\)|;|'|,|"|\|/
        index = -1
        previousText = ''

        loop
            index++
            startBufferPosition = [position.row, position.column - index - 1]
            range = [[position.row, position.column], [startBufferPosition[0], startBufferPosition[1]]]
            currentText = editor.getTextInBufferRange(range)
            if backwardRegex.test(editor.getTextInBufferRange(range)) || startBufferPosition[1] == -1 || currentText == previousText
                foundStart = true
            previousText = editor.getTextInBufferRange(range)
            break if foundStart
        index = -1
        loop
            index++
            endBufferPosition = [position.row, position.column + index + 1]
            range = [[position.row, position.column], [endBufferPosition[0], endBufferPosition[1]]]
            currentText = editor.getTextInBufferRange(range)
            if forwardRegex.test(currentText) || endBufferPosition[1] == 500 || currentText == previousText
                foundEnd = true
            previousText = editor.getTextInBufferRange(range)
            break if foundEnd

        startBufferPosition[1] += 1
        endBufferPosition[1] -= 1
        return editor.getTextInBufferRange([startBufferPosition, endBufferPosition])

    ###*
     * Gets the correct selector when a class or namespace is clicked.
     *
     * @param  {jQuery.Event}  event  A jQuery event.
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

    ###*
     * Gets the parent class of the current class opened in the editor
     * @param  {TextEditor} editor Editor with the class in.
     * @return {string}            The namespace and class of the parent
    ###
    getParentClass: (editor) ->
        text = editor.getText()

        lines = text.split('\n')
        for line in lines
            line = line.trim()

            # If we found extends keyword, return the class
            if line.indexOf('extends ') != -1
                words = line.split(' ')
                extendsIndex = words.indexOf('extends')
                return @getFullClassName(editor, words[extendsIndex + 1])

    ###*
     * Finds the buffer position of the word given
     * @param  {TextEditor} editor TextEditor to search
     * @param  {string}     term   The function name to search for
     * @return {mixed}             Either null or the buffer position of the function.
    ###
    findBufferPositionOfWord: (editor, term, regex, line = null) ->
        if line != null
            lineText = editor.lineTextForBufferRow(line)
            result = @checkLineForWord(lineText, term, regex)
            if result != null
                return [line, result]
        else
            text = editor.getText()
            row = 0
            lines = text.split('\n')
            for line in lines
                result = @checkLineForWord(line, term, regex)
                if result != null
                    return [row, result]
                row++
        return null;

    ###*
     * Checks the lineText for the term and regex matches
     * @param  {string}   lineText The line of text to check.
     * @param  {string}   term     Term to look for.
     * @param  {regex}    regex    Regex to run on the line to make sure it's valid
     * @return {null|int}          Returns null if nothing was found or an
     *                             int of the column the term is on.
    ###
    checkLineForWord: (lineText, term, regex) ->
        if regex.test(lineText)
            words = lineText.split(' ')
            propertyIndex = 0
            for element in words
                if element.indexOf(term) != -1
                    break
                propertyIndex++;

              reducedWords = words.slice(0, propertyIndex).join(' ')
              return reducedWords.length + 1
        return null
