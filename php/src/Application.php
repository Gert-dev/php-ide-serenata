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
     * @var ConstantInfoFetcher
     */
    protected $constantInfoFetcher;

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

        $indexFunctions = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('fu.*', 'fi.path')
            ->from(IndexStorageItemEnum::FUNCTIONS, 'fu')
            ->innerJoin('fu', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fu.file_id')
            ->where('structural_element_id IS NULL')
            ->execute()
            ->fetchAll();

        foreach ($indexFunctions as $function) {
            $result[$function['name']] = $this->getFunctionInfo($function);
        }

        return $this->outputJson(true, $result);
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    protected function getFunctionInfo(array $rawInfo)
    {
        $parameters = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::FUNCTIONS_PARAMETERS)
            ->where('is_optional != 1 AND function_id = ?')
            ->setParameter(0, $rawInfo['id'])
            ->execute()
            ->fetchAll();

        $parameters = array_map(function (array $parameter) {
            return $parameter['name'];
        }, $parameters);

        $optionals = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::FUNCTIONS_PARAMETERS)
            ->where('is_optional = 1 AND function_id = ?')
            ->setParameter(0, $rawInfo['id'])
            ->execute()
            ->fetchAll();

        $optionals = array_map(function (array $parameter) {
            return $parameter['name'];
        }, $optionals);

        $throws = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::FUNCTIONS_THROWS)
            ->where('function_id = ?')
            ->setParameter(0, $rawInfo['id'])
            ->execute()
            ->fetchAll();

        $throwsAssoc = [];

        foreach ($throws as $throws) {
            $throwsAssoc[$throws['type']] = $throws['description'];
        }

        return [
            'name'          => $rawInfo['name'],
            'isBuiltin'     => false,
            'startLine'     => $rawInfo['start_line'],
            'filename'      => $rawInfo['path'],

            'parameters'    => $parameters,
            'optionals'     => $optionals,
            'throws'        => $throwsAssoc,
            'deprecated'    => $rawInfo['is_deprecated'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'        => $rawInfo['return_type'],
                'description' => $rawInfo['return_description']
            ]
        ];
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getGlobalConstants(array $arguments)
    {
        $constants = [];

        foreach (get_defined_constants(true) as $namespace => $constantList) {
            if ($namespace === 'user') {
                continue; // User constants are indexed.
            }

            // NOTE: Be very careful if you want to pass back the value, there are also escaped paths, newlines
            // (PHP_EOL), etc. in there.
            foreach ($constantList as $name => $value) {
                $constants[$name] = $this->getConstantInfoFetcher()->getInfo($name);
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
            $constants[$constant['name']] = $this->getConstantInfo($constant);
        }

        return $this->outputJson(true, $constants);
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    protected function getConstantInfo(array $rawInfo)
    {
        $info = [];

        $info = $this->getConstantInfoFetcher()->getInfo($rawInfo['name']);
        $info['isBuiltin'] = false;

        return $info;
    }

    /**
     * @return ConstantInfoFetcher
     */
    protected function getConstantInfoFetcher()
    {
        if (!$this->constantInfoFetcher) {
            $this->constantInfoFetcher = new ConstantInfoFetcher();
        }

        return $this->constantInfoFetcher;
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




        if ($arguments[0] === '--reindex') {
            @unlink($databasePath); // TODO: Remove me. for testing purposes.
        }



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
        if (empty($arguments)) {
            throw new UnexpectedValueException('The path to index is required for this command.');
        }

        $showOutput = false;
        $projectPath = array_shift($arguments);

        if (!empty($arguments)) {
            if (array_shift($arguments) === '--show-output') {
                $showOutput = true;
            }
        }

        $indexer = new Indexer($this->indexDatabase, $showOutput);

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

        return $this->outputJson(true, null);
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
