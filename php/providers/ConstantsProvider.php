<?php

namespace PhpIntegrator;

/**
 * Provides global constants. Class constants are not handled by this provider.
 */
class ConstantsProvider extends Tools implements ProviderInterface
{
    /**
     * Execute the command
     * @param  array  $args Arguments gived to the command
     * @return array Response
     */
    public function execute($args = array())
    {
        $constants = array(
            'names'  => array(),
            'values' => array()
        );

        foreach (get_defined_constants(true) as $namespace => $constantList) {
            // We don't want constants from our own code showing up, but we don't select the internal namespace
            // explicitly as there might be installed extensions such as PCRE adding globals as well.
            if ($namespace === 'user') {
                continue;
            }

            // NOTE: Be very careful if you want to pass back the value, there are also escaped paths, newlines
            // (PHP_EOL), etc. in there.
            foreach ($constantList as $constantName => $value) {
                $constants['names'][] = $constantName;
                $constants['values'][$constantName] = array(
                    array(
                        // NOTE: No additional information is available at the moment, but keep the format consistent.
                    )
                );
            }
        }

        return $constants;
    }
}

?>
