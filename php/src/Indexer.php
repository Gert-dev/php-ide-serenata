<?php

namespace PhpIntegrator;

use DateTime;
use ReflectionClass;
use FilesystemIterator;
use UnexpectedValueException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use PhpParser\Lexer;
use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

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
     * @var TypeAnalyzer|null
     */
    protected $typeAnalyzer;

    /**
     * @var Parser|null
     */
    protected $parser;

    /**
     * @var array|null
     */
    protected $accessModifierMap;

    /**
     * @var array|null
     */
    protected $structureTypeMap;

    /**
     * Whether to display (debug) output.
     *
     * @var bool
     */
    protected $showOutput;

    /**
     * Whether to stream progress.
     *
     * @var bool
     */
    protected $streamProgress;

    /**
     * Constructor.
     *
     * @param Indexer\StorageInterface $storage
     * @param bool                     $showOutput
     * @param bool                     $streamProgress
     */
    public function __construct(Indexer\StorageInterface $storage, $showOutput = false, $streamProgress = false)
    {
        $this->storage = $storage;
        $this->showOutput = $showOutput;
        $this->streamProgress = $streamProgress;
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
     * Logs progress for streaming progress.
     *
     * @param int $itemNumber
     * @param int $totalItemCount
     */
    protected function sendProgress($itemNumber, $totalItemCount)
    {
        if (!$this->streamProgress) {
            return;
        }

        if ($totalItemCount) {
            $progress = ($itemNumber / $totalItemCount) * 100;
        } else {
            $progress = 100;
        }

        file_put_contents('php://stderr', $progress . PHP_EOL);
    }

    /**
     * Indexes the specified project.
     *
     * @param string $directory
     *
     * @return array A list of errors accumulated during indexing (indexing continues when these happen).
     */
    public function indexDirectory($directory)
    {
        $this->logMessage('Pruning removed files...');
        $this->pruneRemovedFiles();

        $this->logMessage('Scanning for files that need (re)indexing...');
        $fileClassMap = $this->scan($directory);

        foreach ($fileClassMap as $filename => $fqsens) {
            $this->logMessage('  - ' . $filename);

            foreach ($fqsens as $fqsen => $dependencyFqsens) {
                $this->logMessage('    - ' . $fqsen);

                foreach ($dependencyFqsens as $dependencyFqsen) {
                    $this->logMessage('      - ' . $dependencyFqsen);
                }
            }
        }

        $this->logMessage('Indexing outline...');

        $totalItems = count($files);

        $this->sendProgress(0, $totalItems);

        $errors = [];

        foreach ($files as $i => $filePath) {
            echo $this->logMessage('  - Indexing ' . $filePath);

            try {
                $this->indexFile($filePath);
            } catch (Indexer\IndexingFailedException $e) {
                $errors = array_merge($errors, $e->getErrors());

                $this->logMessage('    - ERROR: Indexing failed due to parsing errors!');
            }

            $this->sendProgress($i+1, $totalItems);
        }

        return $errors;
    }

    /**
     * Indexes the specified file.
     *
     * @param string      $filePath
     * @param string|null $code     The source code of the file. If null, will be fetched automatically.
     */
    public function indexFile($filePath, $code = null)
    {
        $code = $code ?: @file_get_contents($filePath);

        if (!is_string($code)) {
            throw new Indexer\IndexingFailedException();
        }

        $this->storage->beginTransaction();

        try {
            $this->indexFileOutline($filePath, $code);

            $this->storage->commitTransaction();
        } catch (Error $e) {
            $this->storage->rollbackTransaction();

            throw new Indexer\IndexingFailedException([
                [
                    'file'        => $filePath,
                    'startLine'   => $e->getStartLine() >= 0 ? $e->getStartLine() : null,
                    'endLine'     => $e->getEndLine() >= 0 ? $e->getEndLine() : null,
                    'startColumn' => $e->hasColumnInfo() ? $e->getStartColumn($code) : null,
                    'endColumn'   => $e->hasColumnInfo() ? $e->getEndColumn($code) : null,
                    'message'     => $e->getMessage()
                ]
            ]);
        }
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

        $iterator = new RecursiveIteratorIterator(
            $dirIterator,
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        /** @var \DirectoryIterator $fileInfo */
        foreach ($iterator as $filename => $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            if (!$isIncremental
             || !isset($fileModifiedMap[$filename])
             || $fileInfo->getMTime() > $fileModifiedMap[$filename]->getTimestamp()
            ) {
                $dependencies = [];

                try {
                    $dependencies = $this->getFqsenDependenciesForFile($filename);
                } catch (Error $e) {

                }

                $fileClassMap[$filename] = $dependencies;
            }
        }

        return $fileClassMap;
    }

    /**
     * Retrieves a list of FQSENs in the specified file along with their dependencies.
     *
     * @throws Error When the file could not be parsed.
     *
     * @return array
     */
    protected function getFqsenDependenciesForFile($filename)
    {
        $nodes = [];
        $parser = $this->getParser();

        $nodes = $parser->parse(@file_get_contents($filename));

        if ($nodes === null) {
            throw new Error('Unknown syntax error encountered');
        }

        $dependencyFetchingVisitor = new Indexer\DependencyFetchingVisitor();

        $traverser = new NodeTraverser(false);
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
                    'end_line'              => null,
                    'is_builtin'            => 1, // ($namespace !== 'user' ? 1 : 0)
                    'is_deprecated'         => false,
                    'short_description'     => null,
                    'long_description'      => null,
                    'return_type'           => null,
                    'full_return_type'      => null,
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
                    'end_line'              => null,
                    'is_builtin'            => 1,
                    'is_deprecated'         => $function->isDeprecated() ? 1 : 0,
                    'short_description'     => null,
                    'long_description'      => null,
                    'return_type'           => $returnType,
                    'full_return_type'      => $returnType,
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
                        'type'         => (string) $type,
                        'full_type'    => (string) $type,
                        'description'  => null,
                        'is_reference' => $parameter->isPassedByReference() ? 1 : 0,
                        'is_optional'  => $parameter->isOptional() ? 1 : 0,
                        'is_variadic'  => $isVariadic ? 1 : 0
                    ];

                    if (!isset($parameterData['name'])) {
                        $this->logMessage(
                            '  - WARNING: Ignoring malformed function parameters for ' . $function->getName()
                        );

                        // Some PHP extensions somehow contain parameters that have no name. An example of this is
                        // ssh2_poll (from the ssh2 extension). Strangely enough this mystery function also can't be
                        // found in the documentation. (Perhaps a bug in the extension?) Ignore these.
                        continue;
                    }

                    $parameters[] = $parameterData;

                    $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, $parameterData);
                }

                $this->storage->update(IndexStorageItemEnum::FUNCTIONS, $functionId, [
                    'throws_serialized'     => serialize([]),
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
                $this->indexBuiltinStructure($element);
            }
        }

        foreach (get_declared_interfaces() as $interface) {
            $element = new ReflectionClass($interface);

            if ($element->isInternal()) {
                $this->indexBuiltinStructure($element);
            }
        }

        foreach (get_declared_classes() as $class) {
            $element = new ReflectionClass($class);

            if ($element->isInternal()) {
                $this->indexBuiltinStructure($element);
            }
        }
    }

    /**
     * Indexes the specified built-in structural element.
     *
     * @param ReflectionClass $element
     */
    protected function indexBuiltinStructure(ReflectionClass $element)
    {
        $type = null;
        $parents = [];
        $interfaces = [];

        if ($element->isTrait()) {
            $type = 'trait';
            $interfaces = [];
            $parents = [];
        } elseif ($element->isInterface()) {
            $type = 'interface';
            $interfaces = [];

            // 'getParentClass' only returns one extended interface. If an interface extends multiple interfaces, the
            // other ones instead show up in 'getInterfaceNames'.
            $parents = $element->getInterfaceNames();
        } else {
            $type = 'class';
            $interfaces = $element->getInterfaceNames();
            $parents = $element->getParentClass() ? [$element->getParentClass()->getName()] : [];
        }

        // Adapt the data from the ReflectionClass to the data from the OutlineIndexingVisitor.
        $rawData = [
            'name'       => $element->getName(),
            'type'       => $type,
            'startLine'  => null,
            'endLine'    => null,
            'isAbstract' => $element->isAbstract(),
            'docComment' => null,
            'parents'    => $parents,
            'interfaces' => $interfaces,
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
                    'type'        => (string) $type,
                    'fullType'    => (string) $type,
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
                'name'           => $method->getName(),
                'startLine'      => null,
                'endLine'        => null,
                'isPublic'       => $method->isPublic(),
                'isPrivate'      => $method->isPrivate(),
                'isProtected'    => $method->isProtected(),
                'isStatic'       => $method->isStatic(),
                'returnType'     => $returnType,
                'fullReturnType' => $returnType,
                'parameters'     => $parameters,
                'docComment'     => null
            ];
        }

        foreach ($element->getProperties() as $property) {
            $rawData['properties'][$property->getName()] = [
                'name'        => $property->getName(),
                'startLine'   => null,
                'endLine'     => null,
                'isPublic'    => $property->isPublic(),
                'isPrivate'   => $property->isPrivate(),
                'isStatic'    => $property->isStatic(),
                'isProtected' => $property->isProtected(),
                'docComment'  => null
            ];
        }

        foreach ($element->getConstants() as $constantName => $constantValue) {
            $rawData['constants'][$constantName] = [
                'name'       => $constantName,
                'startLine'  => null,
                'endLine'    => null,
                'docComment' => null
            ];
        }

        $this->indexStructure($rawData, null, $element->getName(), true);
    }

    /**
     * Indexes the outline of the specified file.
     *
     * The outline consists of functions, structural elements (classes, interfaces, traits, ...), ... contained within
     * the file. For structural elements, this also includes (direct) members, information about the parent class,
     * used traits, etc.
     *
     * @param string $fileName
     * @param string $code
     *
     * @throws Error When the file could not be parsed.
     */
    protected function indexFileOutline($fileName, $code)
    {
        $nodes = $this->getParser()->parse($code);

        if ($nodes === null) {
            throw new Error('Unknown syntax error encountered');
        }

        $outlineIndexingVisitor = new Indexer\OutlineIndexingVisitor();
        $useStatementFetchingVisitor = new Indexer\UseStatementFetchingVisitor();

        $traverser = new NodeTraverser(false);
        $traverser->addVisitor($outlineIndexingVisitor);
        $traverser->addVisitor($useStatementFetchingVisitor);
        $traverser->traverse($nodes);

        $this->storage->deleteFile($fileName);

        $fileId = $this->storage->insert(IndexStorageItemEnum::FILES, [
            'path'         => $fileName,
            'indexed_time' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        $indexedSeIds = [];

        foreach ($outlineIndexingVisitor->getStructures() as $fqsen => $structure) {
            $indexedSeIds[] = $this->indexStructure(
                $structure,
                $fileId,
                $fqsen,
                false,
                $useStatementFetchingVisitor
            );
        }

        foreach ($outlineIndexingVisitor->getGlobalFunctions() as $function) {
            $this->indexFunction($function, $fileId, null, null, false, $useStatementFetchingVisitor);
        }

        foreach ($outlineIndexingVisitor->getGlobalConstants() as $constant) {
            $this->indexConstant($constant, $fileId, null, $useStatementFetchingVisitor);
        }

        foreach ($useStatementFetchingVisitor->getNamespaces() as $namespace) {
            $namespaceId = $this->storage->insert(IndexStorageItemEnum::FILES_NAMESPACES, [
                'start_line'  => $namespace['startLine'],
                'end_line'    => $namespace['endLine'],
                'namespace'   => $namespace['name'],
                'file_id'    => $fileId
            ]);

            foreach ($namespace['useStatements'] as $useStatement) {
                $this->storage->insert(IndexStorageItemEnum::FILES_NAMESPACES_IMPORTS, [
                    'line'               => $useStatement['line'],
                    'alias'              => $useStatement['alias'] ?: null,
                    'fqsen'              => $useStatement['fqsen'],
                    'files_namespace_id' => $namespaceId
                ]);
            }
        }
    }

    /**
     * Indexes the specified structural element.
     *
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param string                                   $fqsen
     * @param bool                                     $isBuiltin
     * @param Indexer\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     *
     * @return int The ID of the structural element.
     */
    protected function indexStructure(
        array $rawData,
        $fileId,
        $fqsen,
        $isBuiltin,
        Indexer\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $structureTypeMap = $this->getStructureTypeMap();

        $documentation = $this->getDocParser()->parse($rawData['docComment'], [
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION,
            DocParser::METHOD,
            DocParser::PROPERTY,
            DocParser::PROPERTY_READ,
            DocParser::PROPERTY_WRITE
        ], $rawData['name']);

        $seData = [
            'name'              => $rawData['name'],
            'fqsen'             => $fqsen,
            'file_id'           => $fileId,
            'start_line'        => $rawData['startLine'],
            'end_line'          => $rawData['endLine'],
            'structure_type_id' => $structureTypeMap[$rawData['type']],
            'is_abstract'       => (isset($rawData['isAbstract']) && $rawData['isAbstract']) ? 1 : 0,
            'is_deprecated'     => $documentation['deprecated'] ? 1 : 0,
            'is_builtin'        => $isBuiltin ? 1 : 0,
            'has_docblock'      => empty($rawData['docComment']) ? 0 : 1,
            'short_description' => $documentation['descriptions']['short'],
            'long_description'  => $documentation['descriptions']['long']
        ];

        $this->storage->deleteStructure($this->getNormalizedFqcn($fqsen));

        $seId = $this->storage->insert(IndexStorageItemEnum::STRUCTURES, $seData);

        $accessModifierMap = $this->getAccessModifierMap();

        if (isset($rawData['parents'])) {
            foreach ($rawData['parents'] as $parent) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_PARENTS_LINKED, [
                    'structure_id'           => $seId,
                    'linked_structure_fqsen' => $this->getNormalizedFqcn($parent)
                ]);
            }
        }

        if (isset($rawData['interfaces'])) {
            foreach ($rawData['interfaces'] as $interface) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_INTERFACES_LINKED, [
                    'structure_id'           => $seId,
                    'linked_structure_fqsen' => $this->getNormalizedFqcn($interface)
                ]);
            }
        }

        if (isset($rawData['traits'])) {
            foreach ($rawData['traits'] as $trait) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_LINKED, [
                    'structure_id'           => $seId,
                    'linked_structure_fqsen' => $this->getNormalizedFqcn($trait)
                ]);
            }
        }

        if (isset($rawData['traitAliases'])) {
            foreach ($rawData['traitAliases'] as $traitAlias) {
                $accessModifier = $this->parseAccessModifier($traitAlias, true);

                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_ALIASES, [
                    'structure_id'          => $seId,
                    'trait_structure_fqsen' => $this->getNormalizedFqcn($traitAlias['trait']),
                    'access_modifier_id'    => $accessModifier ? $accessModifierMap[$accessModifier] : null,
                    'name'                  => $traitAlias['name'],
                    'alias'                 => $traitAlias['alias']
                ]);
            }
        }

        if (isset($rawData['traitPrecedences'])) {
            foreach ($rawData['traitPrecedences'] as $traitPrecedence) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_PRECEDENCES, [
                    'structure_id'          => $seId,
                    'trait_structure_fqsen' => $this->getNormalizedFqcn($traitPrecedence['trait']),
                    'name'                  => $traitPrecedence['name']
                ]);
            }
        }

        foreach ($rawData['properties'] as $property) {
            $accessModifier = $this->parseAccessModifier($property);

            $this->indexProperty(
                $property,
                $fileId,
                $seId,
                $accessModifierMap[$accessModifier],
                false,
                $useStatementFetchingVisitor
            );
        }

        foreach ($rawData['methods'] as $method) {
            $accessModifier = $this->parseAccessModifier($method);

            $this->indexFunction(
                $method,
                $fileId,
                $seId,
                $accessModifierMap[$accessModifier],
                false,
                $useStatementFetchingVisitor
            );
        }

        foreach ($rawData['constants'] as $constant) {
            $this->indexConstant(
                $constant,
                $fileId,
                $seId,
                $useStatementFetchingVisitor
            );
        }

        // Index magic properties.
        $magicProperties = array_merge(
            $documentation['properties'],
            $documentation['propertiesReadOnly'],
            $documentation['propertiesWriteOnly']
        );

        foreach ($magicProperties as $propertyName => $propertyData) {
            $data = $this->adaptMagicPropertyData($propertyName, $propertyData);

            // Use the same line as the class definition, it matters for e.g. type resolution.
            $data['startLine'] = $data['endLine'] = $rawData['startLine'];

            $this->indexProperty(
                $data,
                $fileId,
                $seId,
                $accessModifierMap['public'],
                true,
                $useStatementFetchingVisitor
            );
        }

        // Index magic methods.
        foreach ($documentation['methods'] as $methodName => $methodData) {
            $data = $this->adaptMagicMethodData($methodName, $methodData);

            // Use the same line as the class definition, it matters for e.g. type resolution.
            $data['startLine'] = $data['endLine'] = $rawData['startLine'];

            $this->indexFunction(
                $data,
                $fileId,
                $seId,
                $accessModifierMap['public'],
                true,
                $useStatementFetchingVisitor
            );
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
            'name'             => mb_substr($name, 1), // Strip off the dollar sign.
            'startLine'        => null,
            'endLine'          => null,
            'isPublic'         => true,
            'isPrivate'        => false,
            'isProtected'      => false,
            'isStatic'         => $data['isStatic'],
            'returnType'       => $data['type'],
            'fullReturnType'   => null,                // Determine automatically.
            'shortDescription' => $data['description'],
            'docComment'       => null
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
                'name'        => mb_substr($parameterName, 1), // Strip off the dollar sign.
                'type'        => $parameter['type'],
                'fullType'    => null,                         // Determine automatically.
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => false
            ];
        }

        foreach ($data['optionalParameters'] as $parameterName => $parameter) {
            $parameters[] = [
                'name'        => mb_substr($parameterName, 1), // Strip off the dollar sign.
                'type'        => $parameter['type'],
                'fullType'    => null,                         // Determine automatically.
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => true
            ];
        }

        return [
            'name'             => $name,
            'startLine'        => null,
            'endLine'          => null,
            'returnType'       => $data['type'],
            'fullReturnType'   => null,                       // Determine automatically.
            'parameters'       => $parameters,
            'shortDescription' => $data['description'],
            'docComment'       => null,
            'isPublic'         => true,
            'isPrivate'        => false,
            'isProtected'      => false,
            'isStatic'         => $data['isStatic']
        ];
    }

    /**
     * Indexes the specified constant.
     *
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param int|null                                 $seId
     * @param Indexer\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     */
    protected function indexConstant(
        array $rawData,
        $fileId,
        $seId = null,
        Indexer\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $documentation = $this->getDocParser()->parse($rawData['docComment'], [
            DocParser::VAR_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION
        ], $rawData['name']);

        $returnType = null;
        $fullReturnType = null;

        if ($documentation['var']['type']) {
            $returnType = $documentation['var']['type'];

            $fullReturnType = $this->getFullTypeForDocblockType(
                $returnType,
                $rawData['startLine'],
                $useStatementFetchingVisitor
            );
        }

        $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
            'is_builtin'            => 0,
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $documentation['descriptions']['short'],
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $returnType,
            'full_return_type'      => $fullReturnType,
            'return_description'    => $documentation['var']['description'],
            'structure_id'          => $seId,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1
        ]);
    }

    /**
     * Indexes the specified property.
     *
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param int                                      $seId
     * @param int                                      $amId
     * @param bool                                     $isMagic
     * @param Indexer\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     */
    protected function indexProperty(
        array $rawData,
        $fileId,
        $seId,
        $amId,
        $isMagic = false,
        Indexer\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $documentation = $this->getDocParser()->parse($rawData['docComment'], [
            DocParser::VAR_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION
        ], $rawData['name']);

        $shortDescription = isset($rawData['shortDescription']) ? $rawData['shortDescription'] : null;

        if ($shortDescription === null) {
            $shortDescription = $documentation['descriptions']['short'];

            // You can place documentation after the @var tag as well as at the start of the docblock. Fall back
            // from the latter to the former.
            if (empty($shortDescription)) {
                $shortDescription = $documentation['var']['description'];
            }
        }

        $returnType = isset($rawData['returnType']) ? $rawData['returnType'] : null;
        $returnType = $returnType ?: ($documentation ? $documentation['var']['type'] : null);

        $fullReturnType = isset($rawData['fullReturnType']) ? $rawData['fullReturnType'] : null;

        if (!$fullReturnType) {
            $fullReturnType = $this->getFullTypeForDocblockType(
                $returnType,
                $rawData['startLine'],
                $useStatementFetchingVisitor
            );
        }

        $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $shortDescription,
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $returnType,
            'full_return_type'      => $fullReturnType,
            'return_description'    => $documentation['var']['description'],
            'structure_id'          => $seId,
            'access_modifier_id'    => $amId,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1,
            'is_magic'              => $isMagic ? 1 : 0,
            'is_static'             => $rawData['isStatic'] ? 1 : 0
        ]);
    }

    /**
     * Indexes the specified function.
     *
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param int|null                                 $seId
     * @param int|null                                 $amId
     * @param bool                                     $isMagic
     * @param Indexer\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     */
    protected function indexFunction(
        array $rawData,
        $fileId,
        $seId = null,
        $amId = null,
        $isMagic = false,
        Indexer\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $documentation = $this->getDocParser()->parse($rawData['docComment'], [
            DocParser::THROWS,
            DocParser::PARAM_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION,
            DocParser::RETURN_VALUE
        ], $rawData['name']);

        $returnType = $rawData['returnType'] ?: ($documentation ? $documentation['return']['type'] : null);
        $fullReturnType = $rawData['fullReturnType'];

        if (!$fullReturnType) {
            $fullReturnType = $this->getFullTypeForDocblockType(
                $returnType,
                $rawData['startLine'],
                $useStatementFetchingVisitor
            );
        }

        $shortDescription = isset($rawData['shortDescription']) ? $rawData['shortDescription'] : null;

        if ($shortDescription === null) {
            $shortDescription = $documentation['descriptions']['short'];
        }

        $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
            'is_builtin'            => 0,
            'is_abstract'           => (isset($rawData['isAbstract']) && $rawData['isAbstract']) ? 1 : 0,
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'short_description'     => $shortDescription,
            'long_description'      => $documentation['descriptions']['long'],
            'return_type'           => $returnType,
            'full_return_type'      => $fullReturnType,
            'return_description'    => $documentation['return']['description'],
            'structure_id'          => $seId,
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

            $type = $parameter['type'] ?: ($parameterDoc ? $parameterDoc['type'] : null);
            $fullType = $parameter['fullType'];

            if (!$fullType) {
                $fullType = $this->getFullTypeForDocblockType(
                    $type,
                    $rawData['startLine'],
                    $useStatementFetchingVisitor
                );
            }

            $parameterData = [
                'function_id'  => $functionId,
                'name'         => $parameter['name'],
                'type'         => $type,
                'full_type'    => $fullType,
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
                'full_type'   => $this->getFullTypeForDocblockType($type, $rawData['startLine'], $useStatementFetchingVisitor),
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
     * Resolves and determines the FQSEN of the specified type.
     *
     * @param string                                   $type
     * @param int                                      $line
     * @param Indexer\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     *
     * @return string|null
     */
    protected function getFullTypeForDocblockType(
        $type,
        $line,
        Indexer\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        // There can be multiple namespaces in each file, check with namespace is active for the specified line.
        $useStatements = [];
        $namespaceName = null;

        if ($useStatementFetchingVisitor) {
            if (!$line) {
                throw new UnexpectedValueException('The passed line number must not be null!');
            }

            foreach ($useStatementFetchingVisitor->getNamespaces() as $namespace) {
                if ($line >= $namespace['startLine'] &&
                   ($line <= $namespace['endLine'] || $namespace['endLine'] === null)) {
                    $namespaceName = $namespace['name'];

                    // Only use statements before the current line actually apply.
                    foreach ($namespace['useStatements'] as $useStatement) {
                        if ($useStatement['line'] <= $line) {
                            $useStatements[] = $useStatement;
                        }
                    }

                    break;
                }
            }
        }

        $typeResolver = new TypeResolver($namespaceName, $useStatements);

        $soleType = $this->getSoleClassNameForTypeSpecification($type);

        if (!$soleType) {
            return $type;
        }

        return $typeResolver->resolve($soleType);
    }

    /**
     * Retrieves the sole class name for the specified type specification.
     *
     * @example "null" returns null.
     * @example "FooClass" returns "FooClass".
     * @example "FooClass|null" returns "FooClass".
     * @example "FooClass|BarClass|null" returns null (there is no single type).
     *
     * @param string $typeSpecification
     *
     * @return string|null
     */
    public function getSoleClassNameForTypeSpecification($typeSpecification)
    {
        if ($typeSpecification) {
            $types = explode(DocParser::TYPE_SPLITTER, $typeSpecification);

            $classTypes = [];

            foreach ($types as $type) {
                if (!$this->getTypeAnalyzer()->isSpecialType($type)) {
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
     * @param string $fqcn
     *
     * @return string
     */
    protected function getNormalizedFqcn($fqcn)
    {
        if ($fqcn && $fqcn[0] === '\\') {
            return mb_substr($fqcn, 1);
        }

        return $fqcn;
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
    protected function getStructureTypeMap()
    {
        if (!$this->structureTypeMap) {
            $this->structureTypeMap = $this->storage->getStructureTypeMap();
        }

        return $this->structureTypeMap;
    }

    /**
     * @return Parser
     */
    protected function getParser()
    {
        if (!$this->parser) {
            $lexer = new Lexer([
                'usedAttributes' => [
                    'comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos'
                ]
            ]);

            $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
        }

        return $this->parser;
    }

    /**
     * @return TypeAnalyzer
     */
    protected function getTypeAnalyzer()
    {
        if (!$this->typeAnalyzer) {
            $this->typeAnalyzer = new TypeAnalyzer();
        }

        return $this->typeAnalyzer;
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
