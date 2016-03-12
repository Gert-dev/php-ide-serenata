<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

class AvailableVariablesTest extends IndexedTest
{
    protected function getCommand($file)
    {
        $path = $this->getTestFilePath($file);

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new AvailableVariables();
        $command->setIndexDatabase($indexDatabase);

        return $command;
    }

    protected function getTestFilePath($name)
    {
        return __DIR__ . '/AvailableVariablesTest/' . $name;
    }

    protected function getAvailableVariables($file)
    {
        $command = $this->getCommand($file);

        $path = $this->getTestFilePath($file);

        $markerOffset = $this->getMarkerOffset($path, '<MARKER>');

        return $command->getAvailableVariables($path, $markerOffset, false);
    }

    protected function getMarkerOffset($path, $marker)
    {
        $testFileContents = file_get_contents($path);

        $markerOffset = mb_strpos($testFileContents, $marker);

        return $markerOffset;
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

    public function testCorrectlyIgnoresVariousStatements()
    {
        $file = 'VariousStatements.php';
        $fullPath = $this->getTestFilePath($file);

        $command = $this->getCommand($file);

        $i = 1;
        $markerOffsets = [];

        while (true) {
            $markerOffset = $this->getMarkerOffset($fullPath, "MARKER_{$i}");

            if ($markerOffset === false) {
                break;
            }

            $markerOffsets[$i++] = $markerOffset;
        }

        $doMarkerTest = function ($markerNumber, array $variableNames) use ($command, $fullPath, $markerOffsets) {
            $list = [];

            foreach ($variableNames as $variableName) {
                $list[$variableName] = ['name' => $variableName, 'type' => null];
            }

            $this->assertEquals(
                $list,
                $command->getAvailableVariables($fullPath, $markerOffsets[$markerNumber], false)
            );
        };

        $doMarkerTest(1, []);
        $doMarkerTest(2, ['$a']);
        $doMarkerTest(3, []);
        $doMarkerTest(4, ['$b']);
        $doMarkerTest(5, []);
        $doMarkerTest(6, ['$b2']);
        $doMarkerTest(7, []);
        $doMarkerTest(8, ['$c']);
        $doMarkerTest(9, []);
        $doMarkerTest(10, ['$d']);
        $doMarkerTest(11, ['$key', '$value']);
        $doMarkerTest(12, ['$key', '$value', '$e']);
        $doMarkerTest(13, ['$i']);
        $doMarkerTest(14, ['$i', '$f']);
        $doMarkerTest(15, []);
        $doMarkerTest(16, ['$g']);
        $doMarkerTest(17, []);
        $doMarkerTest(18, ['$h']);
        $doMarkerTest(19, []);
        $doMarkerTest(20, ['$i']);
        $doMarkerTest(21, []);
        $doMarkerTest(22, ['$j']);
        $doMarkerTest(23, []);
        $doMarkerTest(24, ['$k']);
        $doMarkerTest(25, []);
        $doMarkerTest(26, ['$l']);
        $doMarkerTest(27, []);
        $doMarkerTest(28, ['$m']);
        // $doMarkerTest(29, []); // TODO: Can't be solved for now, see also the implementation code.
        $doMarkerTest(30, ['$n']);
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
