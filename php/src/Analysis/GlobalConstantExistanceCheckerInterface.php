<?php

namespace PhpIntegrator\Analysis;

/**
 * Inerface for classes that can check if a global constant exists.
 */
interface GlobalConstantExistanceCheckerInterface
{
    /**
     * @param string $fqcn
     *
     * @return bool
     */
    public function doesGlobalConstantExist($fqcn);
}
