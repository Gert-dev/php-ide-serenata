Parser = require '../../lib/Parser'

describe "getInvocationInfoAt", ->
    editor = null
    grammar = null
    parser = new Parser(null)

    beforeEach ->
        waitsForPromise ->
            atom.workspace.open().then (result) ->
                editor = result

        waitsForPromise ->
            atom.packages.activatePackage('language-php')

        runs ->
            grammar = atom.grammars.selectGrammar('.source.php')

        waitsFor ->
            grammar and editor

        runs ->
            editor.setGrammar(grammar)

    it "correctly deals with a single line invocation.", ->
        source =
            """
            <?php

            $this->test(1, 2, 3
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        result = parser.getInvocationInfoAt(editor, bufferPosition)

        expect(result.bufferPosition.row).toEqual(2)
        expect(result.bufferPosition.column).toEqual(11)
        expect(result.callStack).toEqual(['$this', 'test'])
        expect(result.argumentIndex).toEqual(2)

    it "correctly deals with a multi-line invocation.", ->
        source =
            """
            <?php

            $this->test(
                1,
                2,
                3
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        result = parser.getInvocationInfoAt(editor, bufferPosition)

        expect(result.bufferPosition.row).toEqual(2)
        expect(result.bufferPosition.column).toEqual(11)
        expect(result.callStack).toEqual(['$this', 'test'])
        expect(result.argumentIndex).toEqual(2)

    it "correctly deals with more complex nested invocation arguments.", ->
        source =
            """
            <?php

            builtin_func(
                ['test', $this->foo()],
                function ($a) {
                    // Something here.
                    $this->something();
                },
                3
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        result = parser.getInvocationInfoAt(editor, bufferPosition)

        expect(result.bufferPosition.row).toEqual(2)
        expect(result.bufferPosition.column).toEqual(12)
        expect(result.callStack).toEqual(['builtin_func'])
        expect(result.argumentIndex).toEqual(2)

    it "correctly deals with more complex nested invocation arguments.", ->
        source =
            """
            <?php

            builtin_func(
                1,
                2,
                ['test'
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        result = parser.getInvocationInfoAt(editor, bufferPosition)

        expect(result.bufferPosition.row).toEqual(2)
        expect(result.bufferPosition.column).toEqual(12)
        expect(result.callStack).toEqual(['builtin_func'])
        expect(result.argumentIndex).toEqual(2)

    it "correctly returns null when not in an invocation.", ->
        source =
            """
            <?php

            if ($this->test() as $test) {
                if (true) {

                }
            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        result = parser.getInvocationInfoAt(editor, bufferPosition)

        expect(result).toEqual(null)

    it "correctly returns null when not in an invocation.", ->
        source =
            """
            <?php

            $this->test();


            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        result = parser.getInvocationInfoAt(editor, bufferPosition)

        expect(result).toEqual(null)

    it "correctly returns null when not in an invocation.", ->
        source =
            """
            <?php

            function test($a, $b)
            {

            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        result = parser.getInvocationInfoAt(editor, bufferPosition)

        expect(result).toEqual(null)
