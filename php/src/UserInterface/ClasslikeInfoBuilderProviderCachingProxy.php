<?php

namespace PhpIntegrator\UserInterface;

use Doctrine\Common\Cache\Cache;

use PhpIntegrator\Analysis\ClasslikeInfoBuilderProviderInterface;

/**
 * Proxy for providers that introduces a caching layer.
 */
class ClasslikeInfoBuilderProviderCachingProxy implements ClasslikeInfoBuilderProviderInterface
{
    /**
     * @var ClasslikeInfoBuilderProviderInterface
     */
    protected $provider;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @param ClasslikeInfoBuilderProviderInterface $provider
     * @param Cache             $cache
     */
    public function __construct(ClasslikeInfoBuilderProviderInterface $provider, Cache $cache)
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
        return $this->synchronized(function () use ($method, $arguments) {
            $cacheId = $this->getCacheId($method, $arguments);

            if ($this->cache->contains($cacheId)) {
                return $this->cache->fetch($cacheId);
            }

            $data = call_user_func_array([$this->provider, $method], $arguments);

            $this->cache->save($cacheId, $data);

            return $data;
        });
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
            $cacheMap = $this->getCacheMap();
            $cacheMap[$fqcn][$cacheId] = true;

            $this->saveCacheMap($cacheMap);
        });
    }

    /**
     * @param string $fqcn
     */
    public function clearCacheFor($fqcn)
    {
        $this->synchronized(function () use ($fqcn) {
            $cacheMap = $this->getCacheMap();

            if (isset($cacheMap[$fqcn])) {
                foreach ($cacheMap[$fqcn] as $cacheId => $ignoredValue) {
                    $this->cache->delete($cacheId);
                }

                unset($cacheMap[$fqcn]);

                $this->saveCacheMap($cacheMap);
            }
        });
    }

    /**
     * @return array
     */
    protected function getCacheMap()
    {
        $cacheIdsCacheId = $this->getCacheIdForFqcnListCacheId();

        // The silence operator isn't actually necessary, except on Windows. In some rare situations, it will complain
        // with a "permission denied" error on the shared cache map file (locking it has no effect either). Usually,
        // however, it will work fine on Windows as well. This way at least these users enjoy caching somewhat instead
        // of having no caching at all. See also https://github.com/Gert-dev/php-integrator-base/issues/185 .
        $cacheMap = @$this->cache->fetch($cacheIdsCacheId);

        return $cacheMap ?: [];
    }

    /**
     * @param array $cacheMap
     */
    protected function saveCacheMap(array $cacheMap)
    {
        $cacheIdsCacheId = $this->getCacheIdForFqcnListCacheId();

        // Silenced for the same reason as above.
        @$this->cache->save($cacheIdsCacheId, $cacheMap);
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
     *
     * @return mixed Whatever the callback returns.
     */
    protected function synchronized($callback)
    {
        $lockFileName = $this->getLockFileName();

        $this->ensurePathExists(dirname($lockFileName));

        $handle = fopen($lockFileName, "w");
        flock($handle, LOCK_EX);

        $result = $callback();

        flock($handle, LOCK_UN);
        fclose($handle);

        return $result;
    }

    /**
     * @return string
     */
    protected function getLockFileName()
    {
        return sys_get_temp_dir() . '/php-integrator-base/accessing_shared_cache.lock';
    }

    /**
     * @param string $path
     */
    protected function ensurePathExists($path)
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

    /**
     * @return string
     */
    protected function getCacheIdForFqcnListCacheId()
    {
        return __CLASS__ . '_fqcn';
    }
}
