<?php

namespace PhpIntegrator;

/**
 * Provides global constants. Class constants are not handled by this provider.
 */
class ConstantsProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [])
    {
        $constants = [];

        foreach (get_defined_constants(true) as $namespace => $constantList) {
            // We don't want constants from our own code showing up, but we don't select the internal namespace
            // explicitly as there might be installed extensions such as PCRE adding globals as well.
            if ($namespace === 'user') {
                continue;
            }

            // NOTE: Be very careful if you want to pass back the value, there are also escaped paths, newlines
            // (PHP_EOL), etc. in there.
            foreach ($constantList as $name => $value) {
                $constants[$name] = [
                    'name' => $name
                ];
            }
        }

        return $constants;
    }
}

?>
