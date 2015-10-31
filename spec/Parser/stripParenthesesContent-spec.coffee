Parser = require '../../lib/Parser'

describe "stripParenthesesContent", ->
    parser = new Parser(null)

    it "correctly strips parentheses.", ->
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

        expectedResult =
            """
            $this
                ->testChaining()
                //------------
                /*
                    another comment$this;[]{}**** /*
                */
                ->testChaining()

                ->testChaining()

                ->testChaining()

                ->testChai
            """

        expect(parser.stripParenthesesContent(source)).toEqual(expectedResult)
