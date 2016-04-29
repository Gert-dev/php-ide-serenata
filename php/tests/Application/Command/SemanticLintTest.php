<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

class SemanticLintTest extends IndexedTest
{
    protected function lintFile($file, $indexingMayFail = false)
    {
        $path = __DIR__ . '/SemanticLintTest/' . $file;

        $indexDatabase = $this->getDatabaseForTestFile($path, $indexingMayFail);

        $command = new SemanticLint();
        $command->setIndexDatabase($indexDatabase);

        return $command->semanticLint($path, file_get_contents($path));
    }

    public function testCorrectlyIdentifiesSyntaxErrors()
    {
        $output = $this->lintFile('SyntaxError.php', true);

        $this->assertEquals(1, count($output['errors']['syntaxErrors']));
    }

    public function testReportsUnknownClassesWithNoNamespace()
    {
        $output = $this->lintFile('UnknownClassesNoNamespace.php');

        $this->assertEquals([
            [
                'name'      => 'A\B',
                'namespace' => null,
                'start'     => 16,
                'end'       => 19
            ]
        ], $output['errors']['unknownClasses']);
    }

    public function testReportsUnknownClassesWithSingleNamespace()
    {
        $output = $this->lintFile('UnknownClassesSingleNamespace.php');

        $this->assertEquals([
            [
                'name'      => 'DateTime',
                'namespace' => 'A',
                'start'     => 64,
                'end'       => 72
            ],
            [
                'name'      => 'DateTimeZone',
                'namespace' => 'A',
                'start'     => 85,
                'end'       => 97
            ]
        ], $output['errors']['unknownClasses']);
    }

    public function testReportsUnknownClassesWithMultipleNamespaces()
    {
        $output = $this->lintFile('UnknownClassesMultipleNamespaces.php');

        $this->assertEquals([
            [
                'name'      => 'DateTime',
                'namespace' => 'A',
                'start'     => 56,
                'end'       => 64
            ],

            [
                'name'      => 'SplFileInfo',
                'namespace' => 'B',
                'start'     => 117,
                'end'       => 128
            ]
        ], $output['errors']['unknownClasses']);
    }

    public function testReportsUnknownClassesInDocBlocks()
    {
        $output = $this->lintFile('UnknownClassesDocblock.php');

        $this->assertEquals([
            [
                'name'      => 'A\B',
                'namespace' => 'A',
                'start'     => 36,
                'end'       => 56
            ],

            [
                'name'      => 'A\C',
                'namespace' => 'A',
                'start'     => 36,
                'end'       => 56
            ]
        ], $output['errors']['unknownClasses']);
    }

    public function testReportsUnusedUseStatementsWithSingleNamespace()
    {
        $output = $this->lintFile('UnusedUseStatementsSingleNamespace.php');

        $this->assertEquals([
            [
                'name'  => 'Traversable',
                'alias' => 'Traversable',
                'start' => 39,
                'end'   => 50
            ]
        ], $output['warnings']['unusedUseStatements']);
    }

    public function testReportsUnusedUseStatementsWithMultipleNamespaces()
    {
        $output = $this->lintFile('UnusedUseStatementsMultipleNamespaces.php');

        $this->assertEquals([
            [
                'name'  => 'SplFileInfo',
                'alias' => 'SplFileInfo',
                'start' => 47,
                'end'   => 58
            ],

            [
                'name'  => 'DateTime',
                'alias' => 'DateTime',
                'start' => 111,
                'end'   => 119
            ]
        ], $output['warnings']['unusedUseStatements']);
    }

    public function testSeesUseStatementsAsUsedIfTheyAppearInComments()
    {
        $output = $this->lintFile('UnusedUseStatementsDocblock.php');

        $this->assertEquals([
            [
                'name'  => 'SplMinHeap',
                'alias' => 'SplMinHeap',
                'start' => 39,
                'end'   => 49
            ],

            [
                'name'  => 'SplFileInfo',
                'alias' => 'SplFileInfo',
                'start' => 72,
                'end'   => 83
            ]
        ], $output['warnings']['unusedUseStatements']);
    }

    public function testCorrectlyIdentifiesMissingDocumentation()
    {
        $output = $this->lintFile('DocblockCorrectnessMissingDocumentation.php');

        $this->assertEquals([
            [
                'name'  => 'someMethod',
                'line'  => 41,
                'start' => 448,
                'end'   => 449
            ],

            [
                'name'  => 'someProperty',
                'line'  => 33,
                'start' => 321,
                'end'   => 322
            ],

            [
                'name'  => 'SOME_CONST',
                'line'  => 31,
                'start' => 294,
                'end'   => 295
            ],

            [
                'name'  => 'MissingDocumentation',
                'line'  => 47,
                'start' => 496,
                'end'   => 497
            ],

            [
                'name'  => 'some_function',
                'line'  => 5,
                'start' => 21,
                'end'   => 22
            ]
        ], $output['warnings']['docblockIssues']['missingDocumentation']);
    }

    public function testCorrectlyIdentifiesDocblockMissingParameter()
    {
        $output = $this->lintFile('DocblockCorrectnessMissingParameter.php');

        $this->assertEquals([
            [
                'name'      => 'some_function_missing_parameter',
                'line'      => 17,
                'start'     => 186,
                'end'       => 187,
                'parameter' => '$param2'
            ]
        ], $output['warnings']['docblockIssues']['parameterMissing']);
    }

    public function testDoesNotComplainAboutMissingParameterWhenItIsAReference()
    {
        $output = $this->lintFile('DocblockCorrectnessParamWithReference.php');

        $this->assertEquals([

        ], $output['warnings']['docblockIssues']['parameterMissing']);
    }

    public function testDoesNotComplainAboutMissingParameterWhenItIsVariadic()
    {
        $output = $this->lintFile('DocblockCorrectnessVariadicParam.php');

        $this->assertEquals([

        ], $output['warnings']['docblockIssues']['parameterMissing']);
    }

    public function testDoesNotComplainAboutDocblocksHavingFullInheritance()
    {
        $output = $this->lintFile('DocblockCorrectnessFullInheritance.php');

        $this->assertEquals([

        ], $output['warnings']['docblockIssues']['parameterMissing']);
    }

    public function testCorrectlyIdentifiesDocblockParameterTypeMismatch()
    {
        $output = $this->lintFile('DocblockCorrectnessParameterTypeMismatch.php');

        $this->assertEquals([
            [
                'name'      => 'some_function_parameter_incorrect_type',
                'line'      => 18,
                'start'     => 247,
                'end'       => 248,
                'parameter' => '$param1'
            ],
        ], $output['warnings']['docblockIssues']['parameterTypeMismatch']);
    }

    public function testCorrectlyIdentifiesDocblockSuperfluousParameters()
    {
        $output = $this->lintFile('DocblockCorrectnessSuperfluousParameters.php');

        $this->assertEquals([
            [
                'name'       => 'some_function_extra_parameter',
                'line'       => 20,
                'start'      => 270,
                'end'        => 271,
                'parameters' => ['$extra1', '$extra2']
            ]
        ], $output['warnings']['docblockIssues']['superfluousParameter']);
    }

    public function testCorrectlyIdentifiesDocblockMissingVarTag()
    {
        $output = $this->lintFile('DocblockCorrectnessMissingVarTag.php');

        $this->assertEquals([
            [
                'name'       => 'property',
                'line'       => 15,
                'start'      => 106,
                'end'        => 107
            ],

            [
                'name'       => 'CONSTANT',
                'line'       => 10,
                'start'      => 58,
                'end'        => 59
            ]
        ], $output['warnings']['docblockIssues']['varTagMissing']);
    }

    public function testCorrectlyIdentifiesDeprecatedCategoryTag()
    {
        $output = $this->lintFile('DocblockCorrectnessDeprecatedCategoryTag.php');

        $this->assertEquals([
            [
                'name'       => 'C',
                'line'       => 8,
                'start'      => 47,
                'end'        => 48
            ]
        ], $output['warnings']['docblockIssues']['deprecatedCategoryTag']);
    }

    public function testCorrectlyIdentifiesDeprecatedSubpackageTag()
    {
        $output = $this->lintFile('DocblockCorrectnessDeprecatedSubpackageTag.php');

        $this->assertEquals([
            [
                'name'       => 'C',
                'line'       => 8,
                'start'      => 49,
                'end'        => 50
            ]
        ], $output['warnings']['docblockIssues']['deprecatedSubpackageTag']);
    }

    public function testCorrectlyIdentifiesDeprecatedLinkTag()
    {
        $output = $this->lintFile('DocblockCorrectnessDeprecatedLinkTag.php');

        $this->assertEquals([
            [
                'name'       => 'C',
                'line'       => 8,
                'start'      => 63,
                'end'        => 64
            ]
        ], $output['warnings']['docblockIssues']['deprecatedLinkTag']);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new SemanticLint();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $output = $this->lintFile('MissingFile.php');
    }
}
