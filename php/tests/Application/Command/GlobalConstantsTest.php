<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

class GlobalConstantsTest extends IndexedTest
{
    public function testGlobalConstants()
    {
        $path = __DIR__ . '/GlobalConstantsTest/' . 'GlobalConstants.php.test';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new GlobalConstants($this->getParser(), null, $indexDatabase);

        $output = $command->getGlobalConstants();

        $this->assertThat($output, $this->arrayHasKey('\DEFINE_CONSTANT'));
        $this->assertEquals($output['\DEFINE_CONSTANT']['name'], 'DEFINE_CONSTANT');
        $this->assertEquals($output['\DEFINE_CONSTANT']['fqcn'], '\DEFINE_CONSTANT');

        $this->assertThat($output, $this->arrayHasKey('\A\DEFINE_CONSTANT_NAMESPACED'));
        $this->assertEquals($output['\A\DEFINE_CONSTANT_NAMESPACED']['name'], 'DEFINE_CONSTANT_NAMESPACED');
        $this->assertEquals($output['\A\DEFINE_CONSTANT_NAMESPACED']['fqcn'], '\A\DEFINE_CONSTANT_NAMESPACED');

        $this->assertThat($output, $this->arrayHasKey('\A\FIRST_CONSTANT'));
        $this->assertEquals($output['\A\FIRST_CONSTANT']['name'], 'FIRST_CONSTANT');
        $this->assertEquals($output['\A\FIRST_CONSTANT']['fqcn'], '\A\FIRST_CONSTANT');

        $this->assertThat($output, $this->arrayHasKey('\A\SECOND_CONSTANT'));
        $this->assertEquals($output['\A\SECOND_CONSTANT']['name'], 'SECOND_CONSTANT');
        $this->assertEquals($output['\A\SECOND_CONSTANT']['fqcn'], '\A\SECOND_CONSTANT');

        $this->assertThat($output, $this->logicalNot($this->arrayHasKey('SHOULD_NOT_SHOW_UP')));
    }
}
