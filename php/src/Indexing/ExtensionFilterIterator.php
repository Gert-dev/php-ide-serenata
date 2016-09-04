<?php

namespace PhpIntegrator\Indexing;

use Iterator;
use FilterIterator;

/**
 * Filters out {@see \DirectoryIterator} values that don't match any of the specified extensions.
 */
class ExtensionFilterIterator extends FilterIterator
{
    /**
     * @var string[]
     */
    protected $allowedExtensions;

    /**
     * @param Iterator $iterator
     * @param string[] $allowedExtensions
     */
    public function __construct(Iterator $iterator, array $allowedExtensions)
    {
        parent::__construct($iterator);

        $this->allowedExtensions = $allowedExtensions;
    }

    /**
     * @inheritDoc
     */
    public function accept()
    {
        /** @var \DirectoryIterator $value */
        $value = $this->current();

        return in_array($value->getExtension(), $this->allowedExtensions, true);
    }
}
