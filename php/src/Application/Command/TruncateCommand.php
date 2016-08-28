<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;

use Doctrine\Common\Cache\ClearableCache;

use GetOptionKit\OptionCollection;

/**
 * Command that truncates the database.
 */
class TruncateCommand extends AbstractCommand
{
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
