<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

/**
 * Command that shows information about the scopes at a specific position in a file.
 */
class AvailableVariables extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('file?', 'The file to examine.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('charoffset?', 'If set, the input offset will be treated as a character offset instead of a byte offset.');
        $optionCollection->add('offset:', 'The character byte offset into the code to use for the determination.')->isa('number');
    }

    /**
     * @inheritDoc
     */
     protected function process(ArrayAccess $arguments)
     {
         if (!isset($arguments['file']) && (!isset($arguments['stdin']) || !$arguments['stdin']->value)) {
             throw new UnexpectedValueException('Either a --file file must be supplied or --stdin must be passed!');
         } elseif (!isset($arguments['offset'])) {
             throw new UnexpectedValueException('An --offset must be supplied into the source code!');
         }

         $code = $this->getSourceCodeHelper()->getSourceCode(
            isset($arguments['file']) ? $arguments['file']->value : null,
            isset($arguments['stdin']) && $arguments['stdin']->value
        );

        $offset = $arguments['offset']->value;

        if (isset($arguments['charoffset']) && $arguments['charoffset']->value == true) {
            $offset = $this->getSourceCodeHelper()->getByteOffsetFromCharacterOffset($offset, $code);
        }

        $result = $this->getAvailableVariables($code, $offset);

        return $this->outputJson(true, $result);
     }

     /**
      * @param string $code
      * @param int    $offset
      */
     public function getAvailableVariables($code, $offset)
     {
         $parser = $this->getParser();

         try {
             $nodes = $parser->parse($code);
         } catch (Error $e) {
             throw new UnexpectedValueException('Parsing the file failed!');
         }

         if ($nodes === null) {
             throw new UnexpectedValueException('Parsing the file failed!');
         }

         $queryingVisitor = new AvailableVariables\QueryingVisitor($offset);
         $scopeLimitingVisitor = new Visitor\ScopeLimitingVisitor($offset);

         $traverser = new NodeTraverser(false);
         $traverser->addVisitor($scopeLimitingVisitor);
         $traverser->addVisitor($queryingVisitor);
         $traverser->traverse($nodes);

         $variables = $queryingVisitor->getVariablesSortedByProximity();

         // We don't do any type resolution at the moment, but we maintain this format for backwards compatibility.
         $outputVariables = [];

         foreach ($variables as $variable) {
             $outputVariables[$variable] = [
                'name' => $variable,
                'type' => null
             ];
         }

         return $outputVariables;
     }
}
