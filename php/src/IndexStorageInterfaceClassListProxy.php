<?php

namespace PhpIntegrator;

/**
 * Proxy for a IndexStorageInterface that does not return any data (is a "white hole") when it comes to data related to
 * constants and properties and only provides the constructor method when methods are requested.
 *
 * This proxy is specifically used to avoid fetching unnecessary information to improve performance.
 */
class IndexStorageInterfaceClassListProxy implements IndexStorageInterface
{
    /**
     * @var IndexStorageInterface
     */
    protected $proxiedObject;

    /**
     * Constructor.
     *
     * @param IndexStorageInterface $proxiedObject
     */
    public function __construct(IndexStorageInterface $proxiedObject)
    {
        $this->proxiedObject = $proxiedObject;
    }

    /**
     * {@inheritDoc}
     */
    public function getFileModifiedMap()
    {
        return $this->proxiedObject->getFileModifiedMap();
    }

    /**
     * {@inheritDoc}
     */
    public function getFilePathById($id)
    {
        return $this->proxiedObject->getFilePathById($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getFileId($path)
    {
        return $this->proxiedObject->getFileId($path);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessModifierId($name)
    {
        return $this->proxiedObject->getAccessModifierId($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementTypeId($name)
    {
        return $this->proxiedObject->getStructuralElementTypeId($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementId($fqsen)
    {
        return $this->proxiedObject->getStructuralElementId($fqsen);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteFile($fileId)
    {
        return $this->proxiedObject->deleteFile($fileId);
    }

    /**
     * {@inheritDoc}
     */
    public function deletePropertiesByFileId($fileId)
    {
        return $this->proxiedObject->deletePropertiesByFileId($fileId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteConstantsByFileId($fileId)
    {
        return $this->proxiedObject->deleteConstantsByFileId($fileId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteFunctionsByFileId($fileId)
    {
        return $this->proxiedObject->deleteFunctionsByFileId($fileId);
    }

    /**
     * {@inheritDoc}
     */
    public function deletePropertiesFor($seId)
    {
        return $this->proxiedObject->deletePropertiesFor($seId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMethodsFor($seId)
    {
        return $this->proxiedObject->deleteMethodsFor($seId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteConstantsFor($seId)
    {
        return $this->proxiedObject->deleteConstantsFor($seId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteParentLinksFor($seId)
    {
        return $this->proxiedObject->deleteParentLinksFor($seId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteInterfaceLinksFor($seId)
    {
        return $this->proxiedObject->deleteInterfaceLinksFor($seId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteTraitLinksFor($seId)
    {
        return $this->proxiedObject->deleteTraitLinksFor($seId);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteExcludedStructuralElementsByFileId($fileId, array $excludedIds)
    {
        return $this->proxiedObject->deleteExcludedStructuralElementsByFileId($fileId, $excludedIds);
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
    public function getParentFqsens($seId)
    {
        return $this->proxiedObject->getParentFqsens($seId);
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
    public function getGlobalConstants()
    {
        return $this->proxiedObject->getGlobalConstants();
    }

    /**
     * {@inheritDoc}
     */
    public function getGlobalFunctions()
    {
        return $this->proxiedObject->getGlobalFunctions();
    }

    /**
     * {@inheritDoc}
     */
    public function insert($indexStorageItem, array $data)
    {
        return $this->proxiedObject->insert($indexStorageItem, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function update($indexStorageItem, $id, array $data)
    {
        return $this->proxiedObject->update($indexStorageItem, $id, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function getAll($indexStorageItem)
    {
        return $this->proxiedObject->getAll($indexStorageItem);
    }
}
