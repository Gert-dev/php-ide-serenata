<?php

namespace PhpIntegrator\Indexing\Iterating;

use Iterator;
use FilterIterator;

/**
 * Filters out {@see \SplFileInfo} values that match the exclusion patterns.
 */
class ExclusionFilterIterator extends FilterIterator
{
    /**
     * @var string[]
     */
    protected $excludedPatterns;

    /**
     * @param Iterator $iterator
     * @param string[] $excludedPatterns The patterns to exclude. Currently only plain (directory or file) paths are
     *                                   supported.
     */
    public function __construct(Iterator $iterator, array $excludedPatterns)
    {
        parent::__construct($iterator);

        $this->excludedPatterns = $excludedPatterns;
    }

    /**
     * @inheritDoc
     */
    public function accept()
    {
        /** @var \SplFileInfo $value */
        $value = $this->current();

        $path = $value->getPathname();

        foreach ($this->excludedPatterns as $excludedPattern) {
            if (mb_strpos($path, $excludedPattern) === 0) {
                return false;
            }
        }

        return true;
    }
}
