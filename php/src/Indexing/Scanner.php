<?php

namespace PhpIntegrator\Indexing;

use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Scans for (modified) PHP files.
 */
class Scanner
{
    /**
     * @var array
     */
    protected $fileModifiedMap;

    /**
     * @var string[]
     */
    protected $allowedExtensions;

    /**
     * @param array $fileModifiedMap      A mapping of (absolute) file names to DateTime objects with their last
     *                                    modified timestamp.
     * @param string[] $allowedExtensions
     */
    public function __construct(array $fileModifiedMap, array $allowedExtensions = ['php'])
    {
        $this->fileModifiedMap = $fileModifiedMap;
        $this->allowedExtensions = $allowedExtensions;
    }

    /**
     * Scans the specified directory, returning a list of file names. Only files that have actually been updated since
     * the previous index will be retrieved by default.
     *
     * @param string $directory
     * @param bool   $isIncremental Whether to only return files modified since their last index (otherwise: all
     *                              files are returned).
     *
     * @return string[]
     */
    public function scan($directory, $isIncremental = true)
    {
        $dirIterator = new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
        );

        $iterator = new RecursiveIteratorIterator(
            $dirIterator,
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $files = [];

        /** @var \DirectoryIterator $fileInfo */
        foreach ($iterator as $filename => $fileInfo) {
            if (!in_array($fileInfo->getExtension(), $this->allowedExtensions, true)) {
                continue;
            }

            if (!$isIncremental
             || !isset($this->fileModifiedMap[$filename])
             || $fileInfo->getMTime() > $this->fileModifiedMap[$filename]->getTimestamp()
            ) {
                $files[] = $filename;
            }
        }

        return $files;
    }
}
