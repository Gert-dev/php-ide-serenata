<?php

namespace PhpIntegrator;

/**
 * Provides information about the docblock for a specific method.
 */
class DocParamProvider implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [])
    {
        $class = $args[0];
        $name  = $args[1];

        if (mb_strpos($class, '\\') == 0) {
            $class = mb_substr($class, 1);
        }

        // TODO: This provider is redundant, we can just fetch method information on the CoffeeScript side instead
        // (which also translates to more caching).

        $test = new ClassInfoProvider();
        $response = $test->execute([$class]);

        if ($response['success']) {
            $info = $response['result'];

            if (isset($info['methods'][$name])) {
                return [
                    'success' => true,
                    'result'  => $info['methods'][$name]['docParameters']
                ];
            }
        }

        return [
            'success' => false,
            'result'  => null
        ];
    }
}

?>
