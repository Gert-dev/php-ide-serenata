<?php

namespace PhpIntegrator;

class FunctionsProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute($args = [])
    {
        $functions = [];

        $definedFunctions = get_defined_functions();

        foreach ($definedFunctions['internal'] as $functionName) {
            try {
                $function = new \ReflectionFunction($functionName);
            } catch (\Exception $e) {
                continue;
            }

            $args = $this->getMethodArguments($function);

            $functions[$function->getName()] = [
                'name'     => $function->getName(),
                'isMethod' => true,
                'args'     => $args
            ];
        }

        return $functions;
    }
}

?>
