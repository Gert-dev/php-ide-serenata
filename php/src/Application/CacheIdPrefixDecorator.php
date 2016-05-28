<?php

namespace PhpIntegrator\Application;

use Doctrine\Common\Cache\Cache;

/**
 * Decorator for cache objects that will automatically prefix cache ID's with a preconfigured string.
 */
class CacheIdPrefixDecorator implements Cache
{
    /**
     * @var Cache
     */
    protected $decoratedObject;

    /**
     * @var string
     */
    protected $cachePrefix;

    /**
     * @param Cache  $decoratedObject
     * @param string $cachePrefix
     */
    public function __construct(Cache $decoratedObject, $cachePrefix)
    {
        $this->decoratedObject = $decoratedObject;
        $this->cachePrefix = $cachePrefix;
    }

    /**
     * @return string
     */
    public function getCachePrefix()
    {
        return $this->cachePrefix;
    }

    /**
     * @param string $cachePrefix
     *
     * @return static
     */
    public function setCachePrefix($cachePrefix)
    {
        $this->cachePrefix = $cachePrefix;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function fetch($id)
    {
        $id = $this->decorateId($id);

        return $this->decoratedObject->fetch($id);
    }

    /**
     * @inheritDoc
     */
    public function contains($id)
    {
        $id = $this->decorateId($id);

        return $this->decoratedObject->contains($id);
    }

    /**
     * @inheritDoc
     */
    public function save($id, $data, $lifeTime = 0)
    {
        $id = $this->decorateId($id);

        return $this->decoratedObject->save($id, $data, $lifeTime = 0);
    }

    /**
     * @inheritDoc
     */
    public function delete($id)
    {
        $id = $this->decorateId($id);

        return $this->decoratedObject->delete($id);
    }

    /**
     * @inheritDoc
     */
    public function getStats()
    {
        return $this->decoratedObject->getStats();
    }

    /**
     * @param string $id
     *
     * @return string
     */
    protected function decorateId($id)
    {
        return $this->cachePrefix . $id;
    }
}
