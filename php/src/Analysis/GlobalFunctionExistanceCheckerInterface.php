<?php

namespace PhpIntegrator\Analysis;

/**
 * Inerface for classes that can check if a global function exists.
 */
interface GlobalFunctionExistanceCheckerInterface
{
    /**
     * @param string $fqcn
     *
     * @return bool
     */
    public function doesGlobalFunctionExist($fqcn);
}
