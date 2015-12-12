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
                '--reindex'    => 'reindexProject'
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
        // TODO: Class list with constructor information.

        /*
        $index = null;
        $indexFile = Config::get('indexClasses');

        if (file_exists($indexFile)) {
            $index = json_decode(file_get_contents($indexFile), true);
        }

        return [
            'success' => true,
            // If it evaluates to false, the class map hasn't been generated yet, don't error out as it can take a while
            // for it to be generated.
            'result'  => $index ?: []
        ];
        */
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getClassInfo(array $arguments)
    {
        // TODO: Class info on specified FQSEN.

        /*
        $className = $args[0];

        if (mb_strpos($className, '\\') === 0) {
            $className = mb_substr($className, 1);
        }

        $classInfoFetcher = new ClassInfoFetcher(
            new PropertyInfoFetcher(),
            new MethodInfoFetcher(),
            new ConstantInfoFetcher()
        );

        $reflectionClass = null;

        try {
            $reflectionClass = new ReflectionClass($className);
        } catch (\Exception $e) {

        }

        return [
            'success' => !!$reflectionClass,
            'result'  => $reflectionClass ? $classInfoFetcher->getInfo($reflectionClass) : null
        ];
        */
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getGlobalFunctions(array $arguments)
    {
        $result = [];

        $definedFunctions = get_defined_functions();
        $functionInfoFetcher = new FunctionInfoFetcher();

        foreach ($definedFunctions as $group => $functions) {
            foreach ($functions as $functionName) {
                try {
                    $function = new \ReflectionFunction($functionName);
                } catch (\Exception $e) {
                    continue;
                }

                $result[$function->getName()] = $functionInfoFetcher->getInfo($function);
            }
        }

        /*$indexFunctions = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::CONSTANTS)
            ->where('structural_element_id IS NULL')
            ->execute()
            ->fetchAll();

        foreach ($indexFunctions as $function) {
            $result[$function['name']] = $functionInfoFetcher->getInfo($function['name']);
        }*/

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

        $constantInfoFetcher = new ConstantInfoFetcher();

        foreach (get_defined_constants(true) as $namespace => $constantList) {
            if ($namespace === 'user') {
                continue; // User constants are indexed.
            }

            // NOTE: Be very careful if you want to pass back the value, there are also escaped paths, newlines
            // (PHP_EOL), etc. in there.
            foreach ($constantList as $name => $value) {
                $constants[$name] = $constantInfoFetcher->getInfo($name);
                $constants[$name]['isBuiltin'] = true;
                // $constants[$name]['isBuiltin'] = ($namespace !== 'user');
            }
        }

        $indexConstants = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::CONSTANTS)
            ->where('structural_element_id IS NULL')
            ->execute()
            ->fetchAll();

        foreach ($indexConstants as $constant) {
            $constants[$constant['name']] = $constantInfoFetcher->getInfo($constant['name']);
            $constants[$constant['name']]['isBuiltin'] = false;
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
    protected function reindexProject(array $arguments)
    {
        @unlink($this->databasePath); // TODO: Remove me. for testing purposes.

        if (empty($arguments)) {
            throw new UnexpectedValueException('The path to index is required for this command.');
        }

        $projectPath = array_shift($arguments);

        $indexer = new Indexer($this->indexDatabase);

        // TODO: Also index built-in classes (do this in the indexer).

        /*
        foreach (get_declared_classes() as $class) {
            if (mb_strpos($class, 'PhpIntegrator') === 0) {
                continue; // Don't include our own classes.
            }

            if ($value = $this->fetchClassInfo($class, false)) {
                $index[$class] = $value;
            }
        }

        foreach ($this->buildClassMap() as $class => $filePath) {
            if ($value = $this->fetchClassInfo($class, true)) {
                $index[$class] = $value;
            }
        }
        */

        $indexer->indexProject($projectPath);
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
