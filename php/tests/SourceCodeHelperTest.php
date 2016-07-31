<?php

namespace PhpIntegrator;

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
}
