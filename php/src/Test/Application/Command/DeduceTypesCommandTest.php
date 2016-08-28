<?php

namespace PhpIntegrator\Test\Application\Command;

use PhpIntegrator\Application\Command\DeduceTypesCommand;

use PhpIntegrator\Test\IndexedTest;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class DeduceTypesCommandTest extends IndexedTest
{
    protected function deduceTypes($file, array $expressionParts)
    {
        $path = __DIR__ . '/DeduceTypesTest/' . $file;

        $markerOffset = $this->getMarkerOffset($path, '<MARKER>');

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new DeduceTypesCommand($this->getParser(), null, $indexDatabase);

        return $command->deduceTypes($path, file_get_contents($path), $expressionParts, $markerOffset);
    }

    protected function getMarkerOffset($path, $marker)
    {
        $testFileContents = @file_get_contents($path);

        $markerOffset = mb_strpos($testFileContents, $marker);

        return $markerOffset;
    }

    public function testCorrectlyAnalyzesTypeOverrideAnnotations()
    {
        $output = $this->deduceTypes('TypeOverrideAnnotations.php.test', ['$a']);

        $this->assertEquals(['\Traversable'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.php.test', ['$b']);

        $this->assertEquals(['\Traversable'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.php.test', ['$c']);

        $this->assertEquals(['\A\C', 'null'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.php.test', ['$d']);

        $this->assertEquals(['\A\D'], $output);
    }

    public function testCorrectlyResolvesThisInClass()
    {
        $output = $this->deduceTypes('ThisInClass.php.test', ['$this']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyResolvesThisOutsideClass()
    {
        $output = $this->deduceTypes('ThisOutsideClass.php.test', ['$this']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesFunctionTypeHints()
    {
        $output = $this->deduceTypes('FunctionParameterTypeHint.php.test', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    public function testCorrectlyAnalyzesFunctionDocblocks()
    {
        $output = $this->deduceTypes('FunctionParameterDocblock.php.test', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    public function testCorrectlyAnalyzesMethodTypeHints()
    {
        $output = $this->deduceTypes('MethodParameterTypeHint.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesMethodDocblocks()
    {
        $output = $this->deduceTypes('MethodParameterDocblock.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesClosureTypeHints()
    {
        $output = $this->deduceTypes('ClosureParameterTypeHint.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyMovesBeyondClosureScopeForVariableUses()
    {
        $output = $this->deduceTypes('ClosureVariableUseStatement.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.php.test', ['$c']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.php.test', ['$d']);

        $this->assertEquals(['\A\D'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.php.test', ['$e']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesCatchBlockTypeHints()
    {
        $output = $this->deduceTypes('CatchBlockTypeHint.php.test', ['$e']);

        $this->assertEquals(['\UnexpectedValueException'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofIf.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndVariableInsideCondition()
    {
        $output = $this->deduceTypes('InstanceofComplexIfVariableInsideCondition.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndAnd()
    {
        $output = $this->deduceTypes('InstanceofComplexIfAnd.php.test', ['$b']);

        $this->assertEquals(['\A\B', '\A\C', '\A\D'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndOr()
    {
        $output = $this->deduceTypes('InstanceofComplexIfOr.php.test', ['$b']);

        $this->assertEquals(['\A\B', '\A\C', '\A\D', '\A\E'], $output);
    }

    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofNestedIf.php.test', ['$b']);

        $this->assertEquals(['\A\B', '\A\A'], $output);
    }

    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceofAndNegation()
    {
        $output = $this->deduceTypes('InstanceofNestedIfWithNegation.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceofAndReassignment()
    {
        $output = $this->deduceTypes('InstanceofNestedIfReassignment.php.test', ['$b']);

        $this->assertEquals(['\A\A'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithNotInstanceof()
    {
        $output = $this->deduceTypes('IfNotInstanceof.php.test', ['$b']);

        $this->assertEquals(['\A\A'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithNotStrictlyEqualsNull()
    {
        $output = $this->deduceTypes('IfNotStrictlyEqualsNull.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithNotLooselyEqualsNull()
    {
        $output = $this->deduceTypes('IfNotLooselyEqualsNull.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithStrictlyEqualsNull()
    {
        $output = $this->deduceTypes('IfStrictlyEqualsNull.php.test', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithLooselyEqualsNull()
    {
        $output = $this->deduceTypes('IfLooselyEqualsNull.php.test', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithTruthy()
    {
        $output = $this->deduceTypes('IfTruthy.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithFalsy()
    {
        $output = $this->deduceTypes('IfFalsy.php.test', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    public function testTypeOverrideAnnotationsStillTakePrecedenceOverConditionals()
    {
        $output = $this->deduceTypes('IfWithTypeOverride.php.test', ['$b']);

        $this->assertEquals(['string'], $output);
    }

    public function testCorrectlyAnalyzesComplexIfStatementWithVariableHandlingFunction()
    {
        $output = $this->deduceTypes('IfVariableHandlingFunction.php.test', ['$b']);

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
        $output = $this->deduceTypes('InstanceofIfSeparateScope.php.test', ['$b']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesElseIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofElseIf.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyConfinesTreatsElseIfConditionAsSeparateScope()
    {
        $output = $this->deduceTypes('InstanceofElseIfSeparateScope.php.test', ['$b']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesTernaryExpressionWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofTernary.php.test', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    public function testCorrectlyConfinesTreatsTernaryExpressionConditionAsSeparateScope()
    {
        $output = $this->deduceTypes('InstanceofTernarySeparateScope.php.test', ['$b']);

        $this->assertEquals([], $output);
    }

    public function testCorrectlyAnalyzesTernaryExpression()
    {
        $output = $this->deduceTypes('TernaryExpression.php.test', ['$a']);

        $this->assertEquals(['\A'], $output);

        $output = $this->deduceTypes('TernaryExpression.php.test', ['$b']);

        $this->assertEquals(['\B'], $output);

        $output = $this->deduceTypes('TernaryExpression.php.test', ['$c']);

        $this->assertEquals(['\C', 'null'], $output);

        $output = $this->deduceTypes('TernaryExpression.php.test', ['$d']);

        $this->assertEquals(['\A', '\C', 'null'], $output);
    }

    public function testCorrectlyAnalyzesForeach()
    {
        $output = $this->deduceTypes('Foreach.php.test', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testCorrectlyAnalyzesAssignments()
    {
        $output = $this->deduceTypes('Assignment.php.test', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testCorrectlyIgnoresAssignmentsOutOfScope()
    {
        $output = $this->deduceTypes('AssignmentOutOfScope.php.test', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    public function testDocblockTakesPrecedenceOverTypeHint()
    {
        $output = $this->deduceTypes('DocblockPrecedence.php.test', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    public function testSpecialTypesForParametersResolveCorrectly()
    {
        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.php.test', ['$a']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.php.test', ['$b']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.php.test', ['$c']);

        $this->assertEquals(['\A\C'], $output);
    }

    public function testCorrectlyAnalyzesStaticPropertyAccess()
    {
        $result = $this->deduceTypes(
            'StaticPropertyAccess.php.test',
            ['Bar', '$testProperty']
        );

        $this->assertEquals(['\DateTime'], $result);
    }

    public function testCorrectlyAnalyzesSelf()
    {
        $result = $this->deduceTypes(
            'Self.php.test',
            ['self', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesStatic()
    {
        $result = $this->deduceTypes(
            'Static.php.test',
            ['static', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesParent()
    {
        $result = $this->deduceTypes(
            'Parent.php.test',
            ['parent', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesThis()
    {
        $result = $this->deduceTypes(
            'This.php.test',
            ['$this', 'testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesVariables()
    {
        $result = $this->deduceTypes(
            'Variable.php.test',
            ['$var', 'testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    public function testCorrectlyAnalyzesGlobalFunctions()
    {
        $result = $this->deduceTypes(
            'GlobalFunction.php.test',
            ['\global_function()']
        );

        $this->assertEquals(['\B', 'null'], $result);
    }

    public function testCorrectlyAnalyzesClosures()
    {
        $result = $this->deduceTypes(
            'Closure.php.test',
            ['$var']
        );

        $this->assertEquals(['\Closure'], $result);
    }

    public function testCorrectlyAnalyzesNewWithStatic()
    {
        $result = $this->deduceTypes(
            'NewWithStatic.php.test',
            ['new static']
        );

        $this->assertEquals(['\Bar'], $result);
    }

    public function testCorrectlyAnalyzesClone()
    {
        $result = $this->deduceTypes(
            'Clone.php.test',
            ['clone $var']
        );

        $this->assertEquals(['\Bar'], $result);
    }

    public function testCorrectlyAnalyzesLongerChains()
    {
        $result = $this->deduceTypes(
            'LongerChain.php.test',
            ['$this', 'testProperty', 'aMethod()', 'anotherProperty']
        );

        $this->assertEquals(['\DateTime'], $result);
    }

    public function testCorrectlyAnalyzesScalarTypes()
    {
        $file = 'ScalarType.php.test';

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
            'SelfAssign.php.test',
            ['$foo1']
        );

        $this->assertEquals(['\A\Foo'], $result);

        $result = $this->deduceTypes(
            'SelfAssign.php.test',
            ['$foo2']
        );

        $this->assertEquals(['\A\Foo'], $result);

        $result = $this->deduceTypes(
            'SelfAssign.php.test',
            ['$foo3']
        );

        $this->assertEquals(['\A\Foo'], $result);
    }

    public function testCorrectlyProcessesStaticMethodCallAssignedToVariableWithFqcnWithLeadingSlash()
    {
        $result = $this->deduceTypes(
            'StaticMethodCallFqcnLeadingSlash.php.test',
            ['$data']
        );

        $this->assertEquals(['\A\B'], $result);
    }

    public function testCorrectlyReturnsMultipleTypes()
    {
        $result = $this->deduceTypes(
            'MultipleTypes.php.test',
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
