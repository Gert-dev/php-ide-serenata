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

        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 6, column: 17})).toEqual(expectedResult)

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

        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 6, column: 29})).toEqual(expectedResult)

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

        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 6, column: 24})).toEqual(expectedResult)


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

        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 6, column: 22})).toEqual(expectedResult)

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

        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 6, column: 19})).toEqual(expectedResult)

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

        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 6, column: 23})).toEqual(expectedResult)

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

        expect(parser.retrieveSanitizedCallStackAt(editor, {row: 6, column: 26})).toEqual(expectedResult)

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

        expect(parser.retrieveSanitizedCallStackAt(editor, {row: editor.getLineCount() - 1, column: 14})).toEqual(expectedResult)
