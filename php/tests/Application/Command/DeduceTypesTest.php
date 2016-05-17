<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class DeduceTypesTest extends IndexedTest
{
    protected function deduceTypes($file, array $expressionParts)
    {
        $path = __DIR__ . '/DeduceTypesTest/' . $file;

        $markerOffset = $this->getMarkerOffset($path, '<MARKER>');

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new DeduceTypes();
        $command->setIndexDatabase($indexDatabase);

        return $command->deduceTypes($path, file_get_contents($path), $expressionParts, $markerOffset);
    }

    protected function getMarkerOffset($path, $marker)
    {
        $testFileContents = @file_get_contents($path);

        $markerOffset = mb_strpos($testFileContents, $marker);

        return $markerOffset;
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    // public function testThrowsExceptionOnUnknownFile()
    // {
    //     $command = new DeduceTypes();
    //     $command->setIndexDatabase(new IndexDatabase(':memory:', 1));
    //
    //     $output = $this->deduceTypes('MissingFile.php', '$test');
    // }

    /**
     * @return Parser
     */
    // protected function getParser()
    // {
    //     $lexer = new Lexer([
    //         'usedAttributes' => [
    //             'comments', 'startLine', 'startFilePos', 'endFilePos'
    //         ]
    //     ]);
    //
    //     return (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
    // }
    //
    // protected function getExpressionNodeFor($code)
    // {
    //     $nodes = $this->getParser()->parse($code);
    //
    //     assert($nodes);
    //
    //     return $nodes;
    // }

    public function testCorrectlyAnalyzesStaticPropertyAccess()
    {
        $result = $this->deduceTypes(
            'StaticPropertyAccess.php',
            ['Bar', '$testProperty']
        );

        $this->assertEquals(['\DateTime'], $result);
    }

    public function testCorrectlyAnalyzesSelf()
    {
        $result = $this->deduceTypes(
            'Self.php',
            ['self', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesStatic()
    {
        $result = $this->deduceTypes(
            'Static.php',
            ['static', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesParent()
    {
        $result = $this->deduceTypes(
            'Parent.php',
            ['parent', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesThis()
    {
        $result = $this->deduceTypes(
            'This.php',
            ['$this', 'testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesVariables()
    {
        $result = $this->deduceTypes(
            'Variable.php',
            ['$var', 'testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesGlobalFunctions()
    {
        $result = $this->deduceTypes(
            'GlobalFunction.php',
            ['global_function()']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesClosures()
    {
        $result = $this->deduceTypes(
            'Closure.php',
            ['$var']
        );

        $this->assertEquals(['\Closure'], $result);
    }

    public function testCorrectlyAnalyzesNewWithStatic()
    {
        $result = $this->deduceTypes(
            'NewWithStatic.php',
            ['new static']
        );

        $this->assertEquals(['\Bar'], $result);
    }

    public function testCorrectlyAnalyzesClone()
    {
        $result = $this->deduceTypes(
            'Clone.php',
            ['clone $var']
        );

        $this->assertEquals(['\Bar'], $result);
    }

    public function testCorrectlyAnalyzesLongerChains()
    {
        $result = $this->deduceTypes(
            'LongerChain.php',
            ['$this', 'testProperty', 'aMethod()', 'anotherProperty']
        );

        $this->assertEquals(['\DateTime'], $result);
    }

    public function testCorrectlyAnalyzesScalarTypes()
    {
        $file = 'ScalarType.php';

        $this->assertEquals(['int'], $this->deduceTypes($file, ['5']));
        $this->assertEquals(['int'], $this->deduceTypes($file, ['05']));
        $this->assertEquals(['int'], $this->deduceTypes($file, ['0x5']));
        $this->assertEquals(['float'], $this->deduceTypes($file, ['5.5']));
        $this->assertEquals(['bool'], $this->deduceTypes($file, ['true']));
        $this->assertEquals(['bool'], $this->deduceTypes($file, ['false']));
        $this->assertEquals(['string'], $this->deduceTypes($file, ['"test"']));
        $this->assertEquals(['string'], $this->deduceTypes($file, ['\'test\'']));
        $this->assertEquals(['array'], $this->deduceTypes($file, ['[$test1, function() {}]']));
        $this->assertEquals(['array'], $this->deduceTypes($file, ['array($test1, function())']));

        $this->assertEquals(['string'], $this->deduceTypes($file, ['"
            test
        "']));

        $this->assertEquals(['string'], $this->deduceTypes($file, ['\'
            test
        \'']));
    }

    public function testCorrectlyProcessesSelfAssign()
    {
        $result = $this->deduceTypes(
            'SelfAssign.php',
            ['$data', 'getData()']
        );

        $this->assertEquals([], $result);
    }

    public function testCorrectlyReturnsMultipleTypes()
    {
        $result = $this->deduceTypes(
            'MultipleTypes.php',
            ['$this', 'testProperty']
        );

        $this->assertEquals([
            'string',
            'int',
            '\Foo',
            '\Bar'
        ], $result);
    }
}
