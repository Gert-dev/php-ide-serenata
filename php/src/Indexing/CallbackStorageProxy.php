<?php

namespace PhpIntegrator\Indexing;

/**
 * Proxy for classes implementing {@see StorageInterface} that will invoke callback functions when specific methods are
 * called.
 */
class CallbackStorageProxy implements StorageInterface
{
    /**
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var callable
     */
    protected $insertStructureCallback;

    /**
     * @param StorageInterface $storage
     * @param callable         $insertStructureCallback
     */
    public function __construct(StorageInterface $storage, callable $insertStructureCallback)
    {
        $this->storage = $storage;
        $this->insertStructureCallback = $insertStructureCallback;
    }

    /**
     * @inheritDoc
     */
    public function getFileModifiedMap()
    {
        return $this->storage->getFileModifiedMap();
    }

    /**
     * @inheritDoc
     */
    public function getAccessModifierMap()
    {
        return $this->storage->getAccessModifierMap();
    }

    /**
     * @inheritDoc
     */
    public function getStructureTypeMap()
    {
        return $this->storage->getStructureTypeMap();
    }

    /**
     * @inheritDoc
     */
    public function getFileId($path)
    {
        return $this->storage->getFileId($path);
    }

    /**
     * @inheritDoc
     */
    public function deleteFile($path)
    {
        return $this->storage->deleteFile($path);
    }

    /**
     * @inheritDoc
     */
    public function getSetting($name)
    {
        return $this->storage->getSetting($name);
    }

    /**
     * @inheritDoc
     */
    public function insertStructure(array $data)
    {
        $callback = $this->insertStructureCallback;
        $callback($data['fqcn']);

        return $this->storage->insertStructure($data);
    }

    /**
     * @inheritDoc
     */
    public function insert($indexStorageItem, array $data)
    {
        return $this->storage->insert($indexStorageItem, $data);
    }

    /**
     * @inheritDoc
     */
    public function update($indexStorageItem, $id, array $data)
    {
        return $this->storage->update($indexStorageItem, $id, $data);
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction()
    {
        $this->storage->beginTransaction();
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction()
    {
        $this->storage->commitTransaction();
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction()
    {
        $this->storage->rollbackTransaction();
    }
}
