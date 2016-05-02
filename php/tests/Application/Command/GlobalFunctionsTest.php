<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

class GlobalFunctionsTest extends IndexedTest
{
    public function testGlobalFunctions()
    {
        $path = __DIR__ . '/GlobalFunctionsTest/' . 'GlobalFunctions.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new GlobalFunctions();
        $command->setIndexDatabase($indexDatabase);

        $output = $command->getGlobalFunctions();

        $this->assertThat($output, $this->arrayHasKey('firstFunction'));
        $this->assertEquals($output['firstFunction']['fqsen'], 'A\firstFunction');
        $this->assertThat($output, $this->arrayHasKey('secondFunction'));
        $this->assertEquals($output['secondFunction']['fqsen'], 'A\secondFunction');
        $this->assertThat($output, $this->logicalNot($this->arrayHasKey('shouldNotShowUp')));
    }
}
