<?php

namespace PhpIntegrator;

use UnexpectedValueException;

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
     * @inheritDoc
     */
    public function getDirectStructureInfo($fqcn)
    {
        $parameters = null;

        // This query is not cached so we can take up the ID in the cache. This prevents the need to clear the cache
        // for a FQCN when it's reindexed as the ID changes every reindex (a further optimization would be to do that
        // instead of using this).
        $rawInfo = $this->storage->getStructureRawInfo($fqcn);

        if (!$rawInfo) {
            throw new UnexpectedValueException('The structural element "' . $fqcn . '" was not found!');
        }

        $id = $rawInfo['id'];

        $cacheId = 'getDirectStructureInfo_' . $fqcn . '_' . $id;

        if ($this->cache->contains($cacheId)) {
            $parameters = $this->cache->fetch($cacheId);
        } else {
            $parameters = [
                $rawInfo,
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
