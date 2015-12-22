<?php

namespace PhpIntegrator\IndexDataAdapter;

/**
 * Proxy for a ProviderInterface that does not return any data (is a "white hole") when it comes to data related to
 * constants and properties and only provides the constructor method when methods are requested.
 *
 * This proxy is specifically used to avoid fetching unnecessary information to improve performance.
 */
class ClassListProxyProvider implements ProviderInterface
{
    /**
     * @var ProviderInterface
     */
    protected $proxiedObject;

    /**
     * Constructor.
     *
     * @param ProviderInterface $proxiedObject
     */
    public function __construct(ProviderInterface $proxiedObject)
    {
        $this->proxiedObject = $proxiedObject;
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawInfo($id)
    {
        return $this->proxiedObject->getStructuralElementRawInfo($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawInterfaces($id)
    {
        return $this->proxiedObject->getStructuralElementRawInterfaces($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawTraits($id)
    {
        return $this->proxiedObject->getStructuralElementRawTraits($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawConstants($id)
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawProperties($id)
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawMethods($id)
    {
        $methods = $this->proxiedObject->getStructuralElementRawMethods($id);

        $filteredMethods = [];

        foreach ($methods as $method) {
            if ($method['name'] === '__construct') {
                $filteredMethods[] = $method;
                break;
            }
        }

        return new \ArrayIterator($filteredMethods);
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctionParameters($functionId)
    {
        return $this->proxiedObject->getFunctionParameters($functionId);
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctionThrows($functionId)
    {
        return $this->proxiedObject->getFunctionThrows($functionId);
    }

    /**
     * {@inheritDoc}
     */
    public function getParentFqsens($seId)
    {
        return $this->proxiedObject->getParentFqsens($seId);
    }
}
