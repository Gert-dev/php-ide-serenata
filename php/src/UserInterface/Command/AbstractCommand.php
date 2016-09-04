<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use RuntimeException;
use UnexpectedValueException;

use Doctrine\Common\Cache\Cache;

use GetOptionKit\OptionParser;
use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\Relations;
use PhpIntegrator\Analysis\DocblockAnalyzer;
use PhpIntegrator\Analysis\ClasslikeInfoBuilder;
use PhpIntegrator\Analysis\ClasslikeInfoBuilderProviderInterface;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpIntegrator\Analysis\Conversion;
use PhpIntegrator\Parsing\CachingParserProxy;

use PhpIntegrator\UserInterface\ClasslikeInfoBuilderProviderCachingProxy;

use PhpIntegrator\Utility\SourceCodeStreamReader;

use PhpParser\Parser;

/**
 * Base class for commands.
 */
abstract class AbstractCommand implements CommandInterface
{
    /**
     * @inheritDoc
     */
    public function execute(array $processedArguments)
    {
        try {
            return $this->process($processedArguments);
        } catch (UnexpectedValueException $e) {
            return $this->outputJson(false, $e->getMessage());
        }
    }

    /**
     * Sets up command line arguments expected by the command.
     *
     * Operates as a(n optional) template method.
     *
     * @param OptionCollection $optionCollection
     */
    public function attachOptions(OptionCollection $optionCollection)
    {

    }

    /**
     * Executes the actual command and processes the specified arguments.
     *
     * Operates as a template method.
     *
     * @param ArrayAccess $arguments
     *
     * @return string Output to pass back.
     */
    abstract protected function process(ArrayAccess $arguments);

    /**
     * Outputs JSON.
     *
     * @param bool  $success
     * @param mixed $data
     *
     * @throws RuntimeException When the encoding fails, which should never happen.
     *
     * @return string
     */
    protected function outputJson($success, $data)
    {
        $output = json_encode([
            'success' => $success,
            'result'  => $data
        ]);

        if (!$output) {
            $errorMessage = json_last_error_msg() ?: 'Unknown';

            throw new RuntimeException(
                'The encoded JSON output was empty, something must have gone wrong! The error message was: ' .
                '"' .
                $errorMessage .
                '"'
            );
        }

        return $output;
    }
}
