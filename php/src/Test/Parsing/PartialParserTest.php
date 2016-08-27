<?php

namespace PhpIntegrator\Test\Parsing;

use ReflectionClass;

use PhpIntegrator\Parsing\PartialParser;

class PartialParserTest extends \PHPUnit_Framework_TestCase
{
    public function testStripPairContentCorrectlyStripsParantheses()
    {
        $partialParser = new PartialParser();

        $reflectionClass = new ReflectionClass(get_class($partialParser));
        $reflectionMethod = $reflectionClass->getMethod('stripPairContent');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'
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
SOURCE;

        $expectedResult = <<<'SOURCE'
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
SOURCE;

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($partialParser, $source, '(', ')')
        );
    }

    public function testRetrieveSanitizedCallStackCorrectlyStopsWithNoText()
    {
        $partialParser = new PartialParser();

        $reflectionClass = new ReflectionClass(get_class($partialParser));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'

SOURCE;

        $expectedResult = [];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($partialParser, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesCommentsAtTheStartOfTheCallStack()
    {
        $partialParser = new PartialParser();

        $reflectionClass = new ReflectionClass(get_class($partialParser));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'
            /*test
            test
            test*/

            Foo::myFunc
SOURCE;

        $expectedResult = ['Foo', 'myFunc'];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($partialParser, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesCallStacksThatStartWithANewInstance()
    {
        $partialParser = new PartialParser();

        $reflectionClass = new ReflectionClass(get_class($partialParser));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'
            (new Foo())->myFunc
SOURCE;

        $expectedResult = ['new Foo()', 'myFunc'];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($partialParser, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesCallStacksThatStartWithANewInstanceSpreadOverSeveralLines()
    {
        $partialParser = new PartialParser();

        $reflectionClass = new ReflectionClass(get_class($partialParser));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'
            (new Foo(

            ))->myFunc
SOURCE;

        $expectedResult = ['new Foo()', 'myFunc'];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($partialParser, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesClosures()
    {
        $partialParser = new PartialParser();

        $reflectionClass = new ReflectionClass(get_class($partialParser));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'
            function () {
                $value['a'] = $this->method($value['a']);
            }
SOURCE;

        $expectedResult = ['function () {}'];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($partialParser, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesComplexCallStacks()
    {
        $partialParser = new PartialParser();

        $reflectionClass = new ReflectionClass(get_class($partialParser));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'
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
SOURCE;

        $expectedResult = [
            '$this',
            'testChaining()',
            'testChaining()',
            'testChaining()',
            'testChaining()',
            'testChai'
        ];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($partialParser, $source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBasicFunctionCalls()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            array_walk
SOURCE;

        $expectedResult = ['array_walk'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtStaticClassNames()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            if (true) {
                // More code here.
            }

            Bar::testProperty
SOURCE;

        $expectedResult = ['Bar', 'testProperty'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtStaticClassNamesContainingANamespace()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            if (true) {
                // More code here.
            }

            NamespaceTest\Bar::staticmethod()
SOURCE;

        $expectedResult = ['NamespaceTest\Bar', 'staticmethod()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtControlKeywords()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            if (true) {
                // More code here.
            }

            return $this->someProperty
SOURCE;

        $expectedResult = ['$this', 'someProperty'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBuiltinConstructs()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            if (true) {
                // More code here.
            }

            echo $this->someProperty
SOURCE;

        $expectedResult = ['$this', 'someProperty'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtKeywordsSuchAsSelfAndParent()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            if(true) {

            }

            self::$someProperty->test
SOURCE;

        $expectedResult = ['self', '$someProperty', 'test'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtTernaryOperatorsFirstOperand()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $a = $b ? $c->foo()
SOURCE;

        $expectedResult = ['$c', 'foo()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtTernaryOperatorsLastOperand()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $a = $b ? $c->foo() : $d->bar()
SOURCE;

        $expectedResult = ['$d', 'bar()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtConcatenationOperators()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $a = $b . $c->bar()
SOURCE;

        $expectedResult = ['$c', 'bar()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheBracketSyntaxIsUsedForDynamicAccessToMembers()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            if (true) {
                // More code here.
            }

            $this->{$foo}()->test()
SOURCE;

        $expectedResult = ['$this', '{$foo}()', 'test()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtCasts()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $test = (int) $this->test
SOURCE;

        $expectedResult = ['$this', 'test'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheBracketSyntaxIsUsedForVariablesInsideStrings()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $test = "
                SELECT *

                FROM {$this->
SOURCE;

        $expectedResult = ['$this', ''];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtTheNewKeyword()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $test = new $this->
SOURCE;

        $expectedResult = ['$this', ''];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheFirstElementIsAnInstantiationWrappedInParantheses()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            if (true) {
                // More code here.
            }

            (new Foo\Bar())->doFoo()
SOURCE;

        $expectedResult = ['new Foo\Bar()', 'doFoo()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheFirstElementIsAnInstantiationAsArrayValueInAKeyValuePair()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $test = [
                'test' => (new Foo\Bar())->doFoo()
SOURCE;

        $expectedResult = ['new Foo\Bar()', 'doFoo()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheFirstElementIsAnInstantiationWrappedInParaenthesesAndItIsInsideAnArray()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $array = [
                (new Foo\Bar())->doFoo()
SOURCE;

        $expectedResult = ['new Foo\Bar()', 'doFoo()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheFirstElementInAnInstantiationWrappedInParanthesesAndItIsInsideAFunctionCall()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            foo(firstArg($test), (new Foo\Bar())->doFoo()
SOURCE;

        $expectedResult = ['new Foo\Bar()', 'doFoo()'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtSanitizesComplexCallStack()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
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
SOURCE;

        $expectedResult = ['$this', 'testChaining()', 'testChaining()', 'testChaining()', 'testChaining()', 'testChai'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtSanitizesStaticCallWithStaticKeyword()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            static::doSome
SOURCE;

        $expectedResult = ['static', 'doSome'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtAssignmentSymbol()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $test = $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtMultiplicationOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 * $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtDivisionOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 / $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtPlusOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 + $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtModulusOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 % $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtMinusOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 - $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBitwisoOrOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 | $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBitwiseAndOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 & $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBitwiseXorOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 ^ $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBitwiseNotOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 ~ $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBooleanLessOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 < $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBooleanGreaterOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            5 < $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBooleanNotOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            !$this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtSilencingOperator()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            @$this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $partialParser->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testGetInvocationInfoAtWithSingleLineInvocation()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
            <?php

            $this->test(1, 2, 3
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(42, $result['offset']);
        $this->assertEquals(['$this', 'test'], $result['callStack']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithMultiLineInvocation()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        $this->test(
            1,
            2,
            3
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(34, $result['offset']);
        $this->assertEquals(['$this', 'test'], $result['callStack']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithMoreComplexNestedArguments1()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        builtin_func(
            ['test', $this->foo()],
            function ($a) {
                // Something here.
                $this->something();
            },
            3
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['builtin_func'], $result['callStack']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithMoreComplexNestedArguments2()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        builtin_func(/* test */
            "]",// a comment
            "}",/*}*/
            ['test'
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['builtin_func'], $result['callStack']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithMoreComplexNestedArguments3()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        builtin_func(
            $this->foo(),
            $array['key'],
            $array['ke
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['builtin_func'], $result['callStack']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithTrailingCommas()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        builtin_func(
            foo(),
            [
                'Trailing comma',
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(1, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithNestedParantheses()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        builtin_func(
            foo(),
            ($a + $b
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['builtin_func'], $result['callStack']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(1, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithSqlStringArguments()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        foo("SELECT a.one, a.two, a.three FROM test", second
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(1, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCallsWithNormalClassName()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        new MyObject(
            1,
            2,
            3
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['MyObject'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCallsWithNormalClassNamePrecededByLeadingSlash()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        new \MyObject(
            1,
            2,
            3
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(36, $result['offset']);
        $this->assertEquals(['\MyObject'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCallsWithNormalClassNamePrecededByLeadingSlashAndMultipleParts()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        new \MyNamespace\MyObject(
            1,
            2,
            3
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(48, $result['offset']);
        $this->assertEquals(['\MyNamespace\MyObject'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCalls2()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        new static(
            1,
            2,
            3
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(33, $result['offset']);
        $this->assertEquals(['static'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCalls3()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        new self(
            1,
            2,
            3
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertEquals(31, $result['offset']);
        $this->assertEquals(['self'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtReturnsNullWhenNotInInvocation1()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        if ($this->test() as $test) {
            if (true) {

            }
        }
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertNull($result);
    }

    public function testGetInvocationInfoAtReturnsNullWhenNotInInvocation2()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        $this->test();
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertNull($result);
    }

    public function testGetInvocationInfoAtReturnsNullWhenNotInInvocation3()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        function test($a, $b)
        {

SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertNull($result);
    }

    public function testGetInvocationInfoAtReturnsNullWhenNotInInvocation4()
    {
        $partialParser = new PartialParser();

        $source = <<<'SOURCE'
        <?php

        if (preg_match('/^array\s*\(/', $firstElement) === 1) {
            $className = 'array';
        } elseif (
SOURCE;

        $result = $partialParser->getInvocationInfoAt($source);

        $this->assertNull($result);
    }
}
