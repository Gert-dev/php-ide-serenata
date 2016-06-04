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
     * @param string $fqcn
     *
     * @return array
     */
    public function getDirectStructureInfo($fqcn)
    {
        $parameters = null;

        $cacheId = 'getDirectStructureInfo_' . $fqcn;

        if ($this->cache->contains($cacheId)) {
            $parameters = $this->cache->fetch($cacheId);
        } else {
            $rawInfo = $this->storage->getStructureRawInfo($fqcn);

            if (!$rawInfo) {
                throw new UnexpectedValueException('The structural element "' . $fqcn . '" was not found!');
            }

            $id = $rawInfo['id'];

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
