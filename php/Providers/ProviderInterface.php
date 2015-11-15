<?php

namespace PhpIntegrator;

/**
 * Interface for providers.
 */
interface ProviderInterface
{
    /**
     * Executes the command.
     *
     * @param array $args
     *
     * @return array
     */
    public function execute(array $args = []);
}
