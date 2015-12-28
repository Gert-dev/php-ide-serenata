<?php

namespace PhpIntegrator;

use DateTime;
use SplFileInfo;
use ReflectionClass;
use FilesystemIterator;
use UnexpectedValueException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;

use Doctrine\DBAL\Exception\TableNotFoundException;

use PhpParser\Lexer;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

use PhpParser\NodeVisitor\NameResolver;

/**
 * Handles indexation of PHP code.
 *
 * The index only contains "direct" data, meaning that it only contains data that is directly attached to an element.
 * For example, classes will only have their direct members attached in the index. The index will also keep track of
 * links between structural elements and parents, implemented interfaces, and more, but it will not duplicate data,
 * meaning parent methods will not be copied and attached to child classes.
 *
 * The index keeps track of 'outlines' that are confined to a single file. It in itself does not do anything
 * "intelligent" such as automatically inheriting docblocks from overridden methods.
 */
class Indexer
{
    /**
     * The storage to use for index data.
     *
     * @var Indexer\StorageInterface
     */
    protected $storage;

    /**
     * @var DocParser|null
     */
    protected $docParser;

    /**
     * @var PhpParser\Parser|null
     */
    protected $parser;

    /**
     * @var array|null
     */
    protected $accessModifierMap;

    /**
     * @var array|null
     */
    protected $structuralElementTypeMap;

    /**
     * Whether to display (debug) output.
     *
     * @var bool
     */
    protected $showOutput;

    /**
     * Constructor.
     *
     * @param Indexer\StorageInterface $storage
     * @param bool                     $showOutput
     */
    public function __construct(Indexer\StorageInterface $storage, $showOutput = false)
    {
        $this->storage = $storage;
        $this->showOutput = $showOutput;
    }

    /**
     * Logs a single message for debugging purposes.
     *
     * @param string $message
     */
    protected function logMessage($message)
    {
        if (!$this->showOutput) {
            return;
        }

        echo $message . PHP_EOL;
    }

    /**
     * Indexes the specified project.
     *
     * @param string $directory
     */
    public function indexDirectory($directory)
    {
        $this->logMessage('Pruning removed files...');
        $this->pruneRemovedFiles();

        $this->logMessage('Scanning for files that need (re)indexing...');
        $fileClassMap = $this->scan($directory);

        $this->logMessage('Sorting the result by dependencies...');
        $files = $this->getFilesSortedByDependenciesFromScanResult($fileClassMap);

        foreach ($files as $filename) {
            $this->logMessage('  - ' . $filename);

            $fqsens = $fileClassMap[$filename];

            foreach ($fqsens as $fqsen => $dependencyFqsens) {
                $this->logMessage('    - ' . $fqsen);

                foreach ($dependencyFqsens as $dependencyFqsen) {
                    $this->logMessage('      - ' . $dependencyFqsen);
                }
            }
        }

        $this->logMessage('Indexing outline...');
        $this->indexFileOutlines($files);
    }

    /**
     * Indexes the specified file.
     *
     * @param string $filePath
     */
    public function indexFile($filePath)
    {
        $this->indexFileOutline($filePath);
    }

    /**
     * Indexes built-in functions, classes, constants, ...
     */
    public function indexBuiltinItems()
    {
        $this->logMessage('Indexing built-in constants...');
        $this->indexBuiltinConstants();

        $this->logMessage('Indexing built-in functions...');
        $this->indexBuiltinFunctions();

        $this->logMessage('Indexing built-in classes...');
        $this->indexBuiltinClasses();
    }

    /**
     * Scans the specified directory, returning a mapping of file names to a list of FQSEN's contained in the file, each
     * of which are then mapped to a list of FQSEN's they depend on. Only files that have actually been updated since
     * the previous index will be retrieved by default.
     *
     * @param string $directory
     * @param bool   $isIncremental Whether to only return files modified since their last index (or otherwise: all
     *                              files).
     *
     * @return array
     */
    protected function scan($directory, $isIncremental = true)
    {
        $fileClassMap = [];
        $fileModifiedMap = $this->storage->getFileModifiedMap();

        $dirIterator = new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
        );

        /** @var \DirectoryIterator $fileInfo */
        foreach ((new RecursiveIteratorIterator($dirIterator)) as $filename => $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            if (!$isIncremental
             || !isset($fileModifiedMap[$filename])
             || $fileInfo->getMTime() > $fileModifiedMap[$filename]->getTimestamp()
            ) {
                $fileClassMap[$filename] = $this->getFqsenDependenciesForFile($filename);
            }
        }

        return $fileClassMap;
    }

    /**
     * Sorts the specified result set from the {@see scan} method to ensure that files containing structural elements
     * that depend on other structural elements end up after their dependencies in the list.
     *
     * @param array $scanResult
     *
     * @return array A list of files, sorted in such a way that dependencies are listed before their dependents.
     */
    protected function getFilesSortedByDependenciesFromScanResult(array $scanResult)
    {
        $result = [];

        // Build a list of all FQSEN's that we received.
        $fullFqsenList = [];

        foreach ($scanResult as $filename => $fqsens) {
            foreach ($fqsens as $fqsen => $dependencyFqsens) {
                $fullFqsenList[$fqsen] = true;
            }
        }

        // See also https://github.com/marcj/topsort.php .
        $topologicalSorter = new \MJS\TopSort\Implementations\GroupedStringSort();

        foreach ($scanResult as $filename => $fqsens) {
            if (empty($fqsens)) {
                $result[] = $filename; // This file doesn't need sorting, index it first.
                continue;
            }

            foreach ($fqsens as $fqsen => $dependencyFqsens) {
                $dependencyList = [];

                foreach ($dependencyFqsens as $dependencyFqsen) {
                    // The topological sorter requires that, before sorting, all dependencies actually exist. For full
                    // indexes, this will (should) be the case, but when doing an incremental index, we may only have a
                    // couple of files that need to be indexed and things such as base classes might not need to be
                    // reindexed.
                    if (isset($fullFqsenList[$dependencyFqsen])) {
                        $dependencyList[] = $dependencyFqsen;
                    }
                }

                $topologicalSorter->add($fqsen, $filename, $dependencyList);
            }
        }

        $sortedDependencies = $topologicalSorter->sort();

        foreach ($topologicalSorter->getGroups() as $group) {
            $result[] = $group->type;
        }

        return $result;
    }

    /**
     * Retrieves a list of FQSENs in the specified file along with their dependencies.
     *
     * @return array
     */
    protected function getFqsenDependenciesForFile($filename)
    {
        $nodes = [];
        $parser = $this->getParser();

        try {
            $nodes = $parser->parse(file_get_contents($filename));
        } catch (Error $e) {
            $this->logMessage('  - WARNING: ' . $filename . ' could not be indexed due to parsing errors!');
        }

        $dependencyFetchingVisitor = new Indexer\DependencyFetchingVisitor();

        $traverser = new NodeTraverser();
        $traverser->addVisitor($dependencyFetchingVisitor);
        $traverser->traverse($nodes);

        return $dependencyFetchingVisitor->getFqsenDependencyMap();
    }

    /**
     * Prunes removed files from the index.
     */
    protected function pruneRemovedFiles()
    {
        $fileModifiedMap = $this->storage->getFileModifiedMap();

        foreach ($this->storage->getFileModifiedMap() as $filename => $indexedTime) {
            if (!file_exists($filename)) {
                $this->logMessage('  - ' . $filename);

                $id = $this->storage->getFileId($filename);

                if ($id) {
                    $this->storage->deleteFile($id);
                }
            }
        }
    }

    /**
     * Indexes the outline of the specified files.
     *
     * @param array $filePaths
     */
    protected function indexFileOutlines(array $filePaths)
    {
        foreach ($filePaths as $filePath) {
            echo $this->logMessage('  - Indexing ' . $filePath);

            $this->indexFileOutline($filePath);
        }
    }

    /**
     * Indexes built-in PHP constants.
     */
    protected function indexBuiltinConstants()
    {
        foreach (get_defined_constants(true) as $namespace => $constantList) {
            if ($namespace === 'user') {
                continue; // User constants are indexed in the outline.
            }

            // NOTE: Be very careful if you want to pass back the value, there are also escaped paths, newlines
            // (PHP_EOL), etc. in there.
            foreach ($constantList as $name => $value) {
                $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
                    'name'                  => $name,
                    'file_id'               => null,
                    'start_line'            => null,
                    'is_builtin'            => 1, // ($namespace !== 'user' ? 1 : 0)
                    'is_deprecated'         => false,
                    'short_description'     => null,
                    'long_description'      => null,
                    'return_type'           => null,
                    'return_description'    => null
                ]);
            }
        }
    }

    /**
     * Indexes built-in PHP functions.
     */
    protected function indexBuiltinFunctions()
    {
        foreach (get_defined_functions() as $group => $functions) {
            foreach ($functions as $functionName) {
                try {
                    $function = new \ReflectionFunction($functionName);
                } catch (\Exception $e) {
                    continue;
                }

                $returnType = null;

                // Requires PHP >= 7.
                if (method_exists($function, 'getReturnType')) {
                    $returnTYpe = $function->getReturnType();
                }

                $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, [
                    'name'                  => $functionName,
                    'file_id'               => null,
                    'start_line'            => null,
                    'is_builtin'            => 1,
                    'is_deprecated'         => $function->isDeprecated() ? 1 : 0,
                    'short_description'     => null,
                    'long_description'      => null,
                    'return_type'           => $returnType,
                    'return_description'    => null
                ]);

                $parameters = [];

                foreach ($function->getParameters() as $parameter) {
                    $isVariadic = false;

                    // Requires PHP >= 5.6.
                    if (method_exists($parameter, 'isVariadic')) {
                        $isVariadic = $parameter->isVariadic();
                    }

                    $type = null;

                    // Requires PHP >= 7, good thing this only affects built-in functions, which don't have any type
                    // hinting yet anyways (at least in PHP < 7).
                    if (method_exists($function, 'getType')) {
                        $type = $function->getType();
                    }

                    $parameterData = [
                        'function_id'  => $functionId,
                        'name'         => $parameter->getName(),
                        'type'         => $type,
                        'description'  => null,
                        'is_reference' => $parameter->isPassedByReference() ? 1 : 0,
                        'is_optional'  => $parameter->isOptional() ? 1 : 0,
                        'is_variadic'  => $isVariadic ? 1 : 0
                    ];

                    $parameters[] = $parameterData;

                    $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, $parameterData);
                }

                $this->storage->update(IndexStorageItemEnum::FUNCTIONS, $functionId, [
                    'throws_serialized'     => null,
                    'parameters_serialized' => serialize($parameters)
                ]);
            }
        }
    }

    /**
     * Indexes built-in PHP classes.
     */
    protected function indexBuiltinClasses()
    {
        foreach (get_declared_traits() as $trait) {
            $element = new ReflectionClass($trait);

            if ($element->isInternal()) {
                $this->indexBuiltinStructuralElement($element);
            }
        }

        foreach (get_declared_interfaces() as $interface) {
            $element = new ReflectionClass($interface);

            if ($element->isInternal()) {
                $this->indexBuiltinStructuralElement($element);
            }
        }

        foreach (get_declared_classes() as $class) {
            $element = new ReflectionClass($class);

            if ($element->isInternal()) {
                $this->indexBuiltinStructuralElement($element);
            }
        }
    }

    /**
     * Indexes the specified built-in structural element.
     *
     * @param ReflectionClass $element
     */
    protected function indexBuiltinStructuralElement(ReflectionClass $element)
    {
        $type = null;

        if ($element->isTrait()) {
            $type = 'trait';
        } elseif ($element->isInterface()) {
            $type = 'interface';
        } else {
            $type = 'class';
        }

        // Adapt the data from the ReflectionClass to the data from the OutlineIndexingVisitor.
        $rawData = [
            'name'       => $element->getName(),
            'type'       => $type,
            'startLine'  => null,
            'isAbstract' => $element->isAbstract(),
            'docComment' => null,
            'parent'     => $element->getParentClass() ? $element->getParentClass()->getName() : null,
            'interfaces' => $element->getInterfaceNames(),
            'traits'     => $element->getTraitNames(),
            'methods'    => [],
            'properties' => [],
            'constants'  => []
        ];

        foreach ($element->getMethods() as $method) {
            $parameters = [];

            /** @var \ReflectionParameter $param */
            foreach ($method->getParameters() as $param) {
                $type = null;
                $isVariadic = false;

                // Requires PHP >= 5.6.
                if (method_exists($param, 'isVariadic')) {
                    $isVariadic = $param->isVariadic();
                }

                // Requires PHP >= 7.0.
                if (method_exists($param, 'getType')) {
                    $type = $param->getType();
                }

                $parameters[] = [
                    'name'        => $param->getName(),
                    'type'        => $type,
                    'isReference' => $param->isPassedByReference(),
                    'isVariadic'  => $isVariadic,
                    'isOptional'  => $param->isOptional()
                ];
            }

            // Requires PHP >= 7.0.
            $returnType = null;

            if (method_exists($method, 'getReturnType')) {
                $returnType = $method->getReturnType();
            }

            $rawData['methods'][$method->getName()] = [
                'name'        => $method->getName(),
                'startLine'   => null,
                'isPublic'    => $method->isPublic(),
                'isPrivate'   => $method->isPrivate(),
                'isProtected' => $method->isProtected(),
                'isStatic'    => $method->isStatic(),
                'returnType'  => $returnType,
                'parameters'  => $parameters,
                'docComment'  => null
            ];
        }

        foreach ($element->getProperties() as $property) {
            $rawData['properties'][$property->getName()] = [
                'name'        => $property->getName(),
                'startLine'   => null,
                'isPublic'    => $property->isPublic(),
                'isPrivate'   => $property->isPrivate(),
                'isStatic'    => $property->isStatic(),
                'isProtected' => $property->isProtected(),
                'docComment'  => null
            ];
        }

        foreach ($element->getConstants() as $constant) {
            $rawData['constants'][$constant] = [
                'name'       => $constant,
                'startLine'  => null,
                'docComment' => null
            ];
        }

        $this->indexStructuralElement($rawData, null, $element->getName(), true);
    }

    /**
     * Indexes the outline of the specified file.
     *
     * The outline consists of functions, structural elements (classes, interfaces, traits, ...), ... contained within
     * the file. For structural elements, this also includes (direct) members, information about the parent class,
     * used traits, etc.
     *
     * @param string $filename
     */
    protected function indexFileOutline($filename)
    {
        $nodes = [];
        $parser = $this->getParser();

        try {
            $nodes = $parser->parse(file_get_contents($filename));
        } catch (Error $e) {
            $this->logMessage('  - WARNING: ' . $filename . ' could not be indexed due to parsing errors!');
        }

        $outlineIndexingVisitor = new Indexer\OutlineIndexingVisitor();

        $traverser = new NodeTraverser();
        $traverser->addVisitor($outlineIndexingVisitor);
        $traverser->traverse($nodes);

        $fileId = $this->storage->getFileId($filename);

        $time = (new DateTime())->format('Y-m-d H:i:s');

        if ($fileId) {
            $this->storage->deletePropertiesByFileId($fileId);
            $this->storage->deleteConstantsByFileId($fileId);
            $this->storage->deleteFunctionsByFileId($fileId);

            $this->storage->update(IndexStorageItemEnum::FILES, $fileId, [
                'indexed_time' => $time
            ]);
        } else {
            $fileId = $this->storage->insert(IndexStorageItemEnum::FILES, [
                'path'         => $filename,
                'indexed_time' => $time
            ]);
        }

        $indexedSeIds = [];

        foreach ($outlineIndexingVisitor->getStructuralElements() as $fqsen => $structuralElement) {
            $indexedSeIds[] = $this->indexStructuralElement($structuralElement, $fileId, $fqsen, false);
        }

        foreach ($outlineIndexingVisitor->getGlobalFunctions() as $function) {
            $this->indexFunction($function, $fileId);
        }

        foreach ($outlineIndexingVisitor->getGlobalConstants() as $constant) {
            $this->indexConstant($constant, $fileId);
        }

        // Remove structural elements that are no longer in this file.
        $this->storage->deleteExcludedStructuralElementsByFileId($fileId, $indexedSeIds);
    }

    /**
     * Indexes the specified structural element.
     *
     * @param array   $rawData
     * @param int     $fileId
     * @param string  $fqsen
     * @param boolean $isBuiltin
     *
     * @return int The ID of the structural element.
     */
    protected function indexStructuralElement(array $rawData, $fileId, $fqsen, $isBuiltin)
    {
        $structuralElementTypeMap = $this->getStructuralElementTypeMap();

        $documentation = $this->getDocParser()->parse($rawData['docComment'], [
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION,
            DocParser::METHOD,
            DocParser::PROPERTY,
            DocParser::PROPERTY_READ,
            DocParser::PROPERTY_WRITE
        ], $rawData['name']);

        $seData = [
            'name'                       => $rawData['name'],
            'fqsen'                      => $fqsen,
            'file_id'                    => $fileId,
            'start_line'                 => $rawData['startLine'],
            'structural_element_type_id' => $structuralElementTypeMap[$rawData['type']],
            'is_abstract'                => (isset($rawData['is_abstract']) && $rawData['is_abstract']) ? 1 : 0,
            'is_deprecated'              => $documentation['deprecated'] ? 1 : 0,
            'is_builtin'                 => $isBuiltin ? 1 : 0,
            'has_docblock'               => empty($rawData['docComment']) ? 0 : 1,
            'short_description'          => $documentation['descriptions']['short'],
            'long_description'           => $documentation['descriptions']['long']
        ];

        $seId = $this->storage->getStructuralElementId($fqsen);

        if ($seId) {
            $this->storage->deletePropertiesFor($seId);
            $this->storage->deleteMethodsFor($seId);
            $this->storage->deleteConstantsFor($seId);

            $this->storage->deleteParentLinksFor($seId);
            $this->storage->deleteInterfaceLinksFor($seId);
            $this->storage->deleteTraitLinksFor($seId);

            $this->storage->update(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, $seId, $seData);
        } else {
            $seId = $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, $seData);
        }

        $accessModifierMap = $this->getAccessModifierMap();

        if (isset($rawData['parent'])) {
            $parentSeId = $this->storage->getStructuralElementId($rawData['parent']);

            if ($parentSeId) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED, [
                    'structural_element_id'        => $seId,
                    'linked_structural_element_id' => $parentSeId
                ]);
            } else {
                $this->logMessage(
                    '  - WARNING: Could not find a record for parent FQSEN ' .
                    $rawData['parent'] . ' of FQSEN ' . $fqsen
                );
            }
        }

        if (isset($rawData['interfaces'])) {
            foreach ($rawData['interfaces'] as $interface) {
                $interfaceSeId = $this->storage->getStructuralElementId($interface);

                if ($interfaceSeId) {
                    $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_INTERFACES_LINKED, [
                        'structural_element_id'        => $seId,
                        'linked_structural_element_id' => $interfaceSeId
                    ]);
                } else {
                    $this->logMessage(
                        '  - WARNING: Could not find a record for the interface ' .
                        $interface . ' of FQSEN ' . $fqsen
                    );
                }
            }
        }

        if (isset($rawData['traits'])) {
            foreach ($rawData['traits'] as $trait) {
                $traitSeId = $this->storage->getStructuralElementId($trait);

                if ($traitSeId) {
                    $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_TRAITS_LINKED, [
                        'structural_element_id'        => $seId,
                        'linked_structural_element_id' => $traitSeId
                    ]);
                } else {
                    $this->logMessage(
                        '  - WARNING: Could not find a record for the trait ' .
                        $trait . ' of FQSEN ' . $fqsen
                    );
                }
            }
        }

        if (isset($rawData['traitAliases'])) {
            foreach ($rawData['traitAliases'] as $traitAlias) {
                $accessModifier = $this->parseAccessModifier($traitAlias, true);

                $traitSeId = null;

                if ($traitAlias['trait']) {
                    $traitSeId = $this->storage->getStructuralElementId($traitAlias['trait']);

                    if (!$traitSeId) {
                        $this->logMessage(
                            '  - WARNING: Could not find a record for the trait ' .
                            $traitAlias['trait'] . ' of FQSEN ' . $fqsen
                        );
                    }
                }

                $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_TRAITS_ALIASES, [
                    'structural_element_id'       => $seId,
                    'trait_structural_element_id' => $traitSeId,
                    'access_modifier_id'          => $accessModifier ? $accessModifierMap[$accessModifier] : null,
                    'name'                        => $traitAlias['name'],
                    'alias'                       => $traitAlias['alias']
                ]);
            }
        }

        if (isset($rawData['traitPrecedences'])) {
            foreach ($rawData['traitPrecedences'] as $traitPrecedence) {
                $traitSeId = $this->storage->getStructuralElementId($traitPrecedence['trait']);

                if ($traitSeId) {
                    $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_TRAITS_PRECEDENCES, [
                        'structural_element_id'       => $seId,
                        'trait_structural_element_id' => $traitSeId,
                        'name'                        => $traitPrecedence['name']
                    ]);
                } else {
                    $this->logMessage(
                        '  - WARNING: Could not find a record for the trait ' .
                        $traitPrecedence['trait'] . ' of FQSEN ' . $fqsen
                    );
                }
            }
        }

        foreach ($rawData['properties'] as $property) {
            $accessModifier = $this->parseAccessModifier($property);

            $this->indexProperty($property, $fileId, $seId, $accessModifierMap[$accessModifier], false);
        }

        foreach ($rawData['methods'] as $method) {
            $accessModifier = $this->parseAccessModifier($method);

            $this->indexFunction($method, $fileId, $seId, $accessModifierMap[$accessModifier], false);
        }

        foreach ($rawData['constants'] as $constant) {
            $this->indexConstant($constant, $fileId, $seId);
        }

        // Index magic properties.
        $magicProperties = array_merge(
            $documentation['properties'],
            $documentation['propertiesReadOnly'],
            $documentation['propertiesWriteOnly']
        );

        foreach ($magicProperties as $propertyName => $propertyData) {
            $data = $this->adaptMagicPropertyData($propertyName, $propertyData);

            $this->indexProperty($data, $fileId, $seId, $accessModifierMap['public'], true);
        }

        // Index magic methods.
        foreach ($documentation['methods'] as $methodName => $methodData) {
            $data = $this->adaptMagicMethodData($methodName, $methodData);

            $this->indexFunction($data, $fileId, $seId, $accessModifierMap['public'], true);
        }

        return $seId;
    }

    /**
     * Adapts data about the specified magic property to be in the same format returned by the outline indexer.
     *
     * @param string $name
     * @param array  $data
     *
     * @return array
     */
    protected function adaptMagicPropertyData($name, array $data)
    {
        return [
            'name'        => $name,
            'startLine'   => null,
            'isPublic'    => true,
            'isPrivate'   => false,
            'isProtected' => false,
            'isStatic'    => $data['isStatic'],
            'docComment'  => "/** {$data['description']} */"
        ];
    }

    /**
     * Adapts data about the specified magic method to be in the same format returned by the outline indexer.
     *
     * @param string $name
     * @param array  $data
     *
     * @return array
     */
    protected function adaptMagicMethodData($name, array $data)
    {
        $parameters = [];

        foreach ($data['requiredParameters'] as $parameterName => $parameter) {
            $parameters[] = [
                'name'        => $parameterName,
                'type'        => $parameter['type'],
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => false
            ];
        }

        foreach ($data['optionalParameters'] as $parameterName => $parameter) {
            $parameters[] = [
                'name'        => $parameterName,
                'type'        => $parameter['type'],
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => true
            ];
        }

        return [
            'name'        => $name,
            'startLine'   => null,
            'returnType'  => $data['type'],
            'parameters'  => $parameters,
            'docComment'  => "/** {$data['description']} */",
            'isPublic'    => true,
            'isPrivate'   => false,
            'isProtected' => false,
            'isStatic'    => $data['isStatic']
        ];
    }

    /**
     * Indexes the specified constant.
     *
     * @param array    $rawData
     * @param int      $fileId
     * @param int|null $seId
     */
    protected function indexConstant(array $rawData, $fileId, $seId = null)
    {
        $documentation = $this->getDocParser()->parse($rawData['docComment'], [
            DocParser::VAR_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION
        ], $rawData['name']);

        $returnType = null;

        if ($documentation['var']['type']) {
            $filename = $this->storage->getFilePathById($fileId);

            $returnType = $documentation['var']['type'];
            $returnType = $this->getFullReturnTypeForDocblockType($returnType, $filename);
        }

        $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'is_builtin'            => 0,
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $documentation['descriptions']['short'],
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $returnType,
            'return_description'    => $documentation['var']['description'],
            'structural_element_id' => $seId,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1
        ]);
    }

    /**
     * Indexes the specified property.
     *
     * @param array $rawData
     * @param int   $fileId
     * @param int   $seId
     * @param int   $amId
     * @param bool  $isMagic
     */
    protected function indexProperty(array $rawData, $fileId, $seId, $amId, $isMagic = false)
    {
        $documentation = $this->getDocParser()->parse($rawData['docComment'], [
            DocParser::VAR_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION
        ], $rawData['name']);

        $shortDescription = $documentation['descriptions']['short'];

        // You can place documentation after the @var tag as well as at the start of the docblock. Fall back
        // from the latter to the former.
        if (empty($shortDescription)) {
            $shortDescription = $documentation['var']['description'];
        }

        $returnType = null;

        if ($documentation['var']['type']) {
            $filename = $this->storage->getFilePathById($fileId);

            $returnType = $documentation['var']['type'];
            $returnType = $this->getFullReturnTypeForDocblockType($returnType, $filename);
        }

        $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $shortDescription,
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $returnType,
            'return_description'    => $documentation['var']['description'],
            'structural_element_id' => $seId,
            'access_modifier_id'    => $amId,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1,
            'is_magic'              => $isMagic ? 1 : 0,
            'is_static'             => $rawData['isStatic'] ? 1 : 0
        ]);
    }

    /**
     * Indexes the specified function.
     *
     * @param array    $rawData
     * @param int      $fileId
     * @param int|null $seId
     * @param int|null $amId
     * @param bool     $isMagic
     */
    protected function indexFunction(array $rawData, $fileId, $seId = null, $amId = null, $isMagic = false)
    {
        $documentation = $this->getDocParser()->parse($rawData['docComment'], [
            DocParser::THROWS,
            DocParser::PARAM_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION,
            DocParser::RETURN_VALUE
        ], $rawData['name']);

        $returnType = $rawData['returnType'];

        if (!$returnType) {
            $filename = $this->storage->getFilePathById($fileId);

            $returnType = $documentation['return']['type'];
            $returnType = $this->getFullReturnTypeForDocblockType($returnType, $filename);
        }

        $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'is_builtin'            => 0,
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $documentation['descriptions']['short'],
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $returnType,
            'return_description'    => $documentation['return']['description'],
            'structural_element_id' => $seId,
            'access_modifier_id'    => $amId,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1,
            'is_magic'              => $isMagic ? 1 : 0,
            'is_static'             => isset($rawData['isStatic']) ? ($rawData['isStatic'] ? 1 : 0) : 0
        ]);

        $parameters = [];

        foreach ($rawData['parameters'] as $parameter) {
            $parameterKey = '$' . $parameter['name'];
            $parameterDoc = isset($documentation['params'][$parameterKey]) ?
                $documentation['params'][$parameterKey] : null;

            $parameterData = [
                'function_id'  => $functionId,
                'name'         => $parameter['name'],
                'type'         => $parameter['type'] ?: ($parameterDoc ? $parameterDoc['type'] : null),
                'description'  => $parameterDoc ? $parameterDoc['description'] : null,
                'is_reference' => $parameter['isReference'] ? 1 : 0,
                'is_optional'  => $parameter['isOptional'] ? 1 : 0,
                'is_variadic'  => $parameter['isVariadic'] ? 1 : 0
            ];

            $parameters[] = $parameterData;

            $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, $parameterData);
        }

        $throws = [];

        foreach ($documentation['throws'] as $type => $description) {
            $throwsData = [
                'function_id' => $functionId,
                'type'        => $type,
                'description' => $description ?: null
            ];

            $throws[] = $throwsData;

            $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_THROWS, $throwsData);
        }

        $this->storage->update(IndexStorageItemEnum::FUNCTIONS, $functionId, [
            'throws_serialized'     => serialize($throws),
            'parameters_serialized' => serialize($parameters)
        ]);
    }

    /**
     * @param array $rawData
     * @param bool  $returnNull
     *
     * @return string
     *
     * @throws UnexpectedValueException
     */
    protected function parseAccessModifier(array $rawData, $returnNull = false)
    {
        if ($rawData['isPublic']) {
            return 'public';
        } elseif ($rawData['isProtected']) {
            return 'protected';
        } elseif ($rawData['isPrivate']) {
            return 'private';
        } elseif ($returnNull) {
            return null;
        }

        throw new UnexpectedValueException('Unknown access modifier returned!');
    }

    /**
     * Returns a boolean indicating if the specified value is a class type or not.
     *
     * @param string $type
     *
     * @return bool
     */
    protected function isClassType($type)
    {
        return ucfirst($type) === $type;
    }

    /**
     * Retrieves the sole class name from the specified return value statement.
     *
     * @example "null" returns null.
     * @example "FooClass" returns "FooClass".
     * @example "FooClass|null" returns "FooClass".
     * @example "FooClass|BarClass|null" returns null (there is no single type).
     *
     * @param string $returnValueStatement
     *
     * @return string|null
     */
    protected function getSoleClassName($returnValueStatement)
    {
        if ($returnValueStatement) {
            $types = explode(DocParser::TYPE_SPLITTER, $returnValueStatement);

            $classTypes = [];

            foreach ($types as $type) {
                if ($this->isClassType($type)) {
                    $classTypes[] = $type;
                }
            }

            if (count($classTypes) === 1) {
                return $classTypes[0];
            }
        }

        return null;
    }

    /**
     * Resolves and determines the FQSEN of the specified return type.
     *
     * @param string $returnType
     * @param string $filename
     *
     * @return string|null
     */
    protected function getFullReturnTypeForDocblockType($returnType, $filename)
    {
        if (!isset($returnType) || empty($returnType)) {
            return null;
        }

        $soleClassName = $this->getSoleClassName($returnType);

        if (!empty($soleClassName)) {
            if ($soleClassName[0] !== "\\") {
                $parser = new FileParser($filename);

                $completedClassName = $parser->getFullClassName($soleClassName, $useStatementFound);

                return $completedClassName;
            }

            return $soleClassName;
        }

        return $returnType;
    }

    /**
     * @return array
     */
    protected function getAccessModifierMap()
    {
        if (!$this->accessModifierMap) {
            $this->accessModifierMap = $this->storage->getAccessModifierMap();
        }

        return $this->accessModifierMap;
    }

    /**
     * @return array
     */
    protected function getStructuralElementTypeMap()
    {
        if (!$this->structuralElementTypeMap) {
            $this->structuralElementTypeMap = $this->storage->getStructuralElementTypeMap();
        }

        return $this->structuralElementTypeMap;
    }


    /**
     * @return PhpParser\Parser
     */
    protected function getParser()
    {
        if (!$this->parser) {
            $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        }

        return $this->parser;
    }

    /**
     * @return DocParser
     */
    protected function getDocParser()
    {
        if (!$this->docParser) {
            $this->docParser = new DocParser();
        }

        return $this->docParser;
    }
}
