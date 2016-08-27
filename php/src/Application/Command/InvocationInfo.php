<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\SourceCodeHelpers;

use PhpIntegrator\Parsing\PartialParser;

/**
 * Allows fetching invocation information of a method or function call.
 */
class InvocationInfo extends AbstractCommand
{
    /**
     * @var PartialParser
     */
    protected $partialParser;

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
        if (!isset($arguments['offset'])) {
            throw new UnexpectedValueException('An --offset must be supplied into the source code!');
        }

        $code = null;

        if (isset($arguments['stdin']) && $arguments['stdin']->value) {
            $code = $this->getSourceCodeStreamReader()->getSourceCodeFromStdin();
        } elseif (isset($arguments['file']) && $arguments['file']->value) {
            $code = $this->getSourceCodeStreamReader()->getSourceCodeFromFile($arguments['file']);
        } else {
            throw new UnexpectedValueException('Either a --file file must be supplied or --stdin must be passed!');
        }

        $offset = $arguments['offset']->value;

        if (isset($arguments['charoffset']) && $arguments['charoffset']->value == true) {
            $offset = SourceCodeHelpers::getByteOffsetFromCharacterOffset($offset, $code);
        }

        $result = $this->getInvocationInfoAt($code, $offset);

        return $this->outputJson(true, $result);
    }

    /**
     * @param string $code
     * @param int    $offset
     *
     * @return array
     */
    public function getInvocationInfoAt($code, $offset)
    {
        return $this->getInvocationInfo(substr($code, 0, $offset));
    }

    /**
     * @param string $code
     *
     * @return array
     */
    public function getInvocationInfo($code)
    {
        return $this->getPartialParser()->getInvocationInfoAt($code);
    }

    /**
     * Retrieves an instance of PartialParser. The object will only be created once if needed.
     *
     * @return PartialParser
     */
    protected function getPartialParser()
    {
        if (!$this->partialParser instanceof PartialParser) {
            $this->partialParser = new PartialParser();
        }

        return $this->partialParser;
    }
}
