Parser = require '../../lib/Parser'

describe "getAvailableVariables", ->
    editor = null
    grammar = null

    proxyStub =
        getDocParams: () ->
            return {params: null}

    parser = new Parser(proxyStub)

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

    it "correctly returns a list of variables that apply to the current scope when outside a function.", ->
        source =
            """
            <?php

            $var1 = 5;

            function foo($param)
            {
                $nestedVar = null;
            }

            $var2 = 3;

            function test($param1, $param2)
            {
                $closure = function ($closureParam) use ($something) {
                    $test = 3;
                };
            }

            $var3 = 3809;

            //
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 2
            column: 0

        expectedResult = [
            '$var1'
            '$var2'
            '$var3'
        ]

        variables = parser.getAvailableVariables(editor, bufferPosition)

        flatList = (variable for variable,info of variables)

        expect(flatList).toEqual(expectedResult)

    it "correctly returns a list of variables that apply to the current scope when inside a function.", ->
        source =
            """
            <?php

            $var1 = 5;

            function foo($param)
            {
                $nestedVar = null;
            }

            $var2 = 3;

            function test($param1, $param2)
            {
                $closure = function ($closureParam) use ($something) {
                    $test = 3;
                };

                //
            }
            """

        editor.setText(source)

        bufferPosition =
            row: editor.getLineCount() - 2
            column: 0

        expectedResult = [
            '$closure'
            '$param2'
            '$param1'
            '$this'
        ]

        variables = parser.getAvailableVariables(editor, bufferPosition)

        flatList = (variable for variable,info of variables)

        expect(flatList).toEqual(expectedResult)
