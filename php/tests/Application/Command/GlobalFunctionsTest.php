<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

class GlobalFunctionsTest extends IndexedTest
{
    public function testGlobalFunctions()
    {
        $path = __DIR__ . '/GlobalFunctionsTest/' . 'GlobalFunctions.php.test';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new GlobalFunctions($this->getParser(), null, $indexDatabase);

        $output = $command->getGlobalFunctions();

        $this->assertThat($output, $this->arrayHasKey('\A\firstFunction'));
        $this->assertEquals($output['\A\firstFunction']['name'], 'firstFunction');
        $this->assertEquals($output['\A\firstFunction']['fqcn'], '\A\firstFunction');
        $this->assertThat($output, $this->arrayHasKey('\A\secondFunction'));
        $this->assertEquals($output['\A\secondFunction']['name'], 'secondFunction');
        $this->assertEquals($output['\A\secondFunction']['fqcn'], '\A\secondFunction');
        $this->assertThat($output, $this->logicalNot($this->arrayHasKey('shouldNotShowUp')));
    }
}
