<?php

namespace PhpIntegrator;

use ReflectionFunction;

/**
 * Provides global functions. Class methods are not handled by this provider.
 */
class FunctionsProvider implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [])
    {
        $result = [];

        $definedFunctions = get_defined_functions();
        $functionInfoFetcher = new FunctionInfoFetcher();

        foreach ($definedFunctions as $group => $functions) {
            foreach ($functions as $functionName) {
                try {
                    $function = new ReflectionFunction($functionName);
                } catch (\Exception $e) {
                    continue;
                }

                $result[$function->getName()] = $functionInfoFetcher->getInfo($function);

                if ($group === 'internal') {
                    // PHP's built-in functions don't have docblocks, so per exception, this doesn't mean they return
                    // void.
                    $result[$function->getName()]['args']['return']['type'] = '';
                }
            }
        }

        return [
            'success' => true,
            'result'  => $result
        ];
    }
}

?>
