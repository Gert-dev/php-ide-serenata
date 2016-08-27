<?php

namespace PhpIntegrator\Test;

use ReflectionClass;

use PhpIntegrator\SourceCodeHelper;

class SourceCodeHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testStripPairContentCorrectlyStripsParantheses()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $reflectionClass = new ReflectionClass(get_class($sourceCodeHelper));
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
            $reflectionMethod->invoke($sourceCodeHelper, $source, '(', ')')
        );
    }

    public function testRetrieveSanitizedCallStackCorrectlyStopsWithNoText()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $reflectionClass = new ReflectionClass(get_class($sourceCodeHelper));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'

SOURCE;

        $expectedResult = [];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($sourceCodeHelper, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesCommentsAtTheStartOfTheCallStack()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $reflectionClass = new ReflectionClass(get_class($sourceCodeHelper));
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
            $reflectionMethod->invoke($sourceCodeHelper, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesCallStacksThatStartWithANewInstance()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $reflectionClass = new ReflectionClass(get_class($sourceCodeHelper));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'
            (new Foo())->myFunc
SOURCE;

        $expectedResult = ['new Foo()', 'myFunc'];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($sourceCodeHelper, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesCallStacksThatStartWithANewInstanceSpreadOverSeveralLines()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $reflectionClass = new ReflectionClass(get_class($sourceCodeHelper));
        $reflectionMethod = $reflectionClass->getMethod('retrieveSanitizedCallStack');
        $reflectionMethod->setAccessible(true);

        $source = <<<'SOURCE'
            (new Foo(

            ))->myFunc
SOURCE;

        $expectedResult = ['new Foo()', 'myFunc'];

        $this->assertEquals(
            $expectedResult,
            $reflectionMethod->invoke($sourceCodeHelper, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesClosures()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $reflectionClass = new ReflectionClass(get_class($sourceCodeHelper));
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
            $reflectionMethod->invoke($sourceCodeHelper, $source)
        );
    }

    public function testRetrieveSanitizedCallStackSanitizesComplexCallStacks()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $reflectionClass = new ReflectionClass(get_class($sourceCodeHelper));
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
            $reflectionMethod->invoke($sourceCodeHelper, $source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBasicFunctionCalls()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            array_walk
SOURCE;

        $expectedResult = ['array_walk'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtStaticClassNames()
    {
        $sourceCodeHelper = new SourceCodeHelper();

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
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtStaticClassNamesContainingANamespace()
    {
        $sourceCodeHelper = new SourceCodeHelper();

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
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtControlKeywords()
    {
        $sourceCodeHelper = new SourceCodeHelper();

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
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBuiltinConstructs()
    {
        $sourceCodeHelper = new SourceCodeHelper();

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
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtKeywordsSuchAsSelfAndParent()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            if(true) {

            }

            self::$someProperty->test
SOURCE;

        $expectedResult = ['self', '$someProperty', 'test'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtTernaryOperatorsFirstOperand()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $a = $b ? $c->foo()
SOURCE;

        $expectedResult = ['$c', 'foo()'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtTernaryOperatorsLastOperand()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $a = $b ? $c->foo() : $d->bar()
SOURCE;

        $expectedResult = ['$d', 'bar()'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtConcatenationOperators()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $a = $b . $c->bar()
SOURCE;

        $expectedResult = ['$c', 'bar()'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheBracketSyntaxIsUsedForDynamicAccessToMembers()
    {
        $sourceCodeHelper = new SourceCodeHelper();

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
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtCasts()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $test = (int) $this->test
SOURCE;

        $expectedResult = ['$this', 'test'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheBracketSyntaxIsUsedForVariablesInsideStrings()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $test = "
                SELECT *

                FROM {$this->
SOURCE;

        $expectedResult = ['$this', ''];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtTheNewKeyword()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $test = new $this->
SOURCE;

        $expectedResult = ['$this', ''];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheFirstElementIsAnInstantiationWrappedInParantheses()
    {
        $sourceCodeHelper = new SourceCodeHelper();

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
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheFirstElementIsAnInstantiationAsArrayValueInAKeyValuePair()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $test = [
                'test' => (new Foo\Bar())->doFoo()
SOURCE;

        $expectedResult = ['new Foo\Bar()', 'doFoo()'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheFirstElementIsAnInstantiationWrappedInParaenthesesAndItIsInsideAnArray()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $array = [
                (new Foo\Bar())->doFoo()
SOURCE;

        $expectedResult = ['new Foo\Bar()', 'doFoo()'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsWhenTheFirstElementInAnInstantiationWrappedInParanthesesAndItIsInsideAFunctionCall()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            foo(firstArg($test), (new Foo\Bar())->doFoo()
SOURCE;

        $expectedResult = ['new Foo\Bar()', 'doFoo()'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtSanitizesComplexCallStack()
    {
        $sourceCodeHelper = new SourceCodeHelper();

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
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtSanitizesStaticCallWithStaticKeyword()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            static::doSome
SOURCE;

        $expectedResult = ['static', 'doSome'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtAssignmentSymbol()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $test = $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtMultiplicationOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 * $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtDivisionOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 / $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtPlusOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 + $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtModulusOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 % $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtMinusOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 - $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBitwisoOrOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 | $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBitwiseAndOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 & $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBitwiseXorOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 ^ $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBitwiseNotOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 ~ $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBooleanLessOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 < $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBooleanGreaterOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            5 < $this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtBooleanNotOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            !$this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testRetrieveSanitizedCallStackAtStopsAtSilencingOperator()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            @$this->one
SOURCE;

        $expectedResult = ['$this', 'one'];

        $this->assertEquals(
            $expectedResult,
            $sourceCodeHelper->retrieveSanitizedCallStackAt($source)
        );
    }

    public function testGetInvocationInfoAtWithSingleLineInvocation()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
            <?php

            $this->test(1, 2, 3
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(42, $result['offset']);
        $this->assertEquals(['$this', 'test'], $result['callStack']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithMultiLineInvocation()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        $this->test(
            1,
            2,
            3
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(34, $result['offset']);
        $this->assertEquals(['$this', 'test'], $result['callStack']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithMoreComplexNestedArguments1()
    {
        $sourceCodeHelper = new SourceCodeHelper();

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

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['builtin_func'], $result['callStack']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithMoreComplexNestedArguments2()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        builtin_func(/* test */
            "]",// a comment
            "}",/*}*/
            ['test'
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['builtin_func'], $result['callStack']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithMoreComplexNestedArguments3()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        builtin_func(
            $this->foo(),
            $array['key'],
            $array['ke
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['builtin_func'], $result['callStack']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithTrailingCommas()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        builtin_func(
            foo(),
            [
                'Trailing comma',
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(1, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithNestedParantheses()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        builtin_func(
            foo(),
            ($a + $b
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['builtin_func'], $result['callStack']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(1, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithSqlStringArguments()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        foo("SELECT a.one, a.two, a.three FROM test", second
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(1, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCallsWithNormalClassName()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        new MyObject(
            1,
            2,
            3
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals(['MyObject'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCallsWithNormalClassNamePrecededByLeadingSlash()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        new \MyObject(
            1,
            2,
            3
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(36, $result['offset']);
        $this->assertEquals(['\MyObject'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCallsWithNormalClassNamePrecededByLeadingSlashAndMultipleParts()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        new \MyNamespace\MyObject(
            1,
            2,
            3
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(48, $result['offset']);
        $this->assertEquals(['\MyNamespace\MyObject'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCalls2()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        new static(
            1,
            2,
            3
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(33, $result['offset']);
        $this->assertEquals(['static'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtWithConstructorCalls3()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        new self(
            1,
            2,
            3
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertEquals(31, $result['offset']);
        $this->assertEquals(['self'], $result['callStack']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    public function testGetInvocationInfoAtReturnsNullWhenNotInInvocation1()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        if ($this->test() as $test) {
            if (true) {

            }
        }
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertNull($result);
    }

    public function testGetInvocationInfoAtReturnsNullWhenNotInInvocation2()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        $this->test();
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertNull($result);
    }

    public function testGetInvocationInfoAtReturnsNullWhenNotInInvocation3()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        function test($a, $b)
        {

SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertNull($result);
    }

    public function testGetInvocationInfoAtReturnsNullWhenNotInInvocation4()
    {
        $sourceCodeHelper = new SourceCodeHelper();

        $source = <<<'SOURCE'
        <?php

        if (preg_match('/^array\s*\(/', $firstElement) === 1) {
            $className = 'array';
        } elseif (
SOURCE;

        $result = $sourceCodeHelper->getInvocationInfoAt($source);

        $this->assertNull($result);
    }
}
