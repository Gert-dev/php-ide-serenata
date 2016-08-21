<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

use PhpIntegrator\Indexing\IndexDatabase;

class SemanticLintTest extends IndexedTest
{
    protected function lintFile($file, $indexingMayFail = false)
    {
        $path = __DIR__ . '/SemanticLintTest/' . $file;

        $indexDatabase = $this->getDatabaseForTestFile($path, $indexingMayFail);

        $command = new SemanticLint($this->getParser(), null, $indexDatabase);

        return $command->semanticLint($path, file_get_contents($path));
    }

    public function testCorrectlyIdentifiesSyntaxErrors()
    {
        $output = $this->lintFile('SyntaxError.php.test', true);

        $this->assertEquals(2, count($output['errors']['syntaxErrors']));
    }

    public function testReportsUnknownClassesWithNoNamespace()
    {
        $output = $this->lintFile('UnknownClassesNoNamespace.php.test');

        $this->assertEquals([
            [
                'name'      => 'A\B',
                'namespace' => null,
                'start'     => 32,
                'end'       => 35
            ]
        ], $output['errors']['unknownClasses']);
    }

    public function testReportsUnknownClassesWithSingleNamespace()
    {
        $output = $this->lintFile('UnknownClassesSingleNamespace.php.test');

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
        $output = $this->lintFile('UnknownClassesMultipleNamespaces.php.test');

        $this->assertEquals([
            [
                'name'      => 'DateTime',
                'namespace' => 'A',
                'start'     => 97,
                'end'       => 105
            ],

            [
                'name'      => 'SplFileInfo',
                'namespace' => 'B',
                'start'     => 153,
                'end'       => 164
            ]
        ], $output['errors']['unknownClasses']);
    }

    public function testReportsUnknownClassesInDocBlocks()
    {
        $output = $this->lintFile('UnknownClassesDocblock.php.test');

        $this->assertEquals([
            [
                'name'      => 'A\B',
                'namespace' => 'A',
                'start'     => 75,
                'end'       => 95
            ],

            [
                'name'      => 'A\C',
                'namespace' => 'A',
                'start'     => 75,
                'end'       => 95
            ],

            [
                'name'      => 'MissingAnnotationClass',
                'namespace' => 'A',
                'start'     => 175,
                'end'       => 197
            ],

            [
                'name'      => 'A\MissingAnnotationClass',
                'namespace' => 'A',
                'start'     => 202,
                'end'       => 226
            ],

            [
                'name'      => 'B\MissingAnnotationClass',
                'namespace' => 'A',
                'start'     => 231,
                'end'       => 256
            ]
        ], $output['errors']['unknownClasses']);
    }

    public function testDoesNotComplainAboutUnknownClassesInGroupedUseStatements()
    {
        $output = $this->lintFile('GroupedUseStatements.php.test');

        $this->assertEquals([], $output['errors']['unknownClasses']);
    }

    public function testReportsInvalidMemberCallsOnAnExpressionWithoutAType()
    {
        $output = $this->lintFile('UnknownMemberExpressionWithNoType.php.test');

        $this->assertEquals([
            [
                'memberName' => 'foo',
                'start'      => 21,
                'end'        => 32
            ]
        ], $output['errors']['unknownMembers']['expressionHasNoType']);
    }

    public function testReportsInvalidMemberCallsOnAnExpressionThatDoesNotReturnAClasslike()
    {
        $output = $this->lintFile('UnknownMemberExpressionWithNoClasslike.php.test');

        $this->assertEquals([
            [
                'memberName'     => 'foo',
                'expressionType' => 'int',
                'start'          => 57,
                'end'            => 68
            ],

            [
                'memberName'     => 'foo',
                'expressionType' => 'bool',
                'start'          => 57,
                'end'            => 68
            ]
        ], $output['errors']['unknownMembers']['expressionIsNotClasslike']);
    }

    public function testReportsInvalidMemberCallsOnAnExpressionThatReturnsAClasslikeWithNoSuchMember()
    {
        $output = $this->lintFile('UnknownMemberExpressionWithNoSuchMember.php.test');

        $this->assertEquals([
            [
                'memberName'     => 'foo',
                'expressionType' => '\A\Foo',
                'start'          => 124,
                'end'            => 135
            ],

            [
                'memberName'     => 'bar',
                'expressionType' => '\A\Foo',
                'start'          => 137,
                'end'            => 147
            ],

            [
                'memberName'     => 'CONSTANT',
                'expressionType' => '\A\Foo',
                'start'          => 187,
                'end'            => 200
            ]
        ], $output['errors']['unknownMembers']['expressionHasNoSuchMember']);
    }

    public function testReportsInvalidMemberCallsOnAnExpressionThatReturnsAClasslikeWithNoSuchMemberCausingANewMemberToBeCreated()
    {
        $output = $this->lintFile('UnknownMemberExpressionWithNoSuchMember.php.test');

        $this->assertEquals([
            [
                'memberName'     => 'test',
                'expressionType' => '\A\Foo',
                'start'          => 80,
                'end'            => 91
            ],

            [
                'memberName'     => 'fooProp',
                'expressionType' => '\A\Foo',
                'start'          => 149,
                'end'            => 162
            ],

            [
                'memberName'     => 'barProp',
                'expressionType' => '\A\Foo',
                'start'          => 168,
                'end'            => 181
            ]
        ], $output['warnings']['unknownMembers']['expressionNewMemberWillBeCreated']);
    }

    public function testReportsUnknownGlobalFunctions()
    {
        $output = $this->lintFile('UnknownGlobalFunctions.php.test');

        $this->assertEquals([
            [
                'name'  => 'foo',
                'start' => 42,
                'end'   => 47
            ],

            [
                'name'  => 'bar',
                'start' => 49,
                'end'   => 54
            ],

            [
                'name'  => '\A\foo',
                'start' => 56,
                'end'   => 64
            ]
        ], $output['errors']['unknownGlobalFunctions']);
    }

    public function testReportsUnknownGlobalConstants()
    {
        $output = $this->lintFile('UnknownGlobalConstants.php.test');

        $this->assertEquals([
            [
                'name'  => 'FOO',
                'start' => 40,
                'end'   => 43
            ],

            [
                'name'  => 'BAR',
                'start' => 45,
                'end'   => 48
            ],

            [
                'name'  => '\A\FOO',
                'start' => 50,
                'end'   => 56
            ]
        ], $output['errors']['unknownGlobalConstants']);
    }

    public function testReportsUnusedUseStatementsWithSingleNamespace()
    {
        $output = $this->lintFile('UnusedUseStatementsSingleNamespace.php.test');

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
        $output = $this->lintFile('UnusedUseStatementsMultipleNamespaces.php.test');

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

    public function testReportsUnusedUseStatementsWithGroupedUseStatements()
    {
        $output = $this->lintFile('GroupedUseStatements.php.test');

        $this->assertEquals([
            [
                'name'  => 'B\Foo',
                'alias' => 'Foo',
                'start' => 106,
                'end'   => 109
            ],

            [
                'name'  => 'B\Bar',
                'alias' => 'Bar',
                'start' => 119,
                'end'   => 122
            ],

            [
                'name'  => 'B\Missing',
                'alias' => 'Missing',
                'start' => 132,
                'end'   => 139
            ]
        ], $output['warnings']['unusedUseStatements']);
    }

    public function testSeesUseStatementsAsUsedIfTheyAppearInComments()
    {
        $output = $this->lintFile('UnusedUseStatementsDocblock.php.test');

        $this->assertEquals([
            [
                'name'  => 'SplMinHeap',
                'alias' => 'SplMinHeap',
                'start' => 53,
                'end'   => 63
            ],

            [
                'name'  => 'SplFileInfo',
                'alias' => 'SplFileInfo',
                'start' => 69,
                'end'   => 80
            ]
        ], $output['warnings']['unusedUseStatements']);
    }

    public function testCorrectlyIdentifiesMissingDocumentation()
    {
        $output = $this->lintFile('DocblockCorrectnessMissingDocumentation.php.test');

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
                'start' => 331,
                'end'   => 344
            ],

            [
                'name'  => 'SOME_CONST',
                'line'  => 31,
                'start' => 300,
                'end'   => 310
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
        $output = $this->lintFile('DocblockCorrectnessMissingParameter.php.test');

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
        $output = $this->lintFile('DocblockCorrectnessParamWithReference.php.test');

        $this->assertEquals([

        ], $output['warnings']['docblockIssues']['parameterMissing']);
    }

    public function testDoesNotComplainAboutMissingParameterWhenItIsVariadic()
    {
        $output = $this->lintFile('DocblockCorrectnessVariadicParam.php.test');

        $this->assertEquals([

        ], $output['warnings']['docblockIssues']['parameterMissing']);
    }

    public function testDoesNotComplainAboutDocblocksHavingFullInheritance()
    {
        $output = $this->lintFile('DocblockCorrectnessFullInheritance.php.test');

        $this->assertEquals([

        ], $output['warnings']['docblockIssues']['parameterMissing']);
    }

    public function testCorrectlyIdentifiesDocblockParameterTypeMismatch()
    {
        $output = $this->lintFile('DocblockCorrectnessParameterTypeMismatch.php.test');

        $this->assertEquals([
            [
                'name'      => 'some_function_parameter_incorrect_type',
                'line'      => 20,
                'start'     => 287,
                'end'       => 288,
                'parameter' => '$param1'
            ],
        ], $output['warnings']['docblockIssues']['parameterTypeMismatch']);
    }

    public function testCorrectlyIdentifiesDocblockSuperfluousParameters()
    {
        $output = $this->lintFile('DocblockCorrectnessSuperfluousParameters.php.test');

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
        $output = $this->lintFile('DocblockCorrectnessMissingVarTag.php.test');

        $this->assertEquals([
            [
                'name'       => 'property',
                'line'       => 15,
                'start'      => 116,
                'end'        => 125
            ],

            [
                'name'       => 'CONSTANT',
                'line'       => 10,
                'start'      => 64,
                'end'        => 73
            ]
        ], $output['warnings']['docblockIssues']['varTagMissing']);
    }

    public function testCorrectlyIdentifiesDeprecatedCategoryTag()
    {
        $output = $this->lintFile('DocblockCorrectnessDeprecatedCategoryTag.php.test');

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
        $output = $this->lintFile('DocblockCorrectnessDeprecatedSubpackageTag.php.test');

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
        $output = $this->lintFile('DocblockCorrectnessDeprecatedLinkTag.php.test');

        $this->assertEquals([
            [
                'name'       => 'C',
                'line'       => 8,
                'start'      => 63,
                'end'        => 64
            ]
        ], $output['warnings']['docblockIssues']['deprecatedLinkTag']);
    }
}
