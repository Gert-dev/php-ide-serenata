<?php

namespace PhpIntegrator\Analysis;

/**
 * Inerface for classes that can check if a classlike exists.
 */
interface ClasslikeExistanceCheckerInterface
{
    /**
     * @param string $fqcn
     *
     * @return bool
     */
    public function doesClassExist($fqcn);
}
