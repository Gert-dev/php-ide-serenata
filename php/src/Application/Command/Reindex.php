<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

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
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('source:', 'The file or directory to index.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('v|verbose?', 'If set, verbose output will be displayed.');
        $optionCollection->add('s|stream-progress?', 'If set, progress will be streamed. Incompatible with verbose mode.');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['source'])) {
            throw new UnexpectedValueException('The file or directory to index is required for this command.');
        }

        return $this->reindex(
            $arguments['source']->value,
            isset($arguments['stdin']),
            isset($arguments['verbose']),
            isset($arguments['stream-progress'])
        );
    }

    /**
     * @param string $path
     * @param bool   $useStdin
     * @param bool   $showOutput
     * @param bool   $doStreamProgress
     */
    protected function reindex($path, $useStdin, $showOutput, $doStreamProgress)
    {
        $indexer = new Indexer($this->indexDatabase, $showOutput, $doStreamProgress);

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
            $errors = $indexer->indexDirectory($path);

            return $this->outputJson(true, ['errors' => $errors]);
        } elseif (is_file($path)) {
            $code = null;

            if ($useStdin) {
                // NOTE: This call is blocking if there is no input!
                $code = file_get_contents('php://stdin');
            }

            try {
                $indexer->indexFile($path, $code ?: null);
            } catch (Indexer\IndexingFailedException $e) {
                return $this->outputJson(false, ['errors' => $e->getErrors()]);
            }

            return $this->outputJson(true, ['errors' => []]);
        }

        throw new UnexpectedValueException('The specified file or directory does not exist!');
    }
}
