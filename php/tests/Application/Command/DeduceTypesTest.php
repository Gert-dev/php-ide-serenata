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

        $command = new DeduceTypes($this->getParser());
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

    public function testCorrectlyAnalyzesTypeOverrideAnnotations()
    {
        $output = $this->deduceTypes('TypeOverrideAnnotations.php', ['$a']);

        $this->assertEquals(['\Traversable'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.php', ['$b']);

        $this->assertEquals(['\Traversable'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.php', ['$c']);

        $this->assertEquals(['\A\C', 'null'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.php', ['$d']);

        $this->assertEquals(['\A\D'], $output);
    }

    public function testCorrectlyResolvesThisInClass()
    {
        $output = $this->deduceTypes('ThisInClass.php', ['$this']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyResolvesThisOutsideClass()
    {
        $output = $this->deduceTypes('ThisOutsideClass.php', ['$this']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesFunctionTypeHints()
    {
        $output = $this->deduceTypes('FunctionParameterTypeHint.php', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    public function testCorrectlyAnalyzesFunctionDocblocks()
    {
        $output = $this->deduceTypes('FunctionParameterDocblock.php', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    public function testCorrectlyAnalyzesMethodTypeHints()
    {
        $output = $this->deduceTypes('MethodParameterTypeHint.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesMethodDocblocks()
    {
        $output = $this->deduceTypes('MethodParameterDocblock.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesClosureTypeHints()
    {
        $output = $this->deduceTypes('ClosureParameterTypeHint.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyMovesBeyondClosureScopeForVariableUses()
    {
        $output = $this->deduceTypes('ClosureVariableUseStatement.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.php', ['$c']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.php', ['$d']);

        $this->assertEquals(['\A\D'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.php', ['$e']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesCatchBlockTypeHints()
    {
        $output = $this->deduceTypes('CatchBlockTypeHint.php', ['$e']);

        $this->assertEquals(['\UnexpectedValueException'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofIf.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndVariableInsideCondition()
    {
        $output = $this->deduceTypes('InstanceofComplexIfVariableInsideCondition.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndAnd()
    {
        $output = $this->deduceTypes('InstanceofComplexIfAnd.php', ['$b']);

        $this->assertEquals(['\A\B', '\A\C', '\A\D'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndOr()
    {
        $output = $this->deduceTypes('InstanceofComplexIfOr.php', ['$b']);

        $this->assertEquals(['\A\B', '\A\C', '\A\D', '\A\E'], $output);
    }

    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofNestedIf.php', ['$b']);

        $this->assertEquals(['\A\B', '\A\A'], $output);
    }

    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceofAndNegation()
    {
        $output = $this->deduceTypes('InstanceofNestedIfWithNegation.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceofAndReassignment()
    {
        $output = $this->deduceTypes('InstanceofNestedIfReassignment.php', ['$b']);

        $this->assertEquals(['\A\A'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithNotInstanceof()
    {
        $output = $this->deduceTypes('IfNotInstanceof.php', ['$b']);

        $this->assertEquals(['\A\A'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithNotStrictlyEqualsNull()
    {
        $output = $this->deduceTypes('IfNotStrictlyEqualsNull.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithNotLooselyEqualsNull()
    {
        $output = $this->deduceTypes('IfNotLooselyEqualsNull.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithStrictlyEqualsNull()
    {
        $output = $this->deduceTypes('IfStrictlyEqualsNull.php', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithLooselyEqualsNull()
    {
        $output = $this->deduceTypes('IfLooselyEqualsNull.php', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithTruthy()
    {
        $output = $this->deduceTypes('IfTruthy.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithFalsy()
    {
        $output = $this->deduceTypes('IfFalsy.php', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    public function testTypeOverrideAnnotationsStillTakePrecedenceOverConditionals()
    {
        $output = $this->deduceTypes('IfWithTypeOverride.php', ['$b']);

        $this->assertEquals(['string'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithVariableHandlingFunction()
    {
        $output = $this->deduceTypes('IfVariableHandlingFunction.php', ['$b']);

        $this->assertEquals([
            'array',
            'bool',
            'callable',
            'float',
            'int',
            'null',
            'string',
            'object',
            'resource'
        ], $output);
    }

    public function testCorrectlyTreatsIfConditionAsSeparateScope()
    {
        $output = $this->deduceTypes('InstanceofIfSeparateScope.php', ['$b']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesElseIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofElseIf.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyConfinesTreatsElseIfConditionAsSeparateScope()
    {
        $output = $this->deduceTypes('InstanceofElseIfSeparateScope.php', ['$b']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesTernaryExpressionWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofTernary.php', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyConfinesTreatsTernaryExpressionConditionAsSeparateScope()
    {
        $output = $this->deduceTypes('InstanceofTernarySeparateScope.php', ['$b']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesTernaryExpressionWhereBothOperandsResultInTheSameType()
    {
        $output = $this->deduceTypes('TernarySameResultingType.php', ['$a']);

        $this->assertEquals(['\A'], $output);

        $output = $this->deduceTypes('TernarySameResultingType.php', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    public function testCorrectlyAnalyzesForeach()
    {
        $output = $this->deduceTypes('Foreach.php', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testCorrectlyAnalyzesAssignments()
    {
        $output = $this->deduceTypes('Assignment.php', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testCorrectlyIgnoresAssignmentsOutOfScope()
    {
        $output = $this->deduceTypes('AssignmentOutOfScope.php', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testDocblockTakesPrecedenceOverTypeHint()
    {
        $output = $this->deduceTypes('DocblockPrecedence.php', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    public function testSpecialTypesForParametersResolveCorrectly()
    {
        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.php', ['$a']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.php', ['$b']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.php', ['$c']);

        $this->assertEquals(['\A\C'], $output);
    }

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

    public function testCorrectlyProcessesStaticMethodCallAssignedToVariableWithFqcnWithLeadingSlash()
    {
        $result = $this->deduceTypes(
            'StaticMethodCallFqcnLeadingSlash.php',
            ['$data']
        );

        $this->assertEquals(['\A\B'], $result);
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
