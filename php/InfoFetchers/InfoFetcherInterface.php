<?php

namespace PhpIntegrator;

/**
 * Interface for information fetchers.
 */
interface InfoFetcherInterface
{
    /**
     * Fetches information about the specified item.
     *
     * @param mixed $item
     *
     * @return array
     */
    public function getInfo($item);
}
