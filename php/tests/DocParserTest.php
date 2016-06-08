<?php

namespace PhpIntegrator;

use PhpIntegrator\DocParser;

class DocParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParamTagAtEndIsInterpretedCorrectly()
    {
        $parser = new DocParser();
        $result = $parser->parse('
            /**
             * @param string $foo Test description.
             */
        ', [DocParser::PARAM_TYPE], '');

        $this->assertEquals([
            '$foo' => [
                'type'        => 'string',
                'description' => 'Test description.',
                'isVariadic'  => false,
                'isReference' => false
            ]
        ], $result['params']);
    }
}
