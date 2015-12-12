<?php

namespace PhpIntegrator;

/**
 * Defines functionality that must be exposed by classes that can interact with a storage medium for persisting data
 * related to the index.
 */
interface IndexStorageInterface
{
    /**
     * Retrieves the ID of the access modifier with the specified name.
     *
     * @return int|null
     */
    public function getAccessModifierid($name);

    /**
     * Retrieves the ID of the structural element type with the specified name.
     *
     * @return int|null
     */
    public function getStructuralElementTypeId($name);

    /**
     * Retrieves the ID of the structural element with the specified FQSEN.
     *
     * @return int|null
     */
    public function getStructuralElementId($fqsen);

    /**
     * Inserts the specified index item into the storage.
     *
     * @param string $indexStorageItem
     * @param array  $data
     *
     * @return int The unique identifier assigned to the inserted data.
     */
    public function insert($indexStorageItem, array $data);
}
