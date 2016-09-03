<?php

namespace PhpIntegrator\Indexing;

use DateTime;
use Exception;
use UnexpectedValueException;

use PhpIntegrator\Analysis\Typing\TypeDeducer;
use PhpIntegrator\Analysis\Typing\TypeAnalyzer;
use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolver;

use PhpIntegrator\Analysis\Visiting\OutlineFetchingVisitor;
use PhpIntegrator\Analysis\Visiting\UseStatementFetchingVisitor;

use PhpIntegrator\Parsing\DocblockParser;

use PhpIntegrator\UserInterface\Command\DeduceTypesCommand;

use PhpParser\Error;
use PhpParser\Parser;
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
class FileIndexer
{
    /**
     * The storage to use for index data.
     *
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var DocblockParser
     */
    protected $docblockParser;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var DeduceTypesCommand
     */
    protected $typeDeducer;

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var array
     */
    protected $accessModifierMap;

    /**
     * @var array
     */
    protected $structureTypeMap;

    /**
     * @param StorageInterface $storage
     * @param TypeAnalyzer     $typeAnalyzer
     * @param DocblockParser   $docblockParser
     * @param TypeDeducer      $typeDeducer
     * @param Parser           $parser
     */
    public function __construct(
        StorageInterface $storage,
        TypeAnalyzer $typeAnalyzer,
        DocblockParser $docblockParser,
        TypeDeducer $typeDeducer,
        Parser $parser
    ) {
        $this->storage = $storage;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->docblockParser = $docblockParser;
        $this->typeDeducer = $typeDeducer;
        $this->parser = $parser;
    }

    /**
     * Indexes the specified file.
     *
     * @param string $filePath
     * @param string $code
     *
     * @throws IndexingFailedException
     */
    public function index($filePath, $code)
    {
        try {
            $nodes = $this->getParser()->parse($code);

            if ($nodes === null) {
                throw new Error('Unknown syntax error encountered');
            }

            $outlineIndexingVisitor = new OutlineFetchingVisitor($this->typeAnalyzer, $code);
            $useStatementFetchingVisitor = new UseStatementFetchingVisitor();

            $traverser = new NodeTraverser(false);
            $traverser->addVisitor($outlineIndexingVisitor);
            $traverser->addVisitor($useStatementFetchingVisitor);
            $traverser->traverse($nodes);
        } catch (Error $e) {
            throw new IndexingFailedException();
        }

        $this->storage->beginTransaction();

        $this->storage->deleteFile($filePath);

        $fileId = $this->storage->insert(IndexStorageItemEnum::FILES, [
            'path'         => $filePath,
            'indexed_time' => (new DateTime())->format('Y-m-d H:i:s')
        ]);

        try {
            $this->indexVisitorResults($filePath, $fileId, $outlineIndexingVisitor, $useStatementFetchingVisitor);

            $this->storage->commitTransaction();
        } catch (Exception $e) {
            $this->storage->rollbackTransaction();

            throw $e;
        }
    }

    /**
     * Indexes the results of the visitors (the outline of the specified file).
     *
     * The outline consists of functions, structural elements (classes, interfaces, traits, ...), ... contained within
     * the file. For structural elements, this also includes (direct) members, information about the parent class,
     * used traits, etc.
     *
     * @param string                      $filePath
     * @param int                         $fileId
     * @param OutlineFetchingVisitor      $outlineIndexingVisitor
     * @param UseStatementFetchingVisitor $useStatementFetchingVisitor
     */
    protected function indexVisitorResults(
        $filePath,
        $fileId,
        OutlineFetchingVisitor $outlineIndexingVisitor,
        UseStatementFetchingVisitor $useStatementFetchingVisitor
    ) {
        $imports = [];
        $namespaces = $useStatementFetchingVisitor->getNamespaces();

        foreach ($namespaces as $namespace) {
            $namespaceId = $this->storage->insert(IndexStorageItemEnum::FILES_NAMESPACES, [
                'start_line'  => $namespace['startLine'],
                'end_line'    => $namespace['endLine'],
                'namespace'   => $namespace['name'],
                'file_id'     => $fileId
            ]);

            foreach ($namespace['useStatements'] as $useStatement) {
                $imports[] = $useStatement;

                $this->storage->insert(IndexStorageItemEnum::FILES_NAMESPACES_IMPORTS, [
                    'line'               => $useStatement['line'],
                    'alias'              => $useStatement['alias'] ?: null,
                    'fqcn'               => $useStatement['name'],
                    'files_namespace_id' => $namespaceId
                ]);
            }
        }

        $typeResolver = new TypeResolver($this->typeAnalyzer);
        $fileTypeResolver = new FileTypeResolver($typeResolver, $namespaces, $imports);

        foreach ($outlineIndexingVisitor->getStructures() as $fqcn => $structure) {
             $this->indexStructure(
                 $structure,
                 $filePath,
                 $fileId,
                 $fqcn,
                 false,
                 $fileTypeResolver
             );
         }

         foreach ($outlineIndexingVisitor->getGlobalFunctions() as $function) {
             $this->indexFunction($function, $fileId, null, null, false, $fileTypeResolver);
         }

         foreach ($outlineIndexingVisitor->getGlobalConstants() as $constant) {
             $this->indexConstant($constant, $filePath, $fileId, null, $fileTypeResolver);
         }

         foreach ($outlineIndexingVisitor->getGlobalDefines() as $define) {
             $this->indexConstant($define, $filePath, $fileId, null, $fileTypeResolver);
         }
     }

    /**
     * Indexes the specified structural element.
     *
     * @param array            $rawData
     * @param string           $filePath
     * @param int              $fileId
     * @param string           $fqcn
     * @param bool             $isBuiltin
     * @param FileTypeResolver $fileTypeResolver
     *
     * @return int The ID of the structural element.
     */
    protected function indexStructure(
        array $rawData,
        $filePath,
        $fileId,
        $fqcn,
        $isBuiltin,
        FileTypeResolver $fileTypeResolver
    ) {
        $structureTypeMap = $this->getStructureTypeMap();

        $documentation = $this->docblockParser->parse($rawData['docComment'], [
            DocblockParser::DEPRECATED,
            DocblockParser::ANNOTATION,
            DocblockParser::DESCRIPTION,
            DocblockParser::METHOD,
            DocblockParser::PROPERTY,
            DocblockParser::PROPERTY_READ,
            DocblockParser::PROPERTY_WRITE
        ], $rawData['name']);

        $seData = [
            'name'              => $rawData['name'],
            'fqcn'              => $fqcn,
            'file_id'           => $fileId,
            'start_line'        => $rawData['startLine'],
            'end_line'          => $rawData['endLine'],
            'structure_type_id' => $structureTypeMap[$rawData['type']],
            'is_abstract'       => (isset($rawData['isAbstract']) && $rawData['isAbstract']) ? 1 : 0,
            'is_final'          => (isset($rawData['isFinal']) && $rawData['isFinal']) ? 1 : 0,
            'is_deprecated'     => $documentation['deprecated'] ? 1 : 0,
            'is_annotation'     => $documentation['annotation'] ? 1 : 0,
            'is_builtin'        => $isBuiltin ? 1 : 0,
            'has_docblock'      => empty($rawData['docComment']) ? 0 : 1,
            'short_description' => $documentation['descriptions']['short'],
            'long_description'  => $documentation['descriptions']['long']
        ];

        $seId = $this->storage->insertStructure($seData);

        $accessModifierMap = $this->getAccessModifierMap();

        if (isset($rawData['parents'])) {
            foreach ($rawData['parents'] as $parent) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_PARENTS_LINKED, [
                    'structure_id'          => $seId,
                    'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($parent)
                ]);
            }
        }

        if (isset($rawData['interfaces'])) {
            foreach ($rawData['interfaces'] as $interface) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_INTERFACES_LINKED, [
                    'structure_id'          => $seId,
                    'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($interface)
                ]);
            }
        }

        if (isset($rawData['traits'])) {
            foreach ($rawData['traits'] as $trait) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_LINKED, [
                    'structure_id'          => $seId,
                    'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($trait)
                ]);
            }
        }

        if (isset($rawData['traitAliases'])) {
            foreach ($rawData['traitAliases'] as $traitAlias) {
                $accessModifier = $this->parseAccessModifier($traitAlias, true);

                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_ALIASES, [
                    'structure_id'         => $seId,
                    'trait_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($traitAlias['trait']),
                    'access_modifier_id'   => $accessModifier ? $accessModifierMap[$accessModifier] : null,
                    'name'                 => $traitAlias['name'],
                    'alias'                => $traitAlias['alias']
                ]);
            }
        }

        if (isset($rawData['traitPrecedences'])) {
            foreach ($rawData['traitPrecedences'] as $traitPrecedence) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_PRECEDENCES, [
                    'structure_id'         => $seId,
                    'trait_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($traitPrecedence['trait']),
                    'name'                 => $traitPrecedence['name']
                ]);
            }
        }

        foreach ($rawData['properties'] as $property) {
            $accessModifier = $this->parseAccessModifier($property);

            $this->indexProperty(
                $property,
                $filePath,
                $fileId,
                $seId,
                $accessModifierMap[$accessModifier],
                $fileTypeResolver
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
                $fileTypeResolver
            );
        }

        foreach ($rawData['constants'] as $constant) {
            $this->indexConstant(
                $constant,
                $filePath,
                $fileId,
                $seId,
                $fileTypeResolver
            );
        }

        // Index magic properties.
        $magicProperties = array_merge(
            $documentation['properties'],
            $documentation['propertiesReadOnly'],
            $documentation['propertiesWriteOnly']
        );

        foreach ($magicProperties as $propertyName => $propertyData) {
            // Use the same line as the class definition, it matters for e.g. type resolution.
            $propertyData['name'] = mb_substr($propertyName, 1);
            $propertyData['startLine'] = $propertyData['endLine'] = $rawData['startLine'];

            $this->indexMagicProperty(
                $propertyData,
                $fileId,
                $seId,
                $accessModifierMap['public'],
                $fileTypeResolver
            );
        }

        // Index magic methods.
        foreach ($documentation['methods'] as $methodName => $methodData) {
            // Use the same line as the class definition, it matters for e.g. type resolution.
            $methodData['name'] = $methodName;
            $methodData['startLine'] = $methodData['endLine'] = $rawData['startLine'];

            $this->indexMagicMethod(
                $methodData,
                $fileId,
                $seId,
                $accessModifierMap['public'],
                true,
                $fileTypeResolver
            );
        }

        return $seId;
    }

    /**
     * @param string           $typeSpecification
     * @param int              $line
     * @param FileTypeResolver $fileTypeResolver
     *
     * @return array[]
     */
    protected function getTypeDataForTypeSpecification($typeSpecification, $line, FileTypeResolver $fileTypeResolver)
    {
        $typeList = $this->typeAnalyzer->getTypesForTypeSpecification($typeSpecification);

        return $this->getTypeDataForTypeList($typeList, $line, $fileTypeResolver);
    }

    /**
     * @param string[]         $typeList
     * @param int              $line
     * @param FileTypeResolver $fileTypeResolver
     *
     * @return array[]
     */
    protected function getTypeDataForTypeList(array $typeList, $line, FileTypeResolver $fileTypeResolver)
    {
        $types = [];

        foreach ($typeList as $type) {
            $fqcn = $type;

            if ($this->typeAnalyzer->isClassType($type)) {
                $fqcn = $fileTypeResolver->resolve($type, $line);
            }

            $types[] = [
                'type' => $type,
                'fqcn' => $fqcn
            ];
        }

        return $types;
    }

    /**
     * Indexes the specified constant.
     *
     * @param array            $rawData
     * @param string           $filePath
     * @param int              $fileId
     * @param int|null         $seId
     * @param FileTypeResolver $fileTypeResolver
     */
    protected function indexConstant(
        array $rawData,
        $filePath,
        $fileId,
        $seId = null,
        FileTypeResolver $fileTypeResolver
    ) {
        $documentation = $this->docblockParser->parse($rawData['docComment'], [
            DocblockParser::VAR_TYPE,
            DocblockParser::DEPRECATED,
            DocblockParser::DESCRIPTION
        ], $rawData['name']);

        $varDocumentation = isset($documentation['var']['$' . $rawData['name']]) ?
            $documentation['var']['$' . $rawData['name']] :
            null;

        $shortDescription = $documentation['descriptions']['short'];

        $types = [];

        if ($varDocumentation) {
            // You can place documentation after the @var tag as well as at the start of the docblock. Fall back
            // from the latter to the former.
            if (!empty($varDocumentation['description'])) {
                $shortDescription = $varDocumentation['description'];
            }

            $types = $this->getTypeDataForTypeSpecification(
                $varDocumentation['type'],
                $rawData['startLine'],
                $fileTypeResolver
            );
        } elseif ($rawData['defaultValue']) {
            try {
                $typeList = $this->typeDeducer->deduceTypes(
                    $filePath,
                    $rawData['defaultValue'],
                    [$rawData['defaultValue']],
                    0
                );

                $types = $this->getTypeDataForTypeList($typeList, $rawData['startLine'], $fileTypeResolver);
            } catch (UnexpectedValueException $e) {
                $types = [];
            }
        }

        $constantId = $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
            'name'                  => $rawData['name'],
            'fqcn'                  => isset($rawData['fqcn']) ? $rawData['fqcn'] : null,
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
            'default_value'         => $rawData['defaultValue'],
            'is_builtin'            => 0,
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1,
            'short_description'     => $shortDescription,
            'long_description'      => $documentation['descriptions']['long'],
            'type_description'      => $varDocumentation ? $varDocumentation['description'] : null,
            'types_serialized'      => serialize($types),
            'structure_id'          => $seId
        ]);
    }

    /**
     * Indexes the specified property.
     *
     * @param array            $rawData
     * @param string           $filePath
     * @param int              $fileId
     * @param int              $seId
     * @param int              $amId
     * @param FileTypeResolver $fileTypeResolver
     */
    protected function indexProperty(
        array $rawData,
        $filePath,
        $fileId,
        $seId,
        $amId,
        FileTypeResolver $fileTypeResolver
    ) {
        $documentation = $this->docblockParser->parse($rawData['docComment'], [
            DocblockParser::VAR_TYPE,
            DocblockParser::DEPRECATED,
            DocblockParser::DESCRIPTION
        ], $rawData['name']);

        $varDocumentation = isset($documentation['var']['$' . $rawData['name']]) ?
            $documentation['var']['$' . $rawData['name']] :
            null;

        $shortDescription = $documentation['descriptions']['short'];

        $types = [];

        if ($varDocumentation) {
            // You can place documentation after the @var tag as well as at the start of the docblock. Fall back
            // from the latter to the former.
            if (!empty($varDocumentation['description'])) {
                $shortDescription = $varDocumentation['description'];
            }

            $types = $this->getTypeDataForTypeSpecification(
                $varDocumentation['type'],
                $rawData['startLine'],
                $fileTypeResolver
            );
        } elseif (isset($rawData['returnType'])) {
            $types = [
                [
                    'type' => $rawData['returnType'],
                    'fqcn' => isset($rawData['fullReturnType']) ? $rawData['fullReturnType'] : $rawData['returnType']
                ]
            ];
        } elseif ($rawData['defaultValue']) {
            try {
                $typeList = $this->typeDeducer->deduceTypes(
                    $filePath,
                    $rawData['defaultValue'],
                    [$rawData['defaultValue']],
                    0
                );

                $types = $this->getTypeDataForTypeList($typeList, $rawData['startLine'], $fileTypeResolver);
            } catch (UnexpectedValueException $e) {
                $types = [];
            }
        }

        $propertyId = $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
            'default_value'         => $rawData['defaultValue'],
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'is_magic'              => 0,
            'is_static'             => $rawData['isStatic'] ? 1 : 0,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1,
            'short_description'     => $shortDescription,
            'long_description'      => $documentation['descriptions']['long'],
            'type_description'      => $varDocumentation ? $varDocumentation['description'] : null,
            'structure_id'          => $seId,
            'access_modifier_id'    => $amId,
            'types_serialized'      => serialize($types)
        ]);
    }

    /**
     * @param array            $rawData
     * @param int              $fileId
     * @param int              $seId
     * @param int              $amId
     * @param FileTypeResolver $fileTypeResolver
     */
    protected function indexMagicProperty(
        array $rawData,
        $fileId,
        $seId,
        $amId,
        FileTypeResolver $fileTypeResolver
    ) {
        $types = [];

        if ($rawData['type']) {
            $types = $this->getTypeDataForTypeSpecification(
                $rawData['type'],
                $rawData['startLine'],
                $fileTypeResolver
            );
        }

        $propertyId = $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
            'default_value'         => null,
            'is_deprecated'         => 0,
            'is_magic'              => 1,
            'is_static'             => $rawData['isStatic'] ? 1 : 0,
            'has_docblock'          => 0,
            'short_description'     => $rawData['description'],
            'long_description'      => null,
            'type_description'      => null,
            'structure_id'          => $seId,
            'access_modifier_id'    => $amId,
            'types_serialized'      => serialize($types)
        ]);
    }

    /**
     * Indexes the specified function.
     *
     * @param array            $rawData
     * @param int              $fileId
     * @param int|null         $seId
     * @param int|null         $amId
     * @param bool             $isMagic
     * @param FileTypeResolver $fileTypeResolver
     */
    protected function indexFunction(
        array $rawData,
        $fileId,
        $seId = null,
        $amId = null,
        $isMagic = false,
        FileTypeResolver $fileTypeResolver
    ) {
        $documentation = $this->docblockParser->parse($rawData['docComment'], [
            DocblockParser::THROWS,
            DocblockParser::PARAM_TYPE,
            DocblockParser::DEPRECATED,
            DocblockParser::DESCRIPTION,
            DocblockParser::RETURN_VALUE
        ], $rawData['name']);

        $returnTypes = [];

        if ($documentation && $documentation['return']['type']) {
            $returnTypes = $this->getTypeDataForTypeSpecification(
                $documentation['return']['type'],
                $rawData['startLine'],
                $fileTypeResolver
            );
        } elseif (isset($rawData['returnType'])) {
            $returnTypes = [
                [
                    'type' => $rawData['returnType'],
                    'fqcn' => isset($rawData['fullReturnType']) ? $rawData['fullReturnType'] : $rawData['returnType']
                ]
            ];
        }

        $shortDescription = $documentation['descriptions']['short'];

        $throws = [];

        foreach ($documentation['throws'] as $type => $description) {
            $typeData = $this->getTypeDataForTypeSpecification($type, $rawData['startLine'], $fileTypeResolver);
            $typeData = array_shift($typeData);

            $throwsData = [
                'type'        => $typeData['type'],
                'full_type'   => $typeData['fqcn'],
                'description' => $description ?: null
            ];

            $throws[] = $throwsData;
        }

        $parameters = [];

        foreach ($rawData['parameters'] as $parameter) {
            $parameterKey = '$' . $parameter['name'];
            $parameterDoc = isset($documentation['params'][$parameterKey]) ?
                $documentation['params'][$parameterKey] : null;

            $types = [];

            if ($parameterDoc) {
                $types = $this->getTypeDataForTypeSpecification(
                    $parameterDoc['type'],
                    $rawData['startLine'],
                    $fileTypeResolver
                );
            } elseif (isset($parameter['type'])) {
                $types = [
                    [
                        'type' => $parameter['type'],
                        'fqcn' => isset($parameter['fullType']) ? $parameter['fullType'] : $parameter['type']
                    ]
                ];

                if ($parameter['isNullable']) {
                    $types[] = [
                        'type' => 'null',
                        'fqcn' => 'null'
                    ];
                }
            }

            $parameters[] = [
                'name'             => $parameter['name'],
                'type_hint'        => $parameter['type'],
                'types_serialized' => serialize($types),
                'description'      => $parameterDoc ? $parameterDoc['description'] : null,
                'default_value'    => $parameter['defaultValue'],
                'is_nullable'      => $parameter['isNullable'] ? 1 : 0,
                'is_reference'     => $parameter['isReference'] ? 1 : 0,
                'is_optional'      => $parameter['isOptional'] ? 1 : 0,
                'is_variadic'      => $parameter['isVariadic'] ? 1 : 0
            ];
        }

        $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, [
            'name'                    => $rawData['name'],
            'fqcn'                    => isset($rawData['fqcn']) ? $rawData['fqcn'] : null,
            'file_id'                 => $fileId,
            'start_line'              => $rawData['startLine'],
            'end_line'                => $rawData['endLine'],
            'is_builtin'              => 0,
            'is_abstract'             => (isset($rawData['isAbstract']) && $rawData['isAbstract']) ? 1 : 0,
            'is_final'                => (isset($rawData['isFinal']) && $rawData['isFinal']) ? 1 : 0,
            'is_deprecated'           => $documentation['deprecated'] ? 1 : 0,
            'short_description'       => $shortDescription,
            'long_description'        => $documentation['descriptions']['long'],
            'return_description'      => $documentation['return']['description'],
            'return_type_hint'        => $rawData['returnType'],
            'structure_id'            => $seId,
            'access_modifier_id'      => $amId,
            'is_magic'                => $isMagic ? 1 : 0,
            'is_static'               => isset($rawData['isStatic']) ? ($rawData['isStatic'] ? 1 : 0) : 0,
            'has_docblock'            => empty($rawData['docComment']) ? 0 : 1,
            'throws_serialized'       => serialize($throws),
            'parameters_serialized'   => serialize($parameters),
            'return_types_serialized' => serialize($returnTypes)
        ]);

        foreach ($parameters as $parameter) {
            $parameter['function_id'] = $functionId;

            $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, $parameter);
        }
    }

    /**
     * @param array            $rawData
     * @param int              $fileId
     * @param int|null         $seId
     * @param int|null         $amId
     * @param bool             $isMagic
     * @param FileTypeResolver $fileTypeResolver
     */
    protected function indexMagicMethod(
        array $rawData,
        $fileId,
        $seId = null,
        $amId = null,
        $isMagic = false,
        FileTypeResolver $fileTypeResolver
    ) {
        $returnTypes = [];

        if ($rawData['type']) {
            $returnTypes = $this->getTypeDataForTypeSpecification(
                $rawData['type'],
                $rawData['startLine'],
                $fileTypeResolver
            );
        }

        $parameters = [];

        foreach ($rawData['requiredParameters'] as $parameterName => $parameter) {
            $types = [];

            if ($parameter['type']) {
                $types = $this->getTypeDataForTypeSpecification(
                    $parameter['type'],
                    $rawData['startLine'],
                    $fileTypeResolver
                );
            }

            $parameters[] = [
                'name'             => mb_substr($parameterName, 1),
                'type_hint'        => null,
                'types_serialized' => serialize($types),
                'description'      => null,
                'default_value'    => null,
                'is_nullable'      => 0,
                'is_reference'     => 0,
                'is_optional'      => 0,
                'is_variadic'      => 0
            ];
        }

        foreach ($rawData['optionalParameters'] as $parameterName => $parameter) {
            $types = [];

            if ($parameter['type']) {
                $types = $this->getTypeDataForTypeSpecification(
                    $parameter['type'],
                    $rawData['startLine'],
                    $fileTypeResolver
                );
            }

            $parameters[] = [
                'name'             => mb_substr($parameterName, 1),
                'type_hint'        => null,
                'types_serialized' => serialize($types),
                'description'      => null,
                'default_value'    => null,
                'is_nullable'      => 0,
                'is_reference'     => 0,
                'is_optional'      => 1,
                'is_variadic'      => 0,
            ];
        }

        $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, [
            'name'                    => $rawData['name'],
            'fqcn'                    => null,
            'file_id'                 => $fileId,
            'start_line'              => $rawData['startLine'],
            'end_line'                => $rawData['endLine'],
            'is_builtin'              => 0,
            'is_abstract'             => 0,
            'is_deprecated'           => 0,
            'short_description'       => $rawData['description'],
            'long_description'        => null,
            'return_description'      => null,
            'return_type_hint'        => null,
            'structure_id'            => $seId,
            'access_modifier_id'      => $amId,
            'is_magic'                => 1,
            'is_static'               => $rawData['isStatic'] ? 1 : 0,
            'has_docblock'            => 0,
            'throws_serialized'       => serialize([]),
            'parameters_serialized'   => serialize($parameters),
            'return_types_serialized' => serialize($returnTypes)
        ]);

        foreach ($parameters as $parameter) {
            $parameter['function_id'] = $functionId;

            $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, $parameter);
        }
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
        return $this->parser;
    }
}
