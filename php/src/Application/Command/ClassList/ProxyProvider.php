<?php

namespace PhpIntegrator\Application\Command\ClassList;

use ArrayIterator;

use PhpIntegrator\IndexDataAdapter\ProviderInterface;

/**
 * Proxy for a ProviderInterface that does not return any data (is a "white hole") for several methods that are
 * unnecessary when fetching the structural element list to avoid their cost and to improve performance.
 */
class ProxyProvider implements ProviderInterface
{
    /**
     * @var ProviderInterface
     */
    protected $proxiedObject;

    /**
    * @var array|null
    */
    protected $structureRawInfo = null;

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
     * Sets the data to return for the {@see getStructureRawInfo} call. If set to null (the default), that call
     * will proxy the method from the proxied object as usual.
     *
     * Can be used to avoid performing an additional proxy call to improve performance or just to override the returned
     * data.
     *
     * @param array|null
     *
     * @return $this
     */
    public function setStructureRawInfo(array $rawInfo = null)
    {
        $this->structureRawInfo = $rawInfo;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawInfo($id)
    {
        return ($this->structureRawInfo !== null) ?
            $this->structureRawInfo :
            $this->proxiedObject->getStructureRawInfo($id);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawParents($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawChildren($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawInterfaces($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawImplementors($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawTraits($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawTraitUsers($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawConstants($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawProperties($id)
    {
        return new ArrayIterator([]);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawMethods($id)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureTraitAliasesAssoc($id)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureTraitPrecedencesAssoc($id)
    {
        return [];
    }
}
