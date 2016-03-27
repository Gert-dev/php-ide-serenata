<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

class VariableTypeTest extends IndexedTest
{
    protected function getVariableType($file, $name)
    {
        $path = __DIR__ . '/VariableTypeTest/' . $file;

        $markerOffset = $this->getMarkerOffset($path, '<MARKER>');

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new VariableType();
        $command->setIndexDatabase($indexDatabase);

        return $command->getVariableType($path, file_get_contents($path), $name, $markerOffset);
    }

    protected function getMarkerOffset($path, $marker)
    {
        $testFileContents = @file_get_contents($path);

        $markerOffset = mb_strpos($testFileContents, $marker);

        return $markerOffset;
    }

    public function testCorrectlyAnalyzesTypeOverrideAnnotations()
    {
        $output = $this->getVariableType('TypeOverrideAnnotations.php', '$a');

        $this->assertEquals([
            'type'         => '\Traversable',
            'resolvedType' => '\Traversable'
        ], $output);

        $output = $this->getVariableType('TypeOverrideAnnotations.php', '$b');

        $this->assertEquals([
            'type'         => '\Traversable',
            'resolvedType' => '\Traversable'
        ], $output);

        $output = $this->getVariableType('TypeOverrideAnnotations.php', '$c');

        $this->assertEquals([
            'type'         => 'C',
            'resolvedType' => 'A\C'
        ], $output);
    }

    public function testCorrectlyResolvesThisInClass()
    {
        $output = $this->getVariableType('ThisInClass.php', '$this');

        $this->assertEquals([
            'type'         => 'B',
            'resolvedType' => 'A\B'
        ], $output);
    }

    public function testCorrectlyResolvesThisOutsideClass()
    {
        $output = $this->getVariableType('ThisOutsideClass.php', '$this');

        $this->assertEquals([
            'type'         => null,
            'resolvedType' => null
        ], $output);
    }

    public function testCorrectlyAnalyzesFunctionTypeHints()
    {
        $output = $this->getVariableType('FunctionParameterTypeHint.php', '$b');

        $this->assertEquals([
            'type'         => '\B',
            'resolvedType' => '\B'
        ], $output);
    }

    public function testCorrectlyAnalyzesFunctionDocblocks()
    {
        $output = $this->getVariableType('FunctionParameterDocblock.php', '$b');

        $this->assertEquals([
            'type'         => '\B',
            'resolvedType' => '\B'
        ], $output);
    }

    public function testCorrectlyAnalyzesMethodTypeHints()
    {
        $output = $this->getVariableType('MethodParameterTypeHint.php', '$b');

        $this->assertEquals([
            'type'         => 'B',
            'resolvedType' => 'A\B'
        ], $output);
    }

    public function testCorrectlyAnalyzesMethodDocblocks()
    {
        $output = $this->getVariableType('MethodParameterDocblock.php', '$b');

        $this->assertEquals([
            'type'         => 'B',
            'resolvedType' => 'A\B'
        ], $output);
    }

    public function testCorrectlyAnalyzesClosureTypeHints()
    {
        $output = $this->getVariableType('ClosureParameterTypeHint.php', '$b');

        $this->assertEquals([
            'type'         => 'B',
            'resolvedType' => 'A\B'
        ], $output);
    }

    public function testCorrectlyAnalyzesCatchBlockTypeHints()
    {
        $output = $this->getVariableType('CatchBlockTypeHint.php', '$e');

        $this->assertEquals([
            'type'         => '\UnexpectedValueException',
            'resolvedType' => '\UnexpectedValueException'
        ], $output);
    }

    public function testCorrectlyAnalyzesIfStatementWithInstanceof()
    {
        $output = $this->getVariableType('Instanceof.php', '$b');

        $this->assertEquals([
            'type'         => 'B',
            'resolvedType' => 'A\B'
        ], $output);
    }

    public function testCorrectlyAnalyzesForeach()
    {
        $output = $this->getVariableType('Foreach.php', '$a');

        $this->assertEquals([
            'type'         => '\DateTime',
            'resolvedType' => '\DateTime'
        ], $output);
    }

    public function testCorrectlyAnalyzesAssignments()
    {
        $output = $this->getVariableType('Assignment.php', '$a');

        $this->assertEquals([
            'type'         => '\DateTime',
            'resolvedType' => '\DateTime'
        ], $output);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new VariableType();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $output = $this->getVariableType('MissingFile.php', '$test');
    }
}
