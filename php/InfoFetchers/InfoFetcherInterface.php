<?php

namespace PhpIntegrator;

use ReflectionClass;

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
     * @param mixed                $item
     * @param ReflectionClass|null $class
     *
     * @return array
     */
    public function getInfo($item, ReflectionClass $class = null);
}
