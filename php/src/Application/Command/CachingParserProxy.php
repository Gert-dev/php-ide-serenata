<?php

namespace PhpIntegrator\Application\Command;

use PhpParser\Parser;

/**
 * Proxy class for a Parser that caches nodes to avoid parsing the same file or source code multiple times.
 *
 * Only the last parsed result is retained. If different code is passed, the cache will miss and a new parse call will
 * occur.
 */
class CachingParserProxy implements Parser
{
    /**
     * @var Parser
     */
    protected $proxiedObject;

    /**
     * @var array|null
     */
    protected $nodesCache = null;

    /**
     * @var array|null
     */
    protected $errorsCache = null;

    /**
     * @var string|null
     */
    protected $lastCacheKey = null;

    /**
     * @param Parser $proxiedObject
     */
    public function __construct(Parser $proxiedObject)
    {
        $this->proxiedObject = $proxiedObject;
    }

    /**
     * @inheritDoc
     */
    public function parse($code)
    {
        $cacheKey = md5($code);

        if ($cacheKey !== $this->lastCacheKey || $this->nodesCache === null) {
            $this->nodesCache = $this->proxiedObject->parse($code);
            $this->errorsCache = $this->proxiedObject->getErrors();
        }

        $this->lastCacheKey = $cacheKey;

        return $this->nodesCache;
    }

    /**
     * @inheritDoc
     */
    public function getErrors()
    {
        return $this->errorsCache !== null ? $this->errorsCache : $this->proxiedObject->getErrors();
    }
}
