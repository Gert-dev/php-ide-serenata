<?php

namespace PhpIntegrator;

use ReflectionMethod;

/**
 * Fetches information about class methods.
 */
class MethodInfoFetcher extends FunctionInfoFetcher implements InfoFetcherInterface
{
    use MemberInfoFetcherTrait;

    /**
     * Retrieves information about what interface the specified member method is implementind, if any.
     *
     * @param ReflectionMethod $reflectionMember
     *
     * @return array|null
     */
    protected function getImplementationInfo(ReflectionMethod $reflectionMember)
    {
        $implementedMember = null;
        $methodName = $reflectionMember->getName();

        foreach ($reflectionMember->getDeclaringClass()->getInterfaces() as $interface) {
            if ($interface->hasMethod($methodName)) {
                $implementedMember = $interface->getMethod($methodName);
                break;
            }
        }

        if (!$implementedMember) {
            return null;
        }

        return [
            'declaringClass'     => $this->getDeclaringClass($implementedMember),
            'declaringStructure' => $this->getDeclaringStructure($implementedMember),
            'startLine'          => $implementedMember->getStartLine()
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createDefaultInfo(array $options)
    {
        return parent::createDefaultInfo(array_merge([
            'override'           => null,
            'implementation'     => null,

            'isMagic'            => false,

            'isPublic'           => true,
            'isProtected'        => false,
            'isPrivate'          => false,
            'isStatic'           => false,

            'declaringClass'     => null,
            'declaringStructure' => null
        ], $options));
    }

    /**
     * Retrieves a data structure containing information about the specified method, expanding upon
     * {@see getFunctionInfo} to provide additional information.
     *
     * @param ReflectionMethod $method
     *
     * @return array
     */
    public function getInfo($method)
    {
        if (!$method instanceof ReflectionMethod) {
            throw new \InvalidArgumentException("The passed argument is not of the correct type!");
        }

        $data = array_merge(parent::getInfo($method), [
            'override'           => $this->getOverrideInfo($method),
            'implementation'     => $this->getImplementationInfo($method),

            'isMagic'            => false,

            'isPublic'           => $method->isPublic(),
            'isProtected'        => $method->isProtected(),
            'isPrivate'          => $method->isPrivate(),
            'isStatic'           => $method->isStatic(),

            'declaringClass'     => $this->getDeclaringClass($method),
            'declaringStructure' => $this->getDeclaringStructure($method)
        ]);

        // Determine this again as members can return types such as 'static', which requires the declaring class, which
        // was not available yet before.
        $data['return']['resolvedType'] = $this->determineFullReturnType($data);

        return $data;
    }
}
