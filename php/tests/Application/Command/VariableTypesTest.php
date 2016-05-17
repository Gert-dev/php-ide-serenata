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

        $this->assertEquals(['\A\C'], $output);
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
        $output = $this->getVariableTypes('Instanceof.php', '$b');

        $this->assertEquals(['\A\B'], $output);
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
