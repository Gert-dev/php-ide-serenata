<?php

namespace PhpIntegrator;

use UnexpectedValueException;

/**
 * Main application class.
 */
class Application
{
    /**
     * The version of the database we're currently at. When there are large changes to the layout of the database, this
     * number is bumped and all databases with older versions will be dumped and replaced with a new index database.
     *
     * @var int
     */
    const DATABASE_VERSION = 1;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var IndexDataAdapter
     */
    protected $indexDataAdapter;

    /**
     * Handles the application process.
     *
     * @param array $arguments The arguments to pass.
     *
     * @return mixed
     */
    public function handle(array $arguments)
    {
        try {
            $command = $this->parseRequiredArguments($arguments);

            $commands = [
                '--class-list' => 'getClassList',
                '--class-info' => 'getClassInfo',
                '--functions'  => 'getGlobalFunctions',
                '--constants'  => 'getGlobalConstants',
                '--reindex'    => 'reindex'
            ];

            if (isset($commands[$command])) {
                return $this->{$commands[$command]}($arguments);
            }

            $supportedCommands = implode(', ', array_keys($commands));

            throw new UnexpectedValueException("Unknown command {$command}, supported commands: {$supportedCommands}");
        } catch (UnexpectedValueException $e) {
            return $this->outputJson(false, $e->getMessage());
        }
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getClassList(array $arguments)
    {
        $result = [];

        $storageProxy = new IndexDataAdapter\ClassListProxyProvider($this->indexDatabase);
        $dataAdapter = new IndexDataAdapter($storageProxy);

        foreach ($this->indexDatabase->getAllStructuralElementsRawInfo() as $element) {
            // Directly load in the raw information we already have, this avoids performing a database query for each
            // record.
            $storageProxy->setStructuralElementRawInfo($element);

            $info = $dataAdapter->getStructuralElementInfo($element['id']);

            // $constructor = null;

            // if (isset($info['methods']['__construct'])) {
                // $constructor = $info['methods']['__construct'];
            // }

            unset($info['constants'], $info['properties'], $info['methods']);

            // if ($constructor) {
                // $info['methods']['__construct'] = $constructor;
            // }

            $result[$element['fqsen']] = $info;
        }

        return $this->outputJson(true, $result);
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getClassInfo(array $arguments)
    {
        if (empty($arguments)) {
            throw new UnexpectedValueException(
                'The fully qualified name of the structural element is required for this command.'
            );
        }

        $fqsen = array_shift($arguments);
        $fqsen = str_replace('\\\\', '\\', $fqsen);

        if ($fqsen[0] === '\\') {
            $fqsen = mb_substr($fqsen, 1);
        }

        $id = $this->indexDatabase->getStructuralElementId($fqsen);

        if (!$id) {
            throw new UnexpectedValueException('The structural element "' . $fqsen . '" was not found!');
        }

        $result = $this->getIndexDataAdapter()->getStructuralElementInfo($id);

        return $this->outputJson(true, $result);
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getGlobalFunctions(array $arguments)
    {
        $result = [];

        foreach ($this->indexDatabase->getGlobalFunctions() as $function) {
            $result[$function['name']] = $this->getIndexDataAdapter()->getFunctionInfo($function);
        }

        return $this->outputJson(true, $result);
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getGlobalConstants(array $arguments)
    {
        $constants = [];

        foreach ($this->indexDatabase->getGlobalConstants() as $constant) {
            $constants[$constant['name']] = $this->getIndexDataAdapter()->getConstantInfo($constant);
        }

        return $this->outputJson(true, $constants);
    }

    /**
     * Parses required arguments for the application and fetches the command to execute.
     *
     * @param array $arguments
     *
     * @return string
     *
     * @throws UnexpectedValueException
     */
    protected function parseRequiredArguments(array &$arguments)
    {
        if (count($arguments) < 2) {
            throw new UnexpectedValueException(
                'Not enough arguments passed. Usage: Main.php <database path> <command> [<additional options>]'
            );
        }

        $databasePath = array_shift($arguments);

        $this->indexDatabase = new IndexDatabase($databasePath, static::DATABASE_VERSION);

        $command = array_shift($arguments);

        return $command;
    }

    /**
     * Reindexes the project using the specified arguments.
     *
     * @param array $arguments
     *
     * @throws UnexpectedValueException
     */
    protected function reindex(array $arguments)
    {
        if (empty($arguments)) {
            throw new UnexpectedValueException('The path to index is required for this command.');
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
        } else {
            $indexer->indexFile($path);
        }

        return $this->outputJson(true, null);
    }

    /**
     * @return IndexDataAdapter
     */
    protected function getIndexDataAdapter()
    {
        if (!$this->indexDataAdapter) {
            $this->indexDataAdapter = new IndexDataAdapter($this->indexDatabase);
        }

        return $this->indexDataAdapter;
    }

    /**
     * Outputs JSON.
     *
     * @param bool  $success
     * @param mixed $data
     *
     * @return string
     */
    protected function outputJson($success, $data)
    {
        return json_encode([
            'success' => $success,
            'result'  => $data
        ]);
    }
}
