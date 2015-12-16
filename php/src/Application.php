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
        $structuralElements = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('se.*', 'fi.path', '(setype.name) AS type_name', 'sepl.linked_structural_element_id')
            ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENT_TYPES, 'setype', 'setype.id = se.structural_element_type_id')
            ->leftJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED, 'sepl', 'sepl.structural_element_id = se.id')
            ->leftJoin('se', IndexStorageItemEnum::FILES, 'fi', 'fi.id = se.file_id')
            ->execute();

        $result = [];

        foreach ($structuralElements as $element) {
            $parentFqsens = [];

            $parentId = $element['linked_structural_element_id'];

            while ($parentId) {
                $parentSe = $this->indexDatabase->getConnection()->createQueryBuilder()
                    ->select('se.*', 'sepl.linked_structural_element_id')
                    ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, 'se')
                    ->leftJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED, 'sepl', 'sepl.structural_element_id = se.id')
                    ->where('id = ?')
                    ->setParameter(0, $parentId)
                    ->execute()
                    ->fetch();

                $parentFqsens = $parentSe['fqsen'];

                $parentId = $parentSe['linked_structural_element_id'];
            }

            $result[$element['fqsen']] = [
                'class'        => $element['fqsen'],
                'wasFound'     => true,
                'startLine'    => $element['start_line'],
                'name'         => $element['fqsen'],
                'shortName'    => $element['name'],
                'filename'     => $element['path'],
                'isTrait'      => ($element['type_name'] === 'trait'),
                'isClass'      => ($element['type_name'] === 'cass'),
                'isInterface'  => ($element['type_name'] === 'interface'),
                'isAbstract'   => !!$element['is_abstract'],
                'parents'      => $parentFqsens,
                'deprecated'   => !!$element['is_deprecated'],
                'descriptions' => [
                    'short' => $element['short_description'],
                    'long'  => $element['long_description']
                ]
            ];

            // TODO: A constructor can be an override, need inheritance support implemented for this.
            // if ($constructor) {
                // $result[$element['fqsen']]['methods']['__construct'] = [];
            // }
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
        $indexFunctions = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('fu.*', 'fi.path')
            ->from(IndexStorageItemEnum::FUNCTIONS, 'fu')
            ->leftJoin('fu', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fu.file_id')
            ->where('structural_element_id IS NULL')
            ->execute()
            ->fetchAll();

        $result = [];

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
            'deprecated'    => !!$rawInfo['is_deprecated'],

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
        $indexConstants = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::CONSTANTS)
            ->where('structural_element_id IS NULL')
            ->execute()
            ->fetchAll();

        $constants = [];

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
    protected function reindex(array $arguments)
    {
        if (empty($arguments)) {
            throw new UnexpectedValueException('The path to index is required for this command.');
        }

        $showOutput = false;
        $path = array_shift($arguments);

        if (!empty($arguments)) {
            if (array_shift($arguments) === '--show-output') {
                $showOutput = true;
            }
        }

        $indexer = new Indexer($this->indexDatabase, $showOutput);

        if (is_dir($path)) {
            $indexer->indexDirectory($path);
        } else {
            // TODO: Reindex a single file.
        }

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
