<?php

namespace PhpIntegrator\Application\Command\DeduceType;

use ArrayIterator;

use PhpIntegrator\IndexDataAdapter\ProviderInterface;

/**
 * Proxy for a ProviderInterface that limits data fetching to improve performance.
 */
class IndexDataAdapterProvider implements ProviderInterface
{
    /**
     * @var ProviderInterface
     */
    protected $proxiedObject;

    /**
    * @var string|null
    */
    protected $memberFilter;

    /**
     * Constructor.
     *
     * @param ProviderInterface $proxiedObject
     * @param string|null       $memberFilter
     */
    public function __construct(ProviderInterface $proxiedObject, $memberFilter = null)
    {
        $this->memberFilter = $memberFilter;
        $this->proxiedObject = $proxiedObject;
    }

    /**
     * Retrieves the currently set memberFilter.
     *
     * @return string|null
     */
    public function getMemberFilter()
    {
        return $this->memberFilter;
    }

    /**
     * Sets the memberFilter to use.
     *
     * @param string|null $memberFilter
     *
     * @return $this
     */
    public function setMemberFilter($memberFilter)
    {
        $this->memberFilter = $memberFilter;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawInfo($id)
    {
        return $this->proxiedObject->getStructureRawInfo($id);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawParents($id)
    {
        return $this->proxiedObject->getStructureRawParents($id);
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
        return $this->proxiedObject->getStructureRawInterfaces($id);
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
        return $this->proxiedObject->getStructureRawTraits($id);
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
        $filteredData = [];
        $data = $this->proxiedObject->getStructureRawConstants($id);

        foreach ($data as $row) {
            if ($row['name'] !== $this->memberFilter) {
                continue;
            }

            $filteredData[] = $row;
        }

        return new ArrayIterator($filteredData);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawProperties($id)
    {
        $filteredData = [];
        $data = $this->proxiedObject->getStructureRawProperties($id);

        foreach ($data as $row) {
            if ($row['name'] !== $this->memberFilter) {
                continue;
            }

            $filteredData[] = $row;
        }

        return new ArrayIterator($filteredData);
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawMethods($id)
    {
        $filteredData = [];
        $data = $this->proxiedObject->getStructureRawMethods($id);

        foreach ($data as $row) {
            if ($row['name'] !== $this->memberFilter) {
                continue;
            }

            $filteredData[] = $row;
        }

        return new ArrayIterator($filteredData);
    }

    /**
     * @inheritDoc
     */
    public function getStructureTraitAliasesAssoc($id)
    {
        return $this->proxiedObject->getStructureTraitAliasesAssoc($id);
    }

    /**
     * @inheritDoc
     */
    public function getStructureTraitPrecedencesAssoc($id)
    {
        return $this->proxiedObject->getStructureTraitPrecedencesAssoc($id);
    }
}
