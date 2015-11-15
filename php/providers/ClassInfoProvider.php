<?php

namespace PhpIntegrator;

use ReflectionClass;

class ClassInfoProvider implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [])
    {
        $className = $args[0];

        if (mb_strpos($className, '\\') === 0) {
            $className = mb_substr($className, 1);
        }

        $classInfoFetcher = new ClassInfoFetcher(
            new PropertyInfoFetcher(),
            new MethodInfoFetcher(),
            new ConstantInfoFetcher()
        );

        $reflectionClass = null;

        try {
            $reflectionClass = new ReflectionClass($className);
        } catch (\Exception $e) {

        }

        return [
            'success' => !!$reflectionClass,
            'result'  => $reflectionClass ? $classInfoFetcher->getInfo($reflectionClass) : null
        ];
    }
}
