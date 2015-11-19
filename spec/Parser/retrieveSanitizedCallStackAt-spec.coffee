Parser = require '../../lib/Parser'

describe "retrieveSanitizedCallStackAt", ->
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

    it "correctly stops at keywords such as parent and self.", ->
        source = "self::foo"
        editor.setText(source)
        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 0, column: source.length})).toEqual(['self', 'foo'])

        source = "parent::foo->test"
        editor.setText(source)
        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 0, column: source.length})).toEqual(['parent', 'foo', 'test'])

    it "correctly stops at static class names.", ->
        source =
            """
            <?php

            if (true) {
                // More code here.
            }

            Bar::testProperty
            """

        editor.setText(source)

        expectedResult = [
            'Bar',
            'testProperty'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 17

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 0

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly stops at static class names containing a namespace.", ->
        source =
            """
            <?php

            if (true) {
                // More code here.
            }

            Namespace\\Bar::staticmethod()
            """

        editor.setText(source)

        expectedResult = [
            'Namespace\\Bar',
            'staticmethod()'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 29

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 0

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly stops at control keywords.", ->
        source =
            """
            <?php

            if (true) {
                // More code here.
            }

            return Foo::someProperty
            """


        editor.setText(source)

        expectedResult = [
            'Foo',
            'someProperty'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 24

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 7

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)


    it "correctly stops at built-in constructs.", ->
        source =
            """
            <?php

            if (true) {
                // More code here.
            }

            echo Foo::someProperty
            """


        editor.setText(source)

        expectedResult = [
            'Foo',
            'someProperty'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 22

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 5

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly stops at keywords such as self and parent.", ->
        source =
            """
            <?php

            if(true) {

            }

            self::someProperty
            """


        editor.setText(source)

        expectedResult = [
            'self',
            'someProperty'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 19

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 0

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly stops when when the bracket syntax is used for dynamic access to members.", ->
        source =
            """
            <?php

            if (true) {
                // More code here.
            }

            $this->{$foo}()->test()
            """

        editor.setText(source)

        expectedResult = [
            '$this',
            '{$foo}()',
            'test()'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 23

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 0

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly stops when the first element is an instantiation wrapped in parentheses.", ->
        source =
            """
            <?php

            if (true) {
                // More code here.
            }

            (new Foo\\Bar())->doFoo()
            """

        editor.setText(source)

        expectedResult = [
            'new Foo\\Bar()',
            'doFoo()'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 26

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 0

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly stops when the first element is an instantiation as array value in a key-value pair.", ->
        source =
            """
            <?php

            $test = [
                'test' => (new Foo\\Bar())->doFoo()
            ];
            """

        editor.setText(source)

        expectedResult = [
            'new Foo\\Bar()',
            'doFoo()'
        ]

        row = editor.getLineCount() - 2
        column = editor.getBuffer().lineLengthForRow(row)

        bufferPosition =
            row: row
            column: column

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 14

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly stops when the first element is an instantiation wrapped in parentheses and it is inside an array.", ->
        source =
            """
            <?php

            $array = [
                (new Foo\\Bar())->doFoo()
            ];
            """

        editor.setText(source)

        expectedResult = [
            'new Foo\\Bar()',
            'doFoo()'
        ]

        bufferPosition =
            row: editor.getLineCount() - 2
            column: 29

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 0

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly stops when the first element is an instantiation wrapped in parentheses and it is inside a function call.", ->
        source =
            """
            <?php

            foo(firstArg($test), (new Foo\\Bar())->doFoo(), 'test');
            """

        editor.setText(source)

        expectedResult = [
            'new Foo\\Bar()',
            'doFoo()'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 45

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition.column = 21

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)

    it "correctly sanitizes complex call stacks, interleaved with things such as comments, closures and chaining.", ->
        source =
            """
            <?php

            $this
                ->testChaining(5, ['Somewhat more complex parameters', /* inline comment */ null])
                //------------
                /*
                    another comment$this;[]{}**** /*int echo return
                */
                ->testChaining(2, [
                //------------
                    'value1',
                    'value2'
                ])

                ->testChaining(
                //------------
                    3,
                    [],
                    function (FooClass $foo) {
                        echo 'test';
                        //    --------
                        return $foo;
                    }
                )

                ->testChaining(
                //------------
                    nestedCall() - (2 * 5),
                    nestedCall() - 3
                )

                ->testChai
            """

        editor.setText(source)

        expectedResult = [
            '$this',
            'testChaining()',
            'testChaining()',
            'testChaining()',
            'testChaining()',
            'testChai'
        ]

        bufferPosition =
            row: editor.getLineCount() - 1
            column: 14

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition)).toEqual(expectedResult)

        bufferPosition =
            row: 2
            column: 0

        expect(parser.retrieveSanitizedCallStackAt(editor, bufferPosition, false)).toEqual(expectedResult)
