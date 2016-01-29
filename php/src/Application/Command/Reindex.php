<?php

namespace PhpIntegrator\Application\Command;

use UnexpectedValueException;

use PhpIntegrator\Indexer;
use PhpIntegrator\IndexDataAdapter;
use PhpIntegrator\IndexStorageItemEnum;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Command that reindexes a file or folder.
 */
class Reindex extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function process(array $arguments)
    {
        if (empty($arguments)) {
            throw new UnexpectedValueException('The file or directory to index (or \'STDIN\') is required for this command.');
        }

        $showOutput = false;
        $streamProgress = false;
        $path = array_shift($arguments);

        if (!empty($arguments)) {
            $extraArg = array_shift($arguments);

            if ($extraArg === '--show-output') {
                $showOutput = true;
            } elseif ($extraArg === '--stream-progress') {
                $streamProgress = true;
            } else {
                throw new UnexpectedValueException('Unknown extra argument passed.');
            }
        }

        $indexer = new Indexer($this->indexDatabase, $showOutput, $streamProgress);

        $hasIndexedBuiltin = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('id', 'value')
            ->from(IndexStorageItemEnum::SETTINGS)
            ->where('name = ?')
            ->setParameter(0, 'has_indexed_builtin')
            ->execute()
            ->fetch();

        if (!$hasIndexedBuiltin || !$hasIndexedBuiltin['value']) {
            $indexer->indexBuiltinItems();

            if ($hasIndexedBuiltin) {
                $this->indexDatabase->update(IndexStorageItemEnum::SETTINGS, $hasIndexedBuiltin['id'], [
                    'value' => 1
                ]);
            } else {
                $this->indexDatabase->insert(IndexStorageItemEnum::SETTINGS, [
                    'name'  => 'has_indexed_builtin',
                    'value' => 1
                ]);
            }
        }

        if (is_dir($path)) {
            $indexer->indexDirectory($path);
        } elseif (is_file($path)) {
            // NOTE: This call is blocking if there is no input!
            $code = file_get_contents('php://stdin');

            try {
                $indexer->indexFile($path, $code ?: null);
            } catch (Indexer\IndexingFailedException $e) {
                throw new UnexpectedValueException('The file could not be indexed because it contains syntax errors!');
            }
        } else {
            throw new UnexpectedValueException('The specified file or directoy does not exist!');
        }

        return $this->outputJson(true, null);
    }
}
