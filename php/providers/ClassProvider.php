<?php

namespace PhpIntegrator;

class ClassProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute($args = [])
    {
        $class      = $args[0];
        $isInternal = isset($args[1]) ? $args[1] : false;

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        if ($isInternal && !$reflection->isInternal()) {
            return [];
        }

        $constructor = $reflection->getConstructor();

        return [
            'class' => $this->getClassArguments($reflection),
            'methods' => [
                'constructor' => [
                    'has'  => !!$constructor,
                    'args' => $constructor ? $this->getMethodArguments($constructor) : []
                ]
            ]
        ];
    }
}

?>
