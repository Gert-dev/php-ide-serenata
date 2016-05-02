<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

class GlobalConstantsTest extends IndexedTest
{
    public function testGlobalConstants()
    {
        $path = __DIR__ . '/GlobalConstantsTest/' . 'GlobalConstants.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new GlobalConstants();
        $command->setIndexDatabase($indexDatabase);

        $output = $command->getGlobalConstants();

        $this->assertThat($output, $this->arrayHasKey('FIRST_CONSTANT'));
        $this->assertEquals($output['FIRST_CONSTANT']['fqsen'], 'A\FIRST_CONSTANT');
        $this->assertThat($output, $this->arrayHasKey('SECOND_CONSTANT'));
        $this->assertEquals($output['SECOND_CONSTANT']['fqsen'], 'A\SECOND_CONSTANT');
        $this->assertThat($output, $this->logicalNot($this->arrayHasKey('SHOULD_NOT_SHOW_UP')));
    }
}
