<?php

namespace PhpIntegrator;

use Doctrine\Common\Cache\Cache;

/**
 * Index data adapter that also caches its results.
 */
class CachingIndexDataAdapter extends IndexDataAdapter
{
    /**
     * @var Cache
     */
    protected $cache;

    /**
     * Constructor.
     *
     * @param IndexDataAdapter\ProviderInterface $storage
     * @param Cache                              $cache
     */
    public function __construct(IndexDataAdapter\ProviderInterface $storage, Cache $cache)
    {
        parent::__construct($storage);

        $this->cache = $cache;
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function getDirectStructureInfo($id)
    {
        $parameters = null;

        $cacheId = 'getDirectStructureInfo_' . $id;

        if ($this->cache->contains($cacheId)) {
            $parameters = $this->cache->fetch($cacheId);
        } else {
            $parameters = [
                $this->storage->getStructureRawInfo($id),
                $this->storage->getStructureRawParents($id),
                $this->storage->getStructureRawChildren($id),
                $this->storage->getStructureRawInterfaces($id),
                $this->storage->getStructureRawImplementors($id),
                $this->storage->getStructureRawTraits($id),
                $this->storage->getStructureRawTraitUsers($id),
                $this->storage->getStructureRawConstants($id),
                $this->storage->getStructureRawProperties($id),
                $this->storage->getStructureRawMethods($id)
            ];

            $this->cache->save($cacheId, $parameters);
        }

        return call_user_func_array([$this, 'resolveStructure'], $parameters);
    }
}
