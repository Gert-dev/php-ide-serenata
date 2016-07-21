<?php

namespace PhpIntegrator\IndexDataAdapter;

use Doctrine\Common\Cache\Cache;

/**
 * Proxy for providers that introduces a caching layer.
 */
class ProviderCachingProxy implements ProviderInterface
{
    /**
     * @var ProviderInterface
     */
    protected $provider;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @param ProviderInterface $provider
     * @param Cache             $cache
     */
    public function __construct(ProviderInterface $provider, Cache $cache)
    {
        $this->provider = $provider;
        $this->cache = $cache;
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawInfo($fqcn)
    {
        $cacheId = $this->getCacheId(__FUNCTION__, func_get_args());

        $data = $this->proxyCall(__FUNCTION__, func_get_args());

        $this->rememberCacheIdForFqcn($fqcn, $cacheId);

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawParents($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawChildren($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawInterfaces($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawImplementors($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawTraits($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawTraitUsers($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawConstants($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawProperties($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawMethods($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureTraitAliasesAssoc($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getStructureTraitPrecedencesAssoc($id)
    {
        return $this->proxyCall(__FUNCTION__, func_get_args());
    }

    /**
     * @param mixed $method
     * @param array $arguments
     *
     * @return mixed
     */
    protected function proxyCall($method, array $arguments)
    {
        $cacheId = $this->getCacheId($method, $arguments);

        if ($this->cache->contains($cacheId)) {
            return $this->cache->fetch($cacheId);
        }

        $data = call_user_func_array([$this->provider, $method], $arguments);

        $this->cache->save($cacheId, $data);

        return $data;
    }

    /**
     * @param string $method
     * @param array  $arguments
     */
    protected function getCacheId($method, array $arguments)
    {
        return $method . '_' . serialize($arguments);
    }

    /**
     * @param string $fqcn
     * @param string $cacheId
     */
    protected function rememberCacheIdForFqcn($fqcn, $cacheId)
    {
        $this->synchronized(function () use ($fqcn, $cacheId) {
            $cacheIdsCacheId = $this->getCacheIdForFqcnListCacheId();

            $cachedMap = $this->cache->fetch($cacheIdsCacheId);
            $cachedMap[$fqcn][$cacheId] = true;

            $this->cache->save($cacheIdsCacheId, $cachedMap);
        });
    }

    /**
     * @param string $fqcn
     */
    public function clearCacheFor($fqcn)
    {
        $this->synchronized(function () use ($fqcn) {
            $cacheIdsCacheId = $this->getCacheIdForFqcnListCacheId();

            $cachedMap = $this->cache->fetch($cacheIdsCacheId);

            if (isset($cachedMap[$fqcn])) {
                foreach ($cachedMap[$fqcn] as $cacheId => $ignoredValue) {
                    $this->cache->delete($cacheId);
                }

                unset($cachedMap[$fqcn]);

                $this->cache->save($cacheIdsCacheId, $cachedMap);
            }
        });
    }

    /**
     * Executes the specified callback in a "synchronized" way. A shared lock file is created to ensure that all
     * processes executing the code must first wait for the (exclusive) lock.
     *
     * The cache map used in this class is used to maintain a list of which FQCN's relate to which cache ID's. This is
     * necessary because the Doctrine cache has no notion of cache tags or tagging, so there is no way to delete all
     * cache files associated with a certain FQCN. To solve this problem, a shared cache map is maintained (which is
     * also stored in the cache) that keeps track of this list and replaces this missing tagging functionality.
     *
     * If multiple processes are active, they are all accessing the same shared cache map (some may be trying to read
     * it, others may be trying to update it). For this reason, locking is used to ensure each process patiently awaits
     * their turn.
     *
     * Windows seems to have an additional amount of trouble with this, see also
     * https://github.com/Gert-dev/php-integrator-base/issues/185
     *
     * @param callable $callback
     */
    protected function synchronized($callback)
    {
        $handle = fopen($this->getLockFile(), "w");
        flock($handle, LOCK_EX);

        $callback();

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * @return string
     */
    protected function getLockFile()
    {
        return sys_get_temp_dir() . '/php-integrator-base/accessing_shared_cache.lock';
    }

    /**
     * @return string
     */
    protected function getCacheIdForFqcnListCacheId()
    {
        return __CLASS__ . '_fqcn';
    }
}
