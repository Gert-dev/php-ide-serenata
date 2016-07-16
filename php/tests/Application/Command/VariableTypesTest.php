<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

use PhpIntegrator\Indexing\IndexDatabase;

class VariableTypesTest extends IndexedTest
{
    protected function getVariableTypes($file, $name)
    {
        $path = __DIR__ . '/VariableTypesTest/' . $file;

        $markerOffset = $this->getMarkerOffset($path, '// <MARKER>');

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new VariableTypes();
        $command->setIndexDatabase($indexDatabase);

        return $command->getVariableTypes($path, file_get_contents($path), $name, $markerOffset);
    }

    protected function getMarkerOffset($path, $marker)
    {
        $testFileContents = @file_get_contents($path);

        $markerOffset = mb_strpos($testFileContents, $marker);

        return $markerOffset;
    }

    public function testCorrectlyAnalyzesTypeOverrideAnnotations()
    {
        $output = $this->getVariableTypes('TypeOverrideAnnotations.php', '$a');

        $this->assertEquals(['\Traversable'], $output);

        $output = $this->getVariableTypes('TypeOverrideAnnotations.php', '$b');

        $this->assertEquals(['\Traversable'], $output);

        $output = $this->getVariableTypes('TypeOverrideAnnotations.php', '$c');

        $this->assertEquals(['\A\C', 'null'], $output);

        $output = $this->getVariableTypes('TypeOverrideAnnotations.php', '$d');

        $this->assertEquals(['\A\D'], $output);
    }

    public function testCorrectlyResolvesThisInClass()
    {
        $output = $this->getVariableTypes('ThisInClass.php', '$this');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyResolvesThisOutsideClass()
    {
        $output = $this->getVariableTypes('ThisOutsideClass.php', '$this');

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesFunctionTypeHints()
    {
        $output = $this->getVariableTypes('FunctionParameterTypeHint.php', '$b');

        $this->assertEquals(['\B'], $output);
    }

    public function testCorrectlyAnalyzesFunctionDocblocks()
    {
        $output = $this->getVariableTypes('FunctionParameterDocblock.php', '$b');

        $this->assertEquals(['\B'], $output);
    }

    public function testCorrectlyAnalyzesMethodTypeHints()
    {
        $output = $this->getVariableTypes('MethodParameterTypeHint.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesMethodDocblocks()
    {
        $output = $this->getVariableTypes('MethodParameterDocblock.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesClosureTypeHints()
    {
        $output = $this->getVariableTypes('ClosureParameterTypeHint.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyMovesBeyondClosureScopeForVariableUses()
    {
        $output = $this->getVariableTypes('ClosureVariableUseStatement.php', '$b');

        $this->assertEquals(['\A\B'], $output);

        $output = $this->getVariableTypes('ClosureVariableUseStatement.php', '$c');

        $this->assertEquals(['\A\C'], $output);

        $output = $this->getVariableTypes('ClosureVariableUseStatement.php', '$d');

        $this->assertEquals(['\A\D'], $output);

        $output = $this->getVariableTypes('ClosureVariableUseStatement.php', '$e');

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesCatchBlockTypeHints()
    {
        $output = $this->getVariableTypes('CatchBlockTypeHint.php', '$e');

        $this->assertEquals(['\UnexpectedValueException'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithInstanceof()
    {
        $output = $this->getVariableTypes('InstanceofIf.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndVariableInsideCondition()
    {
        $output = $this->getVariableTypes('InstanceofComplexIfVariableInsideCondition.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndAnd()
    {
        $output = $this->getVariableTypes('InstanceofComplexIfAnd.php', '$b');

        $this->assertEquals(['\A\B', '\A\C', '\A\D'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndOr()
    {
        $output = $this->getVariableTypes('InstanceofComplexIfOr.php', '$b');

        $this->assertEquals(['\A\B', '\A\C', '\A\D', '\A\E'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithNotInstanceof()
    {
        $output = $this->getVariableTypes('IfNotInstanceof.php', '$b');

        $this->assertEquals(['\A\A'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithNotStrictlyEqualsNull()
    {
        $output = $this->getVariableTypes('IfNotStrictlyEqualsNull.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithNotLooselyEqualsNull()
    {
        $output = $this->getVariableTypes('IfNotLooselyEqualsNull.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithStrictlyEqualsNull()
    {
        $output = $this->getVariableTypes('IfStrictlyEqualsNull.php', '$b');

        $this->assertEquals(['null'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithLooselyEqualsNull()
    {
        $output = $this->getVariableTypes('IfLooselyEqualsNull.php', '$b');

        $this->assertEquals(['null'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithTruthy()
    {
        $output = $this->getVariableTypes('IfTruthy.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithFalsy()
    {
        $output = $this->getVariableTypes('IfFalsy.php', '$b');

        $this->assertEquals(['null'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithVariableHandlingFunction()
    {
        $output = $this->getVariableTypes('IfVariableHandlingFunction.php', '$b');

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
        $output = $this->getVariableTypes('InstanceofIfSeparateScope.php', '$b');

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesElseIfStatementWithInstanceof()
    {
        $output = $this->getVariableTypes('InstanceofElseIf.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyConfinesTreatsElseIfConditionAsSeparateScope()
    {
        $output = $this->getVariableTypes('InstanceofElseIfSeparateScope.php', '$b');

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesTernaryExpressionWithInstanceof()
    {
        $output = $this->getVariableTypes('InstanceofTernary.php', '$b');

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyConfinesTreatsTernaryExpressionConditionAsSeparateScope()
    {
        $output = $this->getVariableTypes('InstanceofTernarySeparateScope.php', '$b');

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesTernaryExpressionWhereBothOperandsResultInTheSameType()
    {
        $output = $this->getVariableTypes('TernarySameResultingType.php', '$a');

        $this->assertEquals(['\A'], $output);

        $output = $this->getVariableTypes('TernarySameResultingType.php', '$b');

        $this->assertEquals(['\B'], $output);
    }

    public function testCorrectlyAnalyzesForeach()
    {
        $output = $this->getVariableTypes('Foreach.php', '$a');

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testCorrectlyAnalyzesAssignments()
    {
        $output = $this->getVariableTypes('Assignment.php', '$a');

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testCorrectlyIgnoresAssignmentsOutOfScope()
    {
        $output = $this->getVariableTypes('AssignmentOutOfScope.php', '$a');

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testDocblockTakesPrecedenceOverTypeHint()
    {
        $output = $this->getVariableTypes('DocblockPrecedence.php', '$b');

        $this->assertEquals(['\B'], $output);
    }

    public function testSpecialTypesForParametersResolveCorrectly()
    {
        $output = $this->getVariableTypes('FunctionParameterTypeHintSpecial.php', '$a');

        $this->assertEquals(['\A\C'], $output);

        $output = $this->getVariableTypes('FunctionParameterTypeHintSpecial.php', '$b');

        $this->assertEquals(['\A\C'], $output);

        $output = $this->getVariableTypes('FunctionParameterTypeHintSpecial.php', '$c');

        $this->assertEquals(['\A\C'], $output);
    }

    public function testCorrectlyReturnsMultipleTypes()
    {
        $output = $this->getVariableTypes('MultipleTypes.php', '$a');

        $this->assertEquals([
            'string',
            'int',
            '\Foo',
            '\Bar'
        ], $output);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new VariableTypes();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $output = $this->getVariableTypes('MissingFile.php', '$test');
    }
}
