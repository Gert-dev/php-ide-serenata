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

    public function testParamTagWithAtSymbolIsInterpretedCorrectly()
    {
        $parser = new DocParser();
        $result = $parser->parse('
            /**
             * @param string $foo Test description with @ sign.
             */
        ', [DocParser::PARAM_TYPE], '');

        $this->assertEquals([
            '$foo' => [
                'type'        => 'string',
                'description' => 'Test description with @ sign.',
                'isVariadic'  => false,
                'isReference' => false
            ]
        ], $result['params']);
    }

    public function testCorrectlyProcessesRussianUnicodeSequences()
    {
        $parser = new DocParser();
        $result = $parser->parse('/**
     * @param string|null $someString Имя файла пат
     */', [DocParser::PARAM_TYPE], '');

        $this->assertEquals([
            '$someString' => [
                'type'        => 'string|null',
                'description' => 'Имя файла пат',
                'isVariadic'  => false,
                'isReference' => false
            ]
        ], $result['params']);
    }
}
