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
        $cacheMap = $this->getCacheMap();
        $cacheMap[$fqcn][$cacheId] = true;

        $this->saveCacheMap($cacheMap);
    }

    /**
     * @param string $fqcn
     */
    public function clearCacheFor($fqcn)
    {
        $cacheMap = $this->getCacheMap();

        if (isset($cacheMap[$fqcn])) {
            foreach ($cacheMap[$fqcn] as $cacheId => $ignoredValue) {
                $this->cache->delete($cacheId);
            }

            unset($cacheMap[$fqcn]);

            $this->saveCacheMap($cacheMap);
        }
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
     * @return string
     */
    protected function getCacheIdForFqcnListCacheId()
    {
        return __CLASS__ . '_fqcn';
    }
}
