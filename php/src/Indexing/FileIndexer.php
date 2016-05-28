<?php

namespace PhpIntegrator\Indexing;

use DateTime;
use Exception;
use UnexpectedValueException;

use PhpIntegrator\DocParser;
use PhpIntegrator\TypeResolver;
use PhpIntegrator\TypeAnalyzer;

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
class FileIndexer
{
    /**
     * The storage to use for index data.
     *
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var DocParser
     */
    protected $docParser;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var ParserFactory
     */
    protected $parserFactory;

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
     * @param DocParser        $docParser
     * @param ParserFactory    $parserFactory
     */
    public function __construct(
        StorageInterface $storage,
        TypeAnalyzer $typeAnalyzer,
        DocParser $docParser,
        ParserFactory $parserFactory
    ) {
        $this->storage = $storage;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->docParser = $docParser;
        $this->parserFactory = $parserFactory;
    }

    /**
     * Indexes the specified file.
     *
     * @param string      $filePath
     * @param string|null $code     The source code of the file. If null, will be fetched automatically.
     */
    public function index($filePath, $code = null)
    {
        $code = $code ?: @file_get_contents($filePath);

        if (!is_string($code)) {
            throw new IndexingFailedException();
        }

        try {
            $nodes = $this->getParser()->parse($code);

            if ($nodes === null) {
                throw new Error('Unknown syntax error encountered');
            }

            $outlineIndexingVisitor = new Visitor\OutlineIndexingVisitor($code);
            $useStatementFetchingVisitor = new Visitor\UseStatementFetchingVisitor();

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
            $this->indexVisitorResults($fileId, $outlineIndexingVisitor, $useStatementFetchingVisitor);

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
     * @param int                                 $fileId
     * @param Visitor\OutlineIndexingVisitor      $outlineIndexingVisitor
     * @param Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor
     */
    protected function indexVisitorResults(
        $fileId,
        Visitor\OutlineIndexingVisitor $outlineIndexingVisitor,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor
    ) {
         foreach ($outlineIndexingVisitor->getStructures() as $fqcn => $structure) {
             $this->indexStructure(
                 $structure,
                 $fileId,
                 $fqcn,
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
                     'fqcn'              => $useStatement['fqcn'],
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
     * @param string                                   $fqcn
     * @param bool                                     $isBuiltin
     * @param Visitor\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     *
     * @return int The ID of the structural element.
     */
    protected function indexStructure(
        array $rawData,
        $fileId,
        $fqcn,
        $isBuiltin,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $structureTypeMap = $this->getStructureTypeMap();

        $documentation = $this->docParser->parse($rawData['docComment'], [
            DocParser::DEPRECATED,
            DocParser::ANNOTATION,
            DocParser::DESCRIPTION,
            DocParser::METHOD,
            DocParser::PROPERTY,
            DocParser::PROPERTY_READ,
            DocParser::PROPERTY_WRITE
        ], $rawData['name']);

        $seData = [
            'name'              => $rawData['name'],
            'fqcn'              => $fqcn,
            'file_id'           => $fileId,
            'start_line'        => $rawData['startLine'],
            'end_line'          => $rawData['endLine'],
            'structure_type_id' => $structureTypeMap[$rawData['type']],
            'is_abstract'       => (isset($rawData['isAbstract']) && $rawData['isAbstract']) ? 1 : 0,
            'is_deprecated'     => $documentation['deprecated'] ? 1 : 0,
            'is_annotation'     => $documentation['annotation'] ? 1 : 0,
            'is_builtin'        => $isBuiltin ? 1 : 0,
            'has_docblock'      => empty($rawData['docComment']) ? 0 : 1,
            'short_description' => $documentation['descriptions']['short'],
            'long_description'  => $documentation['descriptions']['long']
        ];

        $seId = $this->storage->insert(IndexStorageItemEnum::STRUCTURES, $seData);

        $accessModifierMap = $this->getAccessModifierMap();

        if (isset($rawData['parents'])) {
            foreach ($rawData['parents'] as $parent) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_PARENTS_LINKED, [
                    'structure_id'           => $seId,
                    'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($parent)
                ]);
            }
        }

        if (isset($rawData['interfaces'])) {
            foreach ($rawData['interfaces'] as $interface) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_INTERFACES_LINKED, [
                    'structure_id'           => $seId,
                    'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($interface)
                ]);
            }
        }

        if (isset($rawData['traits'])) {
            foreach ($rawData['traits'] as $trait) {
                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_LINKED, [
                    'structure_id'           => $seId,
                    'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($trait)
                ]);
            }
        }

        if (isset($rawData['traitAliases'])) {
            foreach ($rawData['traitAliases'] as $traitAlias) {
                $accessModifier = $this->parseAccessModifier($traitAlias, true);

                $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_ALIASES, [
                    'structure_id'          => $seId,
                    'trait_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($traitAlias['trait']),
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
                    'trait_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($traitPrecedence['trait']),
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
            // Use the same line as the class definition, it matters for e.g. type resolution.
            $propertyData['name'] = mb_substr($propertyName, 1);
            $propertyData['startLine'] = $propertyData['endLine'] = $rawData['startLine'];

            $this->indexMagicProperty(
                $propertyData,
                $fileId,
                $seId,
                $accessModifierMap['public'],
                $useStatementFetchingVisitor
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
                $useStatementFetchingVisitor
            );
        }

        return $seId;
    }

    /**
     * @param string                              $typeSpecification
     * @param int                                 $line
     * @param Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor
     *
     * @return array[]
     */
    protected function getTypeDataForTypeSpecification(
        $typeSpecification,
        $line,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor
    ) {
        $types = [];
        $typeList = $this->typeAnalyzer->getTypesForTypeSpecification($typeSpecification);

        foreach ($typeList as $type) {
            $fqcn = $type;

            if ($this->typeAnalyzer->isClassType($type)) {
                $fqcn = $this->getFqcnForLocalType($type, $line, $useStatementFetchingVisitor);
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
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param int|null                                 $seId
     * @param Visitor\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     */
    protected function indexConstant(
        array $rawData,
        $fileId,
        $seId = null,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $documentation = $this->docParser->parse($rawData['docComment'], [
            DocParser::VAR_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION
        ], $rawData['name']);

        $types = [];

        if ($documentation['var']['type']) {
            $types = $this->getTypeDataForTypeSpecification(
                $documentation['var']['type'],
                $rawData['startLine'],
                $useStatementFetchingVisitor
            );
        }

        $constantId = $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
            'name'                  => $rawData['name'],
            'fqcn'                 => isset($rawData['fqcn']) ? $rawData['fqcn'] : null,
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
            'is_builtin'            => 0,
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1,
            'short_description'     => $documentation['descriptions']['short'],
            'long_description'      => $documentation['descriptions']['long'],
            'type_description'      => $documentation['var']['description'],
            'types_serialized'      => serialize($types),
            'structure_id'          => $seId
        ]);
    }

    /**
     * Indexes the specified property.
     *
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param int                                      $seId
     * @param int                                      $amId
     * @param Visitor\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     */
    protected function indexProperty(
        array $rawData,
        $fileId,
        $seId,
        $amId,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $documentation = $this->docParser->parse($rawData['docComment'], [
            DocParser::VAR_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION
        ], $rawData['name']);

        $shortDescription = $documentation['descriptions']['short'];

        // You can place documentation after the @var tag as well as at the start of the docblock. Fall back
        // from the latter to the former.
        if (!empty($documentation['var']['description'])) {
            $shortDescription = $documentation['var']['description'];
        }

        $types = [];

        if ($documentation && $documentation['var']['type']) {
            $types = $this->getTypeDataForTypeSpecification(
                $documentation['var']['type'],
                $rawData['startLine'],
                $useStatementFetchingVisitor
            );
        } elseif (isset($rawData['returnType'])) {
            $types = [
                [
                    'type' => $rawData['returnType'],
                    'fqcn' => isset($rawData['fullReturnType']) ? $rawData['fullReturnType'] : $rawData['returnType']
                ]
            ];
        }

        $propertyId = $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
            'is_deprecated'         => $documentation['deprecated'] ? 1 : 0,
            'is_magic'              => 0,
            'is_static'             => $rawData['isStatic'] ? 1 : 0,
            'has_docblock'          => empty($rawData['docComment']) ? 0 : 1,
            'short_description'     => $shortDescription,
            'long_description'      => $documentation['descriptions']['long'],
            'type_description'      => $documentation['var']['description'],
            'structure_id'          => $seId,
            'access_modifier_id'    => $amId,
            'types_serialized'      => serialize($types)
        ]);
    }

    /**
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param int                                      $seId
     * @param int                                      $amId
     * @param Visitor\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     */
    protected function indexMagicProperty(
        array $rawData,
        $fileId,
        $seId,
        $amId,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $types = [];

        if ($rawData['type']) {
            $types = $this->getTypeDataForTypeSpecification(
                $rawData['type'],
                $rawData['startLine'],
                $useStatementFetchingVisitor
            );
        }

        $propertyId = $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
            'name'                  => $rawData['name'],
            'file_id'               => $fileId,
            'start_line'            => $rawData['startLine'],
            'end_line'              => $rawData['endLine'],
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
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param int|null                                 $seId
     * @param int|null                                 $amId
     * @param bool                                     $isMagic
     * @param Visitor\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     */
    protected function indexFunction(
        array $rawData,
        $fileId,
        $seId = null,
        $amId = null,
        $isMagic = false,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $documentation = $this->docParser->parse($rawData['docComment'], [
            DocParser::THROWS,
            DocParser::PARAM_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION,
            DocParser::RETURN_VALUE
        ], $rawData['name']);

        $returnTypes = [];

        if ($documentation && $documentation['return']['type']) {
            $returnTypes = $this->getTypeDataForTypeSpecification(
                $documentation['return']['type'],
                $rawData['startLine'],
                $useStatementFetchingVisitor
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
            $typeData = $this->getTypeDataForTypeSpecification($type, $rawData['startLine'], $useStatementFetchingVisitor);
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
                    $useStatementFetchingVisitor
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
            $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, $parameter);
        }
    }

    /**
     * @param array                                    $rawData
     * @param int                                      $fileId
     * @param int|null                                 $seId
     * @param int|null                                 $amId
     * @param bool                                     $isMagic
     * @param Visitor\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     */
    protected function indexMagicMethod(
        array $rawData,
        $fileId,
        $seId = null,
        $amId = null,
        $isMagic = false,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
    ) {
        $returnTypes = [];

        if ($rawData['type']) {
            $returnTypes = $this->getTypeDataForTypeSpecification(
                $rawData['type'],
                $rawData['startLine'],
                $useStatementFetchingVisitor
            );
        }

        $parameters = [];

        foreach ($rawData['requiredParameters'] as $parameterName => $parameter) {
            $types = [];

            if ($parameter['type']) {
                $types = $this->getTypeDataForTypeSpecification(
                    $parameter['type'],
                    $rawData['startLine'],
                    $useStatementFetchingVisitor
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
                    $useStatementFetchingVisitor
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
            'fqcn'                   => null,
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
     * Resolves and determines the FQCN of the specified type.
     *
     * @param string                                   $type
     * @param int                                      $line
     * @param Visitor\UseStatementFetchingVisitor|null $useStatementFetchingVisitor
     *
     * @return string|null
     */
    protected function getFqcnForLocalType(
        $type,
        $line,
        Visitor\UseStatementFetchingVisitor $useStatementFetchingVisitor = null
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

        return $typeResolver->resolve($type);
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

            $this->parser = $this->parserFactory->create(ParserFactory::PREFER_PHP7, $lexer, [
                'throwOnError' => false
            ]);
        }

        return $this->parser;
    }
}
