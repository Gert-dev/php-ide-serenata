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
                $test = function (\\TestNamespace\\Bar\\BarInterface $blub, \\TestNamespace\\Bar\\BarClass $bar2) {
                    // $test2->
                    // $test3->
                    // $bar2->
                    // $blub->
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
            new Range(new Point(15, 0), bufferPosition)
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
                $closure = function (\\TestNamespace\\Bar\\BarInterface $blub, \\TestNamespace\\Bar\\BarClass $bar2) {
                    // $test2->
                    // $test3->
                    // $bar2->
                    // $blub->
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
            new Range(new Point(9, 4), bufferPosition)
        ]

        expect(parser.getFunctionScopeListAt(editor, bufferPosition)).toEqual(expectedResult)

    it "correctly returns nothing useful when in a function signature.", ->
        source =
            """
            <?php

            function test($param1, $param2)
            {

            }
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 4
            column: 20

        expectedResult = [

        ]

        expect(parser.getFunctionScopeListAt(editor, bufferPosition)).toEqual(expectedResult)
