<?php

namespace PhpIntegrator;

class FunctionsProvider extends Tools implements ProviderInterface
{
    /**
     * Execute the command
     * @param  array  $args Arguments gived to the command
     * @return array Response
     */
    public function execute($args = array())
    {
        $functions = array(
            'names'  => array(),
            'values' => array()
        );

        $functions = get_defined_functions();

        foreach ($functions['internal'] as $functionName) {
            try {
                $function = new \ReflectionFunction($functionName);
            } catch (\Exception $e) {
                continue;
            }

            $functions['names'][] = $function->getName();

            $args = $this->getMethodArguments($function);

            $functions['values'][$function->getName()] = array(
                array(
                    'isMethod' => true,
                    'args'     => $args
                )
            );
        }

        return $functions;
    }
}

?>
