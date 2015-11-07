<?php

namespace PhpIntegrator;

class FunctionsProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [])
    {
        $result = [];

        $definedFunctions = get_defined_functions();

        foreach ($definedFunctions as $group => $functions) {
            foreach ($functions as $functionName) {
                try {
                    $function = new \ReflectionFunction($functionName);
                } catch (\Exception $e) {
                    continue;
                }

                $result[$function->getName()] = $this->getFunctionInfo($function);

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
