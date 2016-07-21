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

        $this->rememberCacheIdForFqcn($cacheId, $fqcn);

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
     * @param string $cacheId
     * @param string $fqcn
     */
    protected function rememberCacheIdForFqcn($cacheId, $fqcn)
    {
        $cacheIdsCacheId = $this->getCacheIdForFqcnListCacheId();

        $tagData = $this->cache->fetch($cacheIdsCacheId);

        $tagData[$cacheId] = $fqcn;

        $this->cache->save($cacheIdsCacheId, $tagData);
    }

    /**
     * @return string[]
     */
    protected function getCacheIdsForFqcn()
    {
        $tagData = $this->cache->fetch($this->getCacheIdForFqcnListCacheId());

        return $tagData ?: [];
    }

    /**
     * @param string $fqcn
     */
    public function clearCacheFor($fqcn)
    {
        foreach ($this->getCacheIdsForFqcn() as $cacheId => $data) {
            if ($fqcn === $data) {
                $this->cache->delete($cacheId);
            }
        }

        $this->cache->delete($this->getCacheIdForFqcnListCacheId());
    }

    /**
     * @return string
     */
    protected function getCacheIdForFqcnListCacheId()
    {
        return __CLASS__ . '_fqcn';
    }
}
