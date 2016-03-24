<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class DeduceTypeTest extends IndexedTest
{
    protected function deduceType($file, $name)
    {
        $path = __DIR__ . '/DeduceTypeTest/' . $file;

        $markerOffset = $this->getMarkerOffset($path, '<MARKER>');

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new DeduceType();
        $command->setIndexDatabase($indexDatabase);

        return $command->deduceType($path, $name, $markerOffset, false);
    }

    protected function getMarkerOffset($path, $marker)
    {
        $testFileContents = @file_get_contents($path);

        $markerOffset = mb_strpos($testFileContents, $marker);

        return $markerOffset;
    }

    public function testCorrectlyAnalyzesTypeOverrideAnnotations()
    {
        $output = $this->deduceType('TypeOverrideAnnotations.php', '$a');

        $this->assertEquals([
            'type'         => '\Traversable',
            'resolvedType' => '\Traversable'
        ], $output);

        $output = $this->deduceType('TypeOverrideAnnotations.php', '$b');

        $this->assertEquals([
            'type'         => '\Traversable',
            'resolvedType' => '\Traversable'
        ], $output);

        $output = $this->deduceType('TypeOverrideAnnotations.php', '$c');

        $this->assertEquals([
            'type'         => 'C',
            'resolvedType' => 'A\C'
        ], $output);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new DeduceType();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $output = $this->deduceType('MissingFile.php', '$test');
    }







    /**
     * @return Parser
     */
    protected function getParser()
    {
        $lexer = new Lexer([
            'usedAttributes' => [
                'comments', 'startLine', 'startFilePos', 'endFilePos'
            ]
        ]);

        return (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
    }

    protected function getExpressionNodeFor($code)
    {
        $nodes = $this->getParser()->parse($code);

        assert($nodes);

        return $nodes;
    }

    public function testCorrectlyAnalyzesStaticPropertyAccess()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            Bar::$testProperty;
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesSelf()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            class Bar
            {
                public function __construct()
                {
                    self::$testProperty
                }
            }
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesStatic()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            class Bar
            {
                public function __construct()
                {
                    static::$testProperty
                }
            }
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesParent()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            class Bar
            {
                public function __construct()
                {
                    parent::$testProperty
                }
            }
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesThis()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            class Bar
            {
                public function __construct()
                {
                    $this->testProperty
                }
            }
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesVariables()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            $var = new Bar();
            $var->testProperty
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesGlobalFunctions()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            global_function()->
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesClosures()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            $var = function () {

            };

            $var->bindTo();
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesNewWithStatic()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            class Bar
            {
                public function __construct()
                {
                    $test = new static();
                }
            }
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesClone()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            $var = new \DateTime();

            $test = clone $var;
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesLongerChains()
    {
        $file = __DIR__ . '/SampleCode.php';

        $typeDeducer = new DeduceType();

        $code = '
            <?php

            class Bar
            {
                public function __construct()
                {
                    $this->testProperty->aMethod()->anotherProperty;
                }
            }
        ';

        $this->assertEquals([
            'type'         => 'A',
            'resolvedType' => 'A\B'
        ], $typeDeducer->deduceType($file, $this->getExpressionNodeFor($code)));
    }

    public function testCorrectlyAnalyzesBasicTypes()
    {
        // TODO
    }
}
