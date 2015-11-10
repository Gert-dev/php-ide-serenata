{Point, Range} = require 'atom'

Parser = require '../../lib/Parser'

describe "getFunctionScopeListAt", ->
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

    it "correctly returns an empty array when not in a function.", ->
        source =
            """
            <?php

            function test()
            {

            }

            //
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 0

        expectedResult = [

        ]

        expect(parser.getFunctionScopeListAt(editor, bufferPosition)).toEqual(expectedResult)

    it "correctly stops at a function signature and returns a single range.", ->
        source =
            """
            <?php

            function test($param1, $param2)
            {
                //
            }
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 2
            column: 0

        expectedResult = [
            new Range(new Point(2, 7), bufferPosition)
        ]

        expect(parser.getFunctionScopeListAt(editor, bufferPosition)).toEqual(expectedResult)

    it "correctly skips nested function scopes and returns multiple ranges.", ->
        source =
            """
            <?php

            function test($param1, $param2)
            {
                $closure = function ($closureParam) use ($something) {

                };

                //
            }
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 2
            column: 0

        expectedResult = [
            new Range(new Point(6, 4), bufferPosition)
            new Range(new Point(2, 7), new Point(4, 22)),
        ]

        expect(parser.getFunctionScopeListAt(editor, bufferPosition)).toEqual(expectedResult)
