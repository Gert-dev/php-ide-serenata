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

                $args = $this->getMethodArguments($function);

                $result[$function->getName()] = [
                    'name'     => $function->getName(),
                    'isMethod' => true,
                    'args'     => $args
                ];
            }
        }

        return [
            'success' => true,
            'result'  => $result
        ];
    }
}

?>
