<?php

namespace PhpIntegrator;

/**
 * Defines functionality that must be exposed by classes that can interact with a storage medium for persisting data
 * related to the index.
 */
interface IndexStorageInterface
{
    /**
     * Retrieves a list of files mapped to their last indexed date (as DateTime).
     *
     * @return array
     */
    public function getFileModifiedMap();

    /**
     * @param int $id
     *
     * @return string|null
     */
    public function getFilePathById($id);

    /**
     * Retrieves the ID of the file with the specified path.
     *
     * @param string $path
     *
     * @return int|null
     */
    public function getFileId($path);

    /**
     * Retrieves the ID of the access modifier with the specified name.
     *
     * @param string $name
     *
     * @return int|null
     */
    public function getAccessModifierId($name);

    /**
     * Retrieves the ID of the structural element type with the specified name.
     *
     * @param string $name
     *
     * @return int|null
     */
    public function getStructuralElementTypeId($name);

    /**
     * Retrieves the ID of the structural element with the specified FQSEN.
     *
     * @param string $fqsen
     *
     * @return int|null
     */
    public function getStructuralElementId($fqsen);

    /**
     * @param int $fileId
     */
    public function deleteFile($fileId);

    /**
     * @param int $fileId
     */
    public function deletePropertiesByFileId($fileId);

    /**
     * @param int $fileId
     */
    public function deleteConstantsByFileId($fileId);

    /**
     * @param int $fileId
     */
    public function deleteFunctionsByFileId($fileId);

    /**
     * @param int $seId
     */
    public function deletePropertiesFor($seId);

    /**
     * @param int $seId
     */
    public function deleteMethodsFor($seId);

    /**
     * @param int $seId
     */
    public function deleteConstantsFor($seId);

    /**
     * @param int $seId
     */
    public function deleteParentLinksFor($seId);

    /**
     * @param int $seId
     */
    public function deleteInterfaceLinksFor($seId);

    /**
     * @param int $seId
     */
    public function deleteTraitLinksFor($seId);

    /**
     * Deletes all structural elements with the specified file ID, except those with the listed ID's.
     *
     * @param int   $fileId
     * @param int[] $excludedIds
     */
    public function deleteExcludedStructuralElementsByFileId($fileId, array $excludedIds);

    /**
     * @return \Traversable
     */
    public function getGlobalConstants();

    /**
     * @return \Traversable
     */
    public function getGlobalFunctions();

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
     * Fetches all index items of the specified type.
     *
     * @param string $indexStorageItem
     *
     * @return \Traversable
     */
    public function getAll($indexStorageItem);
}
