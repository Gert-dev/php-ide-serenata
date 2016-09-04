<?php

namespace PhpIntegrator\Indexing\Iterating;

use SplFileInfo;
use ArrayIterator;
use AppendIterator;
use FilesystemIterator;
use UnexpectedValueException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Recursively iterates over multiple paths.
 */
class MultiRecursivePathIterator extends AppendIterator
{
    /**
     * @param string[] $paths
     *
     * @throws UnexpectedValueException
     */
    public function __construct(array $paths)
    {
        parent::__construct();

        $fileInfoIterators = [];

        foreach ($paths as $path) {
            $fileInfo = new SplFileInfo($path);

            if ($fileInfo->isDir()) {
                $directoryIterator = new RecursiveDirectoryIterator(
                    $fileInfo->getPathname(),
                    FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
                );

                $iterator = new RecursiveIteratorIterator(
                    $directoryIterator,
                    RecursiveIteratorIterator::LEAVES_ONLY,
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                );

                $fileInfoIterators[] = $iterator;
            } elseif ($fileInfo->isFile()) {
                $fileInfoIterators[] = new ArrayIterator([$fileInfo]);
            } else {
                throw new UnexpectedValueException('The specified file or directory "' . $fileInfo->getPathname() . '" does not exist!');
            }
        }

        foreach ($fileInfoIterators as $fileInfoIterator) {
            $this->append($fileInfoIterator);
        }
    }
}
