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
            // NOTE: Be very careful if you want to pass back the value, there are also escaped paths, newlines
            // (PHP_EOL), etc. in there.
            foreach ($constantList as $name => $value) {
                $constants[$name] = $this->getConstantInfo($name);
                $constants[$name]['isBuiltin'] = ($namespace !== 'user');
            }
        }

        return [
            'success' => true,
            'result'  => $constants
        ];
    }
}

?>
