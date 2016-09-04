<?php

namespace PhpIntegrator\UserInterface\Command;

use PhpParser\Parser;

/**
 * Trait making a class aware of a parser.
 */
trait ParserAwareTrait
{
    /**
     * @var ParserAwareTrait
     */
    protected $parser;

    /**
     * @param string $code
     *
     * @throws UnexpectedValueException
     *
     * @return \PhpParser\Node[]
     */
    protected function parse($code)
    {
        try {
            $nodes = $this->parser->parse($code);
        } catch (\PhpParser\Error $e) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        if ($nodes === null) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        return $nodes;
    }
}
