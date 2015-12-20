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
     * @var IndexStorageInterface
     */
    protected $storage;

    /**
     * @var DocParser
     */
    protected $docParser;

    /**
     * Whether to display (debug) output.
     *
     * @var bool
     */
    protected $showOutput;

    /**
     * Constructor.
     *
     * @param IndexStorageInterface $storage
     * @param bool                  $showOutput
     */
    public function __construct(IndexStorageInterface $storage, $showOutput = false)
    {
        $this->storage = $storage;
        $this->showOutput = $showOutput;
    }

    /**
     * Logs a banner for debugging purposes.
     *
     * @param string $message
     */
    protected function logBanner($message)
    {
        if (!$this->showOutput) {
            return;
        }

        echo str_repeat('=', 80) . PHP_EOL;
        echo $message . PHP_EOL;
        echo str_repeat('=', 80) . PHP_EOL;
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
        $this->logMessage('Indexing project ' . $directory);

        $this->logBanner('Pass 1 - Scanning and sorting by dependencies...');

        $fileClassMap = $this->scan($directory);
        $fileClassMap = $this->filterScanResult($fileClassMap);
        $fileClassMap = $this->sortScanResultByDependencies($fileClassMap);

        foreach ($fileClassMap as $filename => $fqsens) {
            $this->logMessage('  - ' . $filename);
        }

        $this->logBanner('Pass 2...');

        $this->logMessage('Indexing outline...');
        $this->indexFileOutlines(array_keys($fileClassMap));
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

        $dirIterator = new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
        );

        /** @var \DirectoryIterator $fileInfo */
        foreach ((new RecursiveIteratorIterator($dirIterator)) as $filename => $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $fileClassMap[$filename] = $this->getFqsenDependenciesForFile($filename);
        }

        return $fileClassMap;
    }

    /**
     * Filters the specified result set from the {@see scan} method to filter out files that are already up-to-date.
     *
     * @param array $scanResult
     *
     * @return array The input value, after filtering.
     */
    protected function filterScanResult(array $scanResult)
    {
        $fileModifiedMap = $this->storage->getFileModifiedMap();

        $result = [];

        foreach ($scanResult as $filename => $fqsens) {
            $fileInfo = new SplFileInfo($filename);

            if (!isset($fileModifiedMap[$filename])
            || $fileInfo->getMTime() > $fileModifiedMap[$filename]->getTimestamp()
            ) {
                $result[$filename] = $fqsens;
            }
        }

        return $result;
    }

    /**
     * Sorts the specified result set from the {@see scan} method to ensure that files containing structural elements
     * that depend on other structural elements end up after their dependencies in the list.
     *
     * @param array $scanResult
     *
     * @return array The input value, after sorting.
     */
    protected function sortScanResultByDependencies(array $scanResult)
    {
        uasort($scanResult, function (array $a, array $b) {
            foreach ($a as $fqsen => $dependencies) {
                foreach ($dependencies as $dependencyFqsen) {
                    if (isset($b[$dependencyFqsen])) {
                        return 1; // a is dependent on b, b must be indexed first.
                    }
                }
            }

            foreach ($b as $fqsen => $dependencies) {
                foreach ($dependencies as $dependencyFqsen) {
                    if (isset($a[$dependencyFqsen])) {
                        return -1; // b is dependent on a, a must be indexed first.
                    }
                }
            }

            return 0; // Neither are dependent on one another, order is irrelevant.
        });

        return $scanResult;
    }

    /**
     * Retrieves a list of FQSENs in the specified file along with their dependencies.
     *
     * @return array
     */
    protected function getFqsenDependenciesForFile($filename)
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $nodes = [];

        try {
            $nodes = $parser->parse(file_get_contents($filename));
        } catch (Error $e) {
            $this->logMessage('  - WARNING: ' . $filename . ' could not be indexed due to parsing errors!');
        }

        $dependencyFetchingVisitor = new DependencyFetchingVisitor();

        $traverser = new NodeTraverser();
        $traverser->addVisitor($dependencyFetchingVisitor);
        $traverser->traverse($nodes);

        return $dependencyFetchingVisitor->getFqsenDependencyMap();
    }

    /**
     * Indexes the outline of the specified files.
     *
     * @param array $filePaths
     */
    protected function indexFileOutlines(array $filePaths)
    {
        foreach ($filePaths as $filePath) {
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

                    $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, [
                        'function_id'  => $functionId,
                        'name'         => $parameter->getName(),
                        'type'         => $type,
                        'description'  => null,
                        'is_reference' => $parameter->isPassedByReference() ? 1 : 0,
                        'is_optional'  => $parameter->isOptional() ? 1 : 0,
                        'is_variadic'  => $isVariadic ? 1 : 0
                    ]);
                }
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

        $this->indexStructuralElement($rawData, null, $element->getName());
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
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $nodes = [];

        try {
            $nodes = $parser->parse(file_get_contents($filename));
        } catch (Error $e) {
            $this->logMessage('  - WARNING: ' . $filename . ' could not be indexed due to parsing errors!');
        }

        $outlineIndexingVisitor = new OutlineIndexingVisitor();

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
            $indexedSeIds[] = $this->indexStructuralElement($structuralElement, $fileId, $fqsen);
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
     * @param array  $rawData
     * @param int    $fileId
     * @param string $fqsen
     *
     * @return int The ID of the structural element.
     */
    protected function indexStructuralElement(array $rawData, $fileId, $fqsen)
    {
        $seTypeId = $this->storage->getStructuralElementTypeId($rawData['type']);

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
            'structural_element_type_id' => $seTypeId,
            'is_abstract'                => (isset($rawData['is_abstract']) && $rawData['is_abstract']) ? 1 : 0,
            'is_deprecated'              => $documentation['deprecated'] ? 1 : 0,
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

        if (isset($rawData['parent'])) {
            $parentSeId = $this->storage->getStructuralElementId($rawData['parent']);

            if ($parentSeId) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED, [
                    'structural_element_id'        => $seId,
                    'linked_structural_element_id' => $parentSeId
                ]);
            } else {
                $this->logMessage(
                    '  - WARNING: Could not find a record for the parent class ' .
                    $rawData['parent']
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
                        $interface
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
                        $trait
                    );
                }
            }
        }

        foreach ($rawData['properties'] as $property) {
            $accessModifier = $this->parseAccessModifier($property);

            $amId = $this->storage->getAccessModifierId($accessModifier);

            $this->indexProperty($property, $fileId, $seId, $amId, false);
        }

        foreach ($rawData['methods'] as $method) {
            $accessModifier = $this->parseAccessModifier($method);

            $amId = $this->storage->getAccessModifierId($accessModifier);

            $this->indexFunction($method, $fileId, $seId, $amId, false);
        }

        foreach ($rawData['constants'] as $constant) {
            $this->indexConstant($constant, $fileId);
        }

        // Index magic properties.
        $magicProperties = array_merge(
            $documentation['properties'],
            $documentation['propertiesReadOnly'],
            $documentation['propertiesWriteOnly']
        );

        $amId = $this->storage->getAccessModifierId('public');

        foreach ($magicProperties as $propertyName => $propertyData) {
            $data = $this->adaptMagicPropertyData($propertyName, $propertyData);

            $this->indexProperty($data, $fileId, $seId, $amId, true);
        }

        // Index magic methods.
        foreach ($documentation['methods'] as $methodName => $methodData) {
            $data = $this->adaptMagicMethodData($methodName, $methodData);

            $this->indexFunction($data, $fileId, $seId, $amId, true);
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

        $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'is_builtin'            => 0,
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $documentation['descriptions']['short'],
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $documentation['var']['type'],
            'return_description'    => $documentation['var']['description'],
            'structural_element_id' => $seId
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

        $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $shortDescription,
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $documentation['var']['type'],
            'return_description'    => $documentation['var']['description'],
            'structural_element_id' => $seId,
            'access_modifier_id'    => $amId,
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

        $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'is_builtin'            => 0,
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $documentation['descriptions']['short'],
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $rawData['returnType'] ?: $documentation['return']['type'],
            'return_description'    => $documentation['return']['description'],
            'structural_element_id' => $seId,
            'access_modifier_id'    => $amId,
            'is_magic'              => $isMagic ? 1 : 0,
            'is_static'             => isset($rawData['isStatic']) ? ($rawData['isStatic'] ? 1 : 0) : 0
        ]);

        foreach ($rawData['parameters'] as $parameter) {
            $parameterKey = '$' . $parameter['name'];
            $parameterDoc = isset($documentation['params'][$parameterKey]) ?
                $documentation['params'][$parameterKey] : null;

            $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, [
                'function_id'  => $functionId,
                'name'         => $parameter['name'],
                'type'         => $parameter['type'] ?: ($parameterDoc ? $parameterDoc['type'] : null),
                'description'  => $parameterDoc ? $parameterDoc['description'] : null,
                'is_reference' => $parameter['isReference'] ? 1 : 0,
                'is_optional'  => $parameter['isOptional'] ? 1 : 0,
                'is_variadic'  => $parameter['isVariadic'] ? 1 : 0
            ]);
        }

        foreach ($documentation['throws'] as $type => $description) {
            $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_THROWS, [
                'function_id' => $functionId,
                'type'        => $type,
                'description' => $description ?: null
            ]);
        }
    }

    /**
     * @param array $rawData
     *
     * @return string
     *
     * @throws UnexpectedValueException
     */
    protected function parseAccessModifier(array $rawData)
    {
        if ($rawData['isPublic']) {
            return 'public';
        } elseif ($rawData['isProtected']) {
            return 'protected';
        } elseif ($rawData['isPrivate']) {
            return 'private';
        }

        throw new UnexpectedValueException('Unknown access modifier returned!');
    }

    /**
     * @return DocParser
     */
    protected function getDocParser()
    {
        if (!$this->docParser) {
            $this->docParser = new DocParser();
        }

        return new DocParser();
    }
}
