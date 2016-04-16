<?php

namespace PhpIntegrator\Indexer;

/**
 * Defines functionality that must be exposed by classes that can interact with a storage medium for persisting data
 * related to the index.
 */
interface StorageInterface
{
    /**
     * Retrieves a list of files mapped to their last indexed date (as DateTime).
     *
     * @return array
     */
    public function getFileModifiedMap();

    /**
    * Retrieves a list of access modifiers mapped to their ID.
    *
    * @return array
    */
    public function getAccessModifierMap();

     /**
     * Retrieves a list of structural element types mapped to their ID.
     *
     * @return array
     */
    public function getStructureTypeMap();

    /**
     * Retrieves the ID of the file with the specified path.
     *
     * @param string $path
     *
     * @return int|null
     */
    public function getFileId($path);

    /**
     * Retrieves the ID of the structural element with the specified FQSEN.
     *
     * @param string $fqsen
     *
     * @return int|null
     */
    public function getStructureId($fqsen);

    /**
     * @param string $path
     */
    public function deleteFile($path);

    /**
     * @param string $fqsen
     */
    public function deleteStructure($fqsen);

    /**
     * Inserts the specified index item into the storage.
     *
     * @param string $indexStorageItem
     * @param array  $data
     *
     * @return int The unique identifier assigned to the inserted data.
     */
    public function insert($indexStorageItem, array $data);

    /**
     * Updates the specified index item.
     *
     * @param string    $indexStorageItem
     * @param int|array $id
     * @param array     $data
     */
    public function update($indexStorageItem, $id, array $data);

    /**
     * @inheritDoc
     */
    public function beginTransaction();

    /**
     * @inheritDoc
     */
    public function commitTransaction();

    /**
     * @inheritDoc
     */
    public function rollbackTransaction();
}
