<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;

use Doctrine\Common\Cache;
use Doctrine\Common\Cache\ClearableCache;

use GetOptionKit\OptionCollection;

/**
 * Command that truncates the database.
 */
class TruncateCommand extends AbstractCommand
{
    /**
     * @var string
     */
    protected $databaseFile;

    /**
     * @var Cache
     */
    protected $cache;



    public function __construct($databaseFile, Cache $cache)
    {
        $this->databaseFile = $databaseFile;
        $this->cache = $cache;
    }
    
    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {

    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        $success = $this->truncate();

        return $this->outputJson($success, []);
    }

    /**
     * @return bool
     */
    public function truncate()
    {
        @unlink($this->databaseFile);

        if ($this->cache instanceof ClearableCache) {
            $this->cache->deleteAll();
        }

        return true;
    }
}
