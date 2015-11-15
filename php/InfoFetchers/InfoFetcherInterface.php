<?php

namespace PhpIntegrator;

/**
 * Interface for information fetchers.
 */
interface InfoFetcherInterface
{
    /**
     * Retrieves a data structure filled with keys relevant for the item, but with defaults as values.
     *
     * @param array $options
     *
     * @return array
     */
    public function createDefaultInfo(array $options);

    /**
     * Fetches information about the specified item.
     *
     * @param mixed $item
     *
     * @return array
     */
    public function getInfo($item);
}
