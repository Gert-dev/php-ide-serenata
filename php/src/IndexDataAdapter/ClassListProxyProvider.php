<?php

namespace PhpIntegrator\IndexDataAdapter;

use ArrayIterator;

/**
 * Proxy for a ProviderInterface that does not return any data (is a "white hole") for several methods that are
 * unnecessary when fetching the structural element list to avoid their cost and to improve performance.
 */
class ClassListProxyProvider implements ProviderInterface
{
    /**
     * @var ProviderInterface
     */
    protected $proxiedObject;

    /**
    * @var array|null
    */
    protected $structuralElementRawInfo = null;

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
     * Sets the data to return for the {@see getStructuralElementRawInfo} call. If set to null (the default), that call
     * will proxy the method from the proxied object as usual.
     *
     * Can be used to avoid performing an additional proxy call to improve performance or just to override the returned
     * data.
     *
     * @param array|null
     *
     * @return $this
     */
    public function setStructuralElementRawInfo(array $rawInfo = null)
    {
        $this->structuralElementRawInfo = $rawInfo;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawInfo($id)
    {
        return ($this->structuralElementRawInfo !== null) ?
            $this->structuralElementRawInfo :
            $this->proxiedObject->getStructuralElementRawInfo($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawParents($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawInterfaces($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawTraits($id)
    {
        // return $this->proxiedObject->getStructuralElementRawTraits($id);
        return new ArrayIterator([]);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawConstants($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawProperties($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementRawMethods($id)
    {
        /*$methods = $this->proxiedObject->getStructuralElementRawMethods($id);

        $filteredMethods = [];

        foreach ($methods as $method) {
            if ($method['name'] === '__construct') {
                $filteredMethods[] = $method;
                break;
            }
        }

        return new \ArrayIterator($filteredMethods);*/

        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementTraitAliasesAssoc($id)
    {
        // return $this->proxiedObject->getStructuralElementTraitAliasesAssoc($id);
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementTraitPrecedencesAssoc($id)
    {
        // return $this->proxiedObject->getStructuralElementTraitPrecedencesAssoc($id);
        return [];
    }
}
