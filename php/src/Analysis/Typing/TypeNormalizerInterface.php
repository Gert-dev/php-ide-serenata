<?php

namespace PhpIntegrator\Analysis\Typing;

/**
 * Normalizes types and FQCN's.
 */
interface TypeNormalizerInterface
{
    /**
     * Normalizes an FQCN, consistently ensuring there is a leading slash.
     *
     * @param string $fqcn
     *
     * @return string
     */
    public function getNormalizedFqcn($fqcn);
}
