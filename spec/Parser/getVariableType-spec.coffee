Parser = require '../../lib/Parser'

describe "getVariableType", ->
    editor = null
    grammar = null

    proxyMock = {
        getClassListForFile: (file) ->
            return {
                'EXPECTED\\TYPE_1' : @getClassInfo('EXPECTED\\TYPE_1')
            }

        getGlobalFunctions: () ->
            return {
                foo:
                    parameters: [
                        {
                            name: 'test1',
                            fullType: 'EXPECTED\\TYPE_1'
                        },
                        {
                            name: 'test2',
                            fullType: 'EXPECTED\\TYPE_2'
                        }
                    ]
            }

        getClassInfo: (className, element) ->
            if className == 'EXPECTED\\TYPE_1'
                return {
                    name: 'EXPECTED\\TYPE_1'
                    startLine: 0
                    endLine: 9999
                    methods:
                        foo:
                            parameters: [
                                {
                                    name: 'test1',
                                    type: 'TYPE_1'
                                    fullType: 'EXPECTED\\TYPE_1'
                                },
                                {
                                    name: 'test2',
                                    type: 'TYPE_2'
                                    fullType: 'EXPECTED\\TYPE_2'
                                }
                            ]

                        bar:
                            return:
                                resolvedType: 'EXPECTED\\TYPE_1'
                }
    }

    parser = new Parser(proxyMock)

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

    it "returns null for undefined variables.", ->
        source =
            """
            <?php
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual(null)

    it "correctly skips lines with comments.", ->
        source =
            """
            <?php

            $test = new EXPECTED\\TYPE_1();
            // $test = new EXPECTED\\TYPE_2();
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of $this.", ->
        source =
            """
            <?php

            namespace EXPECTED;

            class TYPE_1
            {
                public function __construct()
                {
                    //
                }
            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$this')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of a variable through a call stack.", ->
        source =
            """
            <?php

            $test = EXPECTED\\TYPE_1::bar();
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of a variable through a multi-line call stack.", ->
        source =
            """
            <?php

            $test = EXPECTED\\TYPE_1
                ::bar();
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of a variable through an instantiation.", ->
        source =
            """
            <?php

            $test = new EXPECTED\\TYPE_1();
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of a variable through a catch block type hint.", ->
        source =
            """
            <?php

            try {

            } catch (EXPECTED\\TYPE_1 $test) {
                //
            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 2
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of an iterator variable in a foreach that's part of a collection of items.", ->
        source =
            """
            <?php

            /** @var EXPECTED\\TYPE_1[] $list */
            foreach ($list as $test) {

            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 2
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of an iterator variable in a foreach (with key) that's part of a collection of items.", ->
        source =
            """
            <?php

            /** @var EXPECTED\\TYPE_1[] $list */
            foreach ($list as $index => $test) {

            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 2
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the last type of a variable that's checked with instanceof in an if statement.", ->
        source =
            """
            <?php

            if ($test instanceof EXPECTED\\TYPE_1) {

            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 2
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the last type of a variable that's checked with instanceof in an if statement when it's not a class name.", ->
        source =
            """
            <?php

            namespace EXPECTED;

            class TYPE_1
            {
                public function foo()
                {
                    if ($test instanceof self) {
                        //
                    }
                }
            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 4
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of a variable through function parameter type hints.", ->
        source =
            """
            <?php

            function foo(EXPECTED\\TYPE_1 $test1 = null, EXPECTED\\TYPE_2 ...$test2)
            {
                //
            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 2
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test1')).toEqual('EXPECTED\\TYPE_1')
        expect(parser.getVariableType(editor, bufferPosition, '$test2')).toEqual('EXPECTED\\TYPE_2')

    it "correctly returns the type of a variable through the function's docblock.", ->
        source =
            """
            <?php

            function foo($test1, $test2)
            {
                //
            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 2
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test1')).toEqual('EXPECTED\\TYPE_1')
        expect(parser.getVariableType(editor, bufferPosition, '$test2')).toEqual('EXPECTED\\TYPE_2')

    it "correctly returns the type of a variable through the method's docblock.", ->
        # NOTE: The docblock data is returned by the proxy and does not need to be explicitly present here.
        source =
            """
            <?php

            namespace EXPECTED;

            class TYPE_1
            {
                function foo($test1, $test2)
                {
                    //
                }
            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test1')).toEqual('EXPECTED\\TYPE_1')
        expect(parser.getVariableType(editor, bufferPosition, '$test2')).toEqual('EXPECTED\\TYPE_2')

    it "correctly returns the type of a variable through a PHPStorm-style type annotation.", ->
        source =
            """
            <?php

            /** @var EXPECTED\\TYPE_1 $test1 A description. */
            /** @var EXPECTED\\TYPE_1 $test2 */

            some_code_here();
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test1')).toEqual('EXPECTED\\TYPE_1')
        expect(parser.getVariableType(editor, bufferPosition, '$test2')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of a variable through a reverse type annotation.", ->
        source =
            """
            <?php

            /** @var $test1 EXPECTED\\TYPE_1 A description. */
            /** @var $test2 EXPECTED\\TYPE_1 */

            some_code_here();
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test1')).toEqual('EXPECTED\\TYPE_1')
        expect(parser.getVariableType(editor, bufferPosition, '$test2')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the last type of a variable who's type changes over time.", ->
        source =
            """
            <?php

            $test = new \\EXPECTED_TYPE\\WRONG_TYPE();

            try {

            } catch (EXPECTED\\TYPE_1 $test) {

            }
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of a variable who's assigned to a member from itself and doesn't get stuck in an infinite loop.", ->
        source =
            """
            <?php

            $test = new EXPECTED\\TYPE_1();
            $test = $test->bar();

            //
            """

        editor.setText(source)

        row = editor.getLineCount() - 1
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')

    it "correctly returns the type of a partial expression and doesn't go further than the current position.", ->
        source =
            """
            <?php

            $test = new EXPECTED\\TYPE_1();
            $test = $test->

            someOtherExpressionThatShouldNotBeUsed();
            """

        editor.setText(source)

        row = editor.getLineCount() - 3
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row    : row
            column : column

        expect(parser.getVariableType(editor, bufferPosition, '$test')).toEqual('EXPECTED\\TYPE_1')
