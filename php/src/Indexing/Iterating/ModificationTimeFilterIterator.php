<?php

namespace PhpIntegrator\Indexing\Iterating;

use Iterator;
use FilterIterator;

/**
 * Filters out {@see \SplFileInfo} values that haven't been modified since a preconfigured time.
 */
class ModificationTimeFilterIterator extends FilterIterator
{
    /**
     * @var array
     */
    protected $fileModifiedMap;

    /**
     * @param Iterator $iterator
     * @param array    $fileModifiedMap
     */
    public function __construct(Iterator $iterator, array $fileModifiedMap)
    {
        parent::__construct($iterator);

        $this->fileModifiedMap = $fileModifiedMap;
    }

    /**
     * @inheritDoc
     */
    public function accept()
    {
        /** @var \SplFileInfo $value */
        $value = $this->current();

        $filename = $value->getPathname();

        return
            !isset($this->fileModifiedMap[$filename]) ||
            $value->getMTime() > $this->fileModifiedMap[$filename]->getTimestamp();
    }
}
