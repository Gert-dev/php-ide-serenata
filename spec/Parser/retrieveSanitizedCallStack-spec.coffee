Parser = require '../../lib/Parser'

describe "retrieveSanitizedCallStack", ->
    parser = new Parser(null)

    it "correctly sanitizes comments at the start of the call stack.", ->
        source =
            """
            /*test
            test
            test*/

            Foo::myFunc
            """

        expectedResult = [
            'Foo',
            'myFunc'
        ]

        expect(parser.retrieveSanitizedCallStack(source)).toEqual(expectedResult)

    it "correctly sanitizes complex call stacks, interleaved with things such as comments, closures and chaining.", ->
        source =
            """
            $this
                ->testChaining(5, ['Somewhat more complex parameters', /* inline comment */ null])
                //------------
                /*
                    another comment$this;[]{}**** /*
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

        expectedResult = [
            '$this',
            'testChaining()',
            'testChaining()',
            'testChaining()',
            'testChaining()',
            'testChai'
        ]

        expect(parser.retrieveSanitizedCallStack(source)).toEqual(expectedResult)
