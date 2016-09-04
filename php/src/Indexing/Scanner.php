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
     * @param array $fileModifiedMap      A mapping of (absolute) file names to DateTime objects with their last
     *                                    modified timestamp.
     */
    public function __construct(array $fileModifiedMap)
    {
        $this->fileModifiedMap = $fileModifiedMap;
    }

    /**
     * Scans the specified directory, returning a list of file names. Only files that have actually been updated since
     * the previous index will be retrieved by default.
     *
     * @param string   $directory
     * @param string[] $allowedExtensions
     *
     * @return string[]
     */
    public function scan($directory, array $allowedExtensions = ['php'])
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

        $iterator = new ExtensionFilterIterator($iterator, $allowedExtensions);

        $files = [];

        /** @var \DirectoryIterator $fileInfo */
        foreach ($iterator as $filename => $fileInfo) {
            if (!isset($this->fileModifiedMap[$filename])
             || $fileInfo->getMTime() > $this->fileModifiedMap[$filename]->getTimestamp()
            ) {
                $files[] = $filename;
            }
        }

        return $files;
    }
}
