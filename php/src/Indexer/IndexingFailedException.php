<?php

namespace PhpIntegrator\Indexer;

use RuntimeException;

/**
 * Exception that indicates that indexing failed.
 */
class IndexingFailedException extends RuntimeException
{
    /**
     * @var array
     */
    protected $errors;

    /**
     * Constructor.
     *
     * @param array $errors.
     */
    public function __construct(array $errors = [])
    {
        $this->errors = $errors;
    }

    /**
     * Retrieves the currently set errors.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
