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

    it "correctly operates when not in a function (global scope).", ->
        source =
            """
            <?php

            function foo($param)
            {
                $nestedVar = null;
            }

            function test($param1, $param2)
            {
                $closure = function ($closureParam) use ($something) {
                    $test = 3;
                };
            }

            //
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 0

        expectedResult = [
            new Range(new Point(0, 0), new Point(2, 0)),
            new Range(new Point(5, 0), new Point(7, 0)),
            new Range(new Point(12, 0), bufferPosition)
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
            new Range(new Point(2, 0), bufferPosition)
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
            new Range(new Point(2, 0), new Point(4, 15)),
            new Range(new Point(6, 4), bufferPosition)
        ]

        expect(parser.getFunctionScopeListAt(editor, bufferPosition)).toEqual(expectedResult)
