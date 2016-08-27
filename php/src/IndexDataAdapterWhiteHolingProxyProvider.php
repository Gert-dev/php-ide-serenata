<?php

namespace PhpIntegrator;

use PhpIntegrator\IndexDataAdapterProviderInterface;

/**
 * Proxy for a {@see IndexDataAdapterProviderInterface} that does not return any data (is a "white hole") for several
 * methods that are unnecessary when fetching the structural element list to avoid their cost and to improve performance.
 */
class IndexDataAdapterWhiteHolingProxyProvider implements IndexDataAdapterProviderInterface
{
    /**
     * @var IndexDataAdapterProviderInterface
     */
    protected $proxiedObject;

    /**
    * @var array|null
    */
    protected $structureRawInfo = null;

    /**
     * Constructor.
     *
     * @param IndexDataAdapterProviderInterface $proxiedObject
     */
    public function __construct(IndexDataAdapterProviderInterface $proxiedObject)
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
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawChildren($id)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawInterfaces($id)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawImplementors($id)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawTraits($id)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawTraitUsers($id)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawConstants($id)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawProperties($id)
    {
        return [];
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
