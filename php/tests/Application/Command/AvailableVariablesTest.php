<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

class AvailableVariablesTest extends IndexedTest
{
    protected function getAvailableVariables($file)
    {
        $path = __DIR__ . '/AvailableVariablesTest/' . $file;

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new AvailableVariables();
        $command->setIndexDatabase($indexDatabase);

        $testFileContents = file_get_contents($path);

        $markerOffset = mb_strpos($testFileContents, '// <MARKER>');

        assert($markerOffset !== false);

        return $command->getAvailableVariables($path, $markerOffset, false);
    }

    public function testReturnsOnlyVariablesRelevantToTheGlobalScope()
    {
        $output = $this->getAvailableVariables('GlobalScope.php');

        $this->assertEquals([
            '$var3' => ['name' => '$var3', 'type' => null],
            '$var2' => ['name' => '$var2', 'type' => null],
            '$var1' => ['name' => '$var1', 'type' => null]
        ], $output);
    }

    public function testReturnsOnlyVariablesRelevantToTheCurrentFunction()
    {
        $output = $this->getAvailableVariables('FunctionScope.php');

        $this->assertEquals([
            '$closure' => ['name' => '$closure', 'type' => null],
            '$param2'  => ['name' => '$param2',  'type' => null],
            '$param1'  => ['name' => '$param1',  'type' => null]
        ], $output);
    }

    public function testReturnsOnlyVariablesRelevantToTheCurrentMethod()
    {
        $output = $this->getAvailableVariables('ClassMethodScope.php');

        $this->assertEquals([
            '$this'    => ['name' => '$this',    'type' => null],
            '$closure' => ['name' => '$closure', 'type' => null],
            '$param2'  => ['name' => '$param2',  'type' => null],
            '$param1'  => ['name' => '$param1',  'type' => null]
        ], $output);
    }

    public function testReturnsOnlyVariablesRelevantToTheCurrentClosure()
    {
        $output = $this->getAvailableVariables('ClosureScope.php');

        $this->assertEquals([
            '$this'         => ['name' => '$this',         'type' => null],
            '$test'         => ['name' => '$test',         'type' => null],
            '$something'    => ['name' => '$something',    'type' => null],
            '$closureParam' => ['name' => '$closureParam', 'type' => null]
        ], $output);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new AvailableVariables();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $output = $this->getAvailableVariables('MissingFile.php', 0, false);
    }
}
