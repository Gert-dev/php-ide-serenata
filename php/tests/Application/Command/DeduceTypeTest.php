<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class DeduceTypeTest extends IndexedTest
{
    protected function deduceType($file, array $expressionParts)
    {
        $path = __DIR__ . '/DeduceTypeTest/' . $file;

        $markerOffset = $this->getMarkerOffset($path, '<MARKER>');

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new DeduceType();
        $command->setIndexDatabase($indexDatabase);

        return $command->deduceType($path, file_get_contents($path), $expressionParts, $markerOffset);
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
    //     $command = new DeduceType();
    //     $command->setIndexDatabase(new IndexDatabase(':memory:', 1));
    //
    //     $output = $this->deduceType('MissingFile.php', '$test');
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
        $result = $this->deduceType(
            'StaticPropertyAccess.php',
            ['Bar', '$testProperty']
        );

        $this->assertEquals('\DateTime', $result);
    }

    public function testCorrectlyAnalyzesSelf()
    {
        $result = $this->deduceType(
            'Self.php',
            ['self', '$testProperty']
        );

        $this->assertEquals('B', $result);
    }

    public function testCorrectlyAnalyzesStatic()
    {
        $result = $this->deduceType(
            'Static.php',
            ['static', '$testProperty']
        );

        $this->assertEquals('B', $result);
    }

    public function testCorrectlyAnalyzesParent()
    {
        $result = $this->deduceType(
            'Parent.php',
            ['parent', '$testProperty']
        );

        $this->assertEquals('B', $result);
    }

    public function testCorrectlyAnalyzesThis()
    {
        $result = $this->deduceType(
            'This.php',
            ['$this', 'testProperty']
        );

        $this->assertEquals('B', $result);
    }

    public function testCorrectlyAnalyzesVariables()
    {
        $result = $this->deduceType(
            'Variable.php',
            ['$var', 'testProperty']
        );

        $this->assertEquals('B', $result);
    }

    public function testCorrectlyAnalyzesGlobalFunctions()
    {
        $result = $this->deduceType(
            'GlobalFunction.php',
            ['global_function()']
        );

        $this->assertEquals('B', $result);
    }

    public function testCorrectlyAnalyzesClosures()
    {
        $result = $this->deduceType(
            'Closure.php',
            ['$var']
        );

        $this->assertEquals('\Closure', $result);
    }

    public function testCorrectlyAnalyzesNewWithStatic()
    {
        $result = $this->deduceType(
            'NewWithStatic.php',
            ['new static']
        );

        $this->assertEquals('Bar', $result);
    }

    public function testCorrectlyAnalyzesClone()
    {
        $result = $this->deduceType(
            'Clone.php',
            ['clone $var']
        );

        $this->assertEquals('Bar', $result);
    }

    public function testCorrectlyAnalyzesLongerChains()
    {
        $result = $this->deduceType(
            'LongerChain.php',
            ['$this', 'testProperty', 'aMethod()', 'anotherProperty']
        );

        $this->assertEquals('\DateTime', $result);
    }

    public function testCorrectlyAnalyzesScalarTypes()
    {
        $file = 'ScalarType.php';

        $this->assertEquals('int', $this->deduceType($file, ['5']));
        $this->assertEquals('int', $this->deduceType($file, ['05']));
        $this->assertEquals('int', $this->deduceType($file, ['0x5']));
        $this->assertEquals('float', $this->deduceType($file, ['5.5']));
        $this->assertEquals('bool', $this->deduceType($file, ['true']));
        $this->assertEquals('bool', $this->deduceType($file, ['false']));
        $this->assertEquals('string', $this->deduceType($file, ['"test"']));
        $this->assertEquals('string', $this->deduceType($file, ['\'test\'']));
        $this->assertEquals('array', $this->deduceType($file, ['[$test1, function() {}]']));
        $this->assertEquals('array', $this->deduceType($file, ['array($test1, function())']));

        $this->assertEquals('string', $this->deduceType($file, ['"
            test
        "']));

        $this->assertEquals('string', $this->deduceType($file, ['\'
            test
        \'']));
    }
}
