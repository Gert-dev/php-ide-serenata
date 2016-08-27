<?php

namespace PhpIntegrator\UserInterface;

use ArrayAccess;
use ArrayObject;
use Traversable;
use UnexpectedValueException;

use PhpIntegrator\Analysis\DocblockAnalyzer;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Parsing\DocblockParser;

/**
 * Adapts and resolves data from the index as needed to receive an appropriate output data format.
 */
class IndexDataAdapter implements IndexDataAdapterInterface
{
    /**
     * The storage to use for accessing index data.
     *
     * @var IndexDataAdapterProviderInterface
     */
    protected $storage;

    /**
     * @var DocblockAnalyzer
     */
    protected $docblockAnalyzer;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var string[]
     */
    protected $resolutionStack = [];

    /**
     * Constructor.
     *
     * @param IndexDataAdapterProviderInterface $storage
     */
    public function __construct(IndexDataAdapterProviderInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Retrieves information about the specified structural element.
     *
     * @param string $fqcn
     *
     * @throws UnexpectedValueException
     *
     * @return array
     */
    public function getStructureInfo($fqcn)
    {
        $this->resolutionStack = [$fqcn];

        return $this->getDirectStructureInfo($fqcn);
    }

    /**
     * @param string $fqcn
     *
     * @throws UnexpectedValueException
     *
     * @return array
     */
    protected function getDirectStructureInfo($fqcn)
    {
        $rawInfo = $this->storage->getStructureRawInfo($fqcn);

        if (!$rawInfo) {
            throw new UnexpectedValueException('The structural element "' . $fqcn . '" was not found!');
        }

        $id = $rawInfo['id'];

        return $this->resolveStructure(
            $rawInfo,
            $this->storage->getStructureRawParents($id),
            $this->storage->getStructureRawChildren($id),
            $this->storage->getStructureRawInterfaces($id),
            $this->storage->getStructureRawImplementors($id),
            $this->storage->getStructureRawTraits($id),
            $this->storage->getStructureRawTraitUsers($id),
            $this->storage->getStructureRawConstants($id),
            $this->storage->getStructureRawProperties($id),
            $this->storage->getStructureRawMethods($id)
        );
    }

    /**
     * @param string $fqcn
     * @param string $originFqcn
     *
     * @return array
     */
    protected function getCheckedStructureInfo($fqcn, $originFqcn)
    {
        if (in_array($fqcn, $this->resolutionStack)) {
            throw new CircularDependencyException(
                "Circular dependency detected from {$originFqcn} to {$fqcn}!"
            );
        }

        $this->resolutionStack[] = $fqcn;

        $data = $this->getDirectStructureInfo($fqcn);

        array_pop($this->resolutionStack);

        return $data;
    }

    /**
     * Resolves structural element information from the specified raw data.
     *
     * @param array|ArrayAccess $element
     * @param array|Traversable $parents
     * @param array|Traversable $children
     * @param array|Traversable $interfaces
     * @param array|Traversable $implementors
     * @param array|Traversable $traits
     * @param array|Traversable $traitUsers
     * @param array|Traversable $constants
     * @param array|Traversable $properties
     * @param array|Traversable $methods
     *
     * @return array
     */
    public function resolveStructure(
        $element,
        $parents,
        $children,
        $interfaces,
        $implementors,
        $traits,
        $traitUsers,
        $constants,
        $properties,
        $methods
    ) {
        $result = new ArrayObject([
            'name'               => $element['fqcn'],
            'startLine'          => (int) $element['start_line'],
            'endLine'            => (int) $element['end_line'],
            'shortName'          => $element['name'],
            'filename'           => $element['path'],
            'type'               => $element['type_name'],
            'isAbstract'         => !!$element['is_abstract'],
            'isFinal'            => !!$element['is_final'],
            'isBuiltin'          => !!$element['is_builtin'],
            'isDeprecated'       => !!$element['is_deprecated'],
            'isAnnotation'       => !!$element['is_annotation'],
            'hasDocblock'        => !!$element['has_docblock'],
            'hasDocumentation'   => !!$element['has_docblock'],

            'shortDescription'   => $element['short_description'],
            'longDescription'    => $element['long_description'],

            'parents'            => [],
            'interfaces'         => [],
            'traits'             => [],

            'directParents'      => [],
            'directInterfaces'   => [],
            'directTraits'       => [],
            'directChildren'     => [],
            'directImplementors' => [],
            'directTraitUsers'   => [],

            'constants'          => [],
            'properties'         => [],
            'methods'            => []
        ]);

        $this->parseChildrenData($result, $children);
        $this->parseImplementorsData($result, $implementors);
        $this->parseTraitUsersData($result, $traitUsers);

        $this->parseParentData($result, $parents);
        $this->parseInterfaceData($result, $interfaces);
        $this->parseTraitData($result, $traits, $element);

        $this->parseConstantData($result, $constants, $element);
        $this->parsePropertyData($result, $properties, $element);
        $this->parseMethodData($result, $methods, $element);

        $this->resolveSpecialTypes($result, $element['fqcn']);

        return $result->getArrayCopy();
    }

    /**
     * @param ArrayObject       $result
     * @param array|Traversable $constants
     * @param array|ArrayAccess $element
     */
    protected function parseConstantData(ArrayObject $result, $constants, $element)
    {
        foreach ($constants as $rawConstantData) {
            $result['constants'][$rawConstantData['name']] = array_merge($this->getConstantInfo($rawConstantData), [
                'declaringClass' => [
                    'name'      => $element['fqcn'],
                    'filename'  => $element['path'],
                    'startLine' => (int) $element['start_line'],
                    'endLine'   => (int) $element['end_line'],
                    'type'      => $element['type_name'],
                ],

                'declaringStructure' => [
                    'name'            => $element['fqcn'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                    'startLineMember' => (int) $rawConstantData['start_line'],
                    'endLineMember'   => (int) $rawConstantData['end_line']
                ]
            ]);
        }
    }

    /**
     * @param ArrayObject       $result
     * @param array|Traversable $properties
     * @param array|ArrayAccess $element
     */
    protected function parsePropertyData(ArrayObject $result, $properties, $element)
    {
        foreach ($properties as $rawPropertyData) {
            $inheritedData = [];
            $existingProperty = null;
            $overriddenPropertyData = null;

            $property = $this->getPropertyInfo($rawPropertyData);

            if (isset($result['properties'][$property['name']])) {
                $existingProperty = $result['properties'][$property['name']];

                $overriddenPropertyData = [
                    'declaringClass'     => $existingProperty['declaringClass'],
                    'declaringStructure' => $existingProperty['declaringStructure'],
                    'startLine'          => (int) $existingProperty['startLine'],
                    'endLine'            => (int) $existingProperty['endLine']
                ];

                if ($this->isInheritingDocumentation($property)) {
                    $inheritedData = $this->extractInheritedPropertyInfo($existingProperty);
                }
            }

            $resultingProperty = array_merge($property, $inheritedData, [
                'override'       => $overriddenPropertyData,

                'declaringClass' => [
                    'name'            => $element['fqcn'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                ],

                'declaringStructure' => [
                    'name'            => $element['fqcn'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                    'startLineMember' => (int) $rawPropertyData['start_line'],
                    'endLineMember'   => (int) $rawPropertyData['end_line']
                ]
            ]);

            if ($existingProperty) {
                $resultingProperty['longDescription'] = $this->resolveInheritDoc(
                    $resultingProperty['longDescription'],
                    $existingProperty['longDescription']
                );
            }

            $result['properties'][$property['name']] = $resultingProperty;
        }
    }

    /**
     * @param ArrayObject       $result
     * @param array|Traversable $methods
     * @param array|ArrayAccess $element
     */
    protected function parseMethodData(ArrayObject $result, $methods, $element)
    {
        foreach ($methods as $rawMethodData) {
            $inheritedData = [];
            $existingMethod = null;
            $overriddenMethodData = null;
            $implementedMethodData = null;

            $method = $this->getMethodInfo($rawMethodData);

            if (isset($result['methods'][$method['name']])) {
                $existingMethod = $result['methods'][$method['name']];

                if ($existingMethod['declaringStructure']['type'] === 'interface') {
                    $implementedMethodData = [
                        'declaringClass'     => $existingMethod['declaringClass'],
                        'declaringStructure' => $existingMethod['declaringStructure'],
                        'startLine'          => (int) $existingMethod['startLine'],
                        'endLine'            => (int) $existingMethod['endLine']
                    ];
                } else {
                    $overriddenMethodData = [
                        'declaringClass'     => $existingMethod['declaringClass'],
                        'declaringStructure' => $existingMethod['declaringStructure'],
                        'startLine'          => (int) $existingMethod['startLine'],
                        'endLine'            => (int) $existingMethod['endLine'],
                        'wasAbstract'        => (bool) $existingMethod['isAbstract']
                    ];
                }

                if ($this->isInheritingDocumentation($method)) {
                    $inheritedData = $this->extractInheritedMethodInfo($existingMethod, $method);
                }
            }

            $resultingMethod = array_merge($method, $inheritedData, [
                'override'       => $overriddenMethodData,
                'implementation' => $implementedMethodData,

                'declaringClass' => [
                    'name'            => $element['fqcn'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                ],

                'declaringStructure' => [
                    'name'            => $element['fqcn'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                    'startLineMember' => (int) $rawMethodData['start_line'],
                    'endLineMember'   => (int) $rawMethodData['end_line']
                ]
            ]);

            if ($existingMethod) {
                $resultingMethod['longDescription'] = $this->resolveInheritDoc(
                    $resultingMethod['longDescription'],
                    $existingMethod['longDescription']
                );
            }

            $result['methods'][$method['name']] = $resultingMethod;
        }
    }

    /**
     * @param ArrayObject       $result
     * @param array|Traversable $children
     */
    protected function parseChildrenData(ArrayObject $result, $children)
    {
        foreach ($children as $child) {
            $result['directChildren'][] = $child['fqcn'];
        }
    }

    /**
     * @param ArrayObject       $result
     * @param array|Traversable $implementors
     */
    protected function parseImplementorsData(ArrayObject $result, $implementors)
    {
        foreach ($implementors as $implementor) {
            $result['directImplementors'][] = $implementor['fqcn'];
        }
    }

    /**
     * @param ArrayObject       $result
     * @param array|Traversable $traitUsers
     */
    protected function parseTraitUsersData(ArrayObject $result, $traitUsers)
    {
        foreach ($traitUsers as $trait) {
            $result['directTraitUsers'][] = $trait['fqcn'];
        }
    }

    /**
     * Takes all members from base classes and attaches them to the result data.
     *
     * @param ArrayObject       $result
     * @param array|Traversable $parents One or more base classes to inherit from (interfaces can have multiple parents).
     */
    protected function parseParentData(ArrayObject $result, $parents)
    {
        foreach ($parents as $parent) {
            $parentInfo = $this->getCheckedStructureInfo($parent['fqcn'], $result['name']);

            if ($parentInfo) {
                if (!$result['shortDescription']) {
                    $result['shortDescription'] = $parentInfo['shortDescription'];
                }

                if (!$result['longDescription']) {
                    $result['longDescription'] = $parentInfo['longDescription'];
                } else {
                    $result['longDescription'] = $this->resolveInheritDoc(
                        $result['longDescription'],
                        $parentInfo['longDescription']
                    );
                }

                $result['hasDocumentation'] = $result['hasDocumentation'] || $parentInfo['hasDocumentation'];

                $result['constants']  = array_merge($result['constants'], $parentInfo['constants']);
                $result['properties'] = array_merge($result['properties'], $parentInfo['properties']);
                $result['methods']    = array_merge($result['methods'], $parentInfo['methods']);

                $result['traits']     = array_merge($result['traits'], $parentInfo['traits']);
                $result['interfaces'] = array_merge($result['interfaces'], $parentInfo['interfaces']);
                $result['parents']    = array_merge($result['parents'], [$parentInfo['name']], $parentInfo['parents']);

                $result['directParents'][] = $parentInfo['name'];
            }
        }
    }

    /**
     * Appends members from direct interfaces to the pool of members. These only supply additional members, but will
     * never overwrite any existing members as they have a lower priority than inherited members.
     *
     * @param ArrayObject       $result
     * @param array|Traversable $interfaces
     */
    protected function parseInterfaceData(ArrayObject $result, $interfaces)
    {
        foreach ($interfaces as $interface) {
            $interface = $this->getCheckedStructureInfo($interface['fqcn'], $result['name']);

            $result['interfaces'][] = $interface['name'];
            $result['directInterfaces'][] = $interface['name'];

            foreach ($interface['constants'] as $constant) {
                if (!isset($result['constants'][$constant['name']])) {
                    $result['constants'][$constant['name']] = $constant;
                }
            }

            foreach ($interface['methods'] as $method) {
                if (!isset($result['methods'][$method['name']])) {
                    $result['methods'][$method['name']] = $method;
                }
            }
        }
    }

    /**
     * @param ArrayObject       $result
     * @param array|Traversable $traits
     * @param array             $element
     *
     * @return array
     */
    protected function parseTraitData(ArrayObject $result, $traits, $element)
    {
        if (empty($traits)) {
            return;
        }

        $traitAliases = $this->storage->getStructureTraitAliasesAssoc($element['id']);
        $traitPrecedences = $this->storage->getStructureTraitPrecedencesAssoc($element['id']);

        foreach ($traits as $trait) {
            $trait = $this->getCheckedStructureInfo($trait['fqcn'], $result['name']);

            $result['traits'][] = $trait['name'];
            $result['directTraits'][] = $trait['name'];

            foreach ($trait['properties'] as $property) {
                $inheritedData = [];
                $existingProperty = null;

                if (isset($result['properties'][$property['name']])) {
                    $existingProperty = $result['properties'][$property['name']];

                    if ($this->isInheritingDocumentation($property)) {
                        $inheritedData = $this->extractInheritedPropertyInfo($existingProperty);
                    }
                }

                $resultingProperty = array_merge($property, $inheritedData, [
                    'declaringClass' => [
                        'name'            => $element['fqcn'],
                        'filename'        => $element['path'],
                        'startLine'       => (int) $element['start_line'],
                        'endLine'         => (int) $element['end_line'],
                        'type'            => $element['type_name'],
                    ]
                ]);

                if ($existingProperty) {
                    $resultingProperty['longDescription'] = $this->resolveInheritDoc(
                        $resultingProperty['longDescription'],
                        $existingProperty['longDescription']
                    );
                }

                $result['properties'][$property['name']] = $resultingProperty;
            }

            foreach ($trait['methods'] as $method) {
                if (isset($traitAliases[$method['name']])) {
                    $alias = $traitAliases[$method['name']];

                    if ($alias['trait_fqcn'] === null || $alias['trait_fqcn'] === $trait['name']) {
                        $method['name']        = $alias['alias'] ?: $method['name'];
                        $method['isPublic']    = ($alias['access_modifier'] === 'public');
                        $method['isProtected'] = ($alias['access_modifier'] === 'protected');
                        $method['isPrivate']   = ($alias['access_modifier'] === 'private');
                    }
                }

                $inheritedData = [];
                $existingMethod = null;

                if (isset($result['methods'][$method['name']])) {
                    $existingMethod = $result['methods'][$method['name']];

                    if ($existingMethod['declaringStructure']['type'] === 'trait') {
                        if (isset($traitPrecedences[$method['name']])) {
                            if ($traitPrecedences[$method['name']]['trait_fqcn'] !== $trait['name']) {
                                // The method is present in multiple used traits and precedences indicate that the one
                                // from this trait should not be imported.
                                continue;
                            }
                        }
                    }

                    if ($this->isInheritingDocumentation($method)) {
                        $inheritedData = $this->extractInheritedMethodInfo($existingMethod, $method);
                    }
                }

                $resultingMethod = array_merge($method, $inheritedData, [
                    'declaringClass' => [
                        'name'      => $element['fqcn'],
                        'filename'  => $element['path'],
                        'startLine' => (int) $element['start_line'],
                        'endLine'   => (int) $element['end_line'],
                        'type'      => $element['type_name'],
                    ]
                ]);

                if ($existingMethod) {
                    $resultingMethod['longDescription'] = $this->resolveInheritDoc(
                        $resultingMethod['longDescription'],
                        $existingMethod['longDescription']
                    );
                }

                $result['methods'][$method['name']] = $resultingMethod;
            }
        }
    }

    /**
     * @param ArrayObject $result
     * @param string      $elementFqcn
     */
    protected function resolveSpecialTypes(ArrayObject $result, $elementFqcn)
    {
        $typeAnalyzer = $this->getTypeAnalyzer();

        $doResolveTypes = function (array &$type) use ($elementFqcn, $typeAnalyzer) {
            if ($type['type'] === 'self') {
                // self takes the type from the classlike it is first resolved in, so only resolve it once to ensure
                // that it doesn't get overwritten.
                if ($type['resolvedType'] === 'self') {
                    $type['resolvedType'] = $typeAnalyzer->getNormalizedFqcn($elementFqcn);
                }
            } elseif ($type['type'] === '$this' || $type['type'] === 'static') {
                $type['resolvedType'] = $typeAnalyzer->getNormalizedFqcn($elementFqcn);
            } elseif ($typeAnalyzer->isClassType($type['fqcn'])) {
                $type['resolvedType'] = $typeAnalyzer->getNormalizedFqcn($type['fqcn']);
            } else {
                $type['resolvedType'] = $type['fqcn'];
            }
        };

        foreach ($result['methods'] as $name => &$method) {
            foreach ($method['parameters'] as &$parameter) {
                foreach ($parameter['types'] as &$type) {
                    $doResolveTypes($type);
                }
            }

            foreach ($method['returnTypes'] as &$returnType) {
                $doResolveTypes($returnType);
            }
        }

        foreach ($result['properties'] as $name => &$property) {
            foreach ($property['types'] as &$type) {
                $doResolveTypes($type);
            }
        }

        foreach ($result['constants'] as $name => &$constants) {
            foreach ($constants['types'] as &$type) {
                $doResolveTypes($type);
            }
        }
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function getMethodInfo(array $rawInfo)
    {
        return array_merge($this->getFunctionInfo($rawInfo), [
            'isMagic'            => !!$rawInfo['is_magic'],
            'isPublic'           => ($rawInfo['access_modifier'] === 'public'),
            'isProtected'        => ($rawInfo['access_modifier'] === 'protected'),
            'isPrivate'          => ($rawInfo['access_modifier'] === 'private'),
            'isStatic'           => !!$rawInfo['is_static'],
            'isAbstract'         => !!$rawInfo['is_abstract'],
            'isFinal'            => !!$rawInfo['is_final'],

            'override'           => null,
            'implementation'     => null,

            'declaringClass'     => null,
            'declaringStructure' => null
        ]);
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function getFunctionInfo(array $rawInfo)
    {
        $rawParameters = unserialize($rawInfo['parameters_serialized']);

        $parameters = [];

        foreach ($rawParameters as $rawParameter) {
            $parameters[] = [
                'name'         => $rawParameter['name'],
                'typeHint'     => $rawParameter['type_hint'],
                'types'        => $this->getReturnTypeDataForSerializedTypes($rawParameter['types_serialized']),
                'description'  => $rawParameter['description'],
                'defaultValue' => $rawParameter['default_value'],
                'isNullable'   => !!$rawParameter['is_nullable'],
                'isReference'  => !!$rawParameter['is_reference'],
                'isVariadic'   => !!$rawParameter['is_variadic'],
                'isOptional'   => !!$rawParameter['is_optional']
            ];
        }

        $throws = unserialize($rawInfo['throws_serialized']);

        $throwsAssoc = [];

        foreach ($throws as $throws) {
            $throwsAssoc[$throws['type']] = $throws['description'];
        }

        return [
            'name'              => $rawInfo['name'],
            'fqcn'              => $rawInfo['fqcn'],
            'isBuiltin'         => !!$rawInfo['is_builtin'],
            'startLine'         => (int) $rawInfo['start_line'],
            'endLine'           => (int) $rawInfo['end_line'],
            'filename'          => $rawInfo['path'],

            'parameters'        => $parameters,
            'throws'            => $throwsAssoc,
            'isDeprecated'      => !!$rawInfo['is_deprecated'],
            'hasDocblock'       => !!$rawInfo['has_docblock'],
            'hasDocumentation'  => !!$rawInfo['has_docblock'],

            'shortDescription'  => $rawInfo['short_description'],
            'longDescription'   => $rawInfo['long_description'],
            'returnDescription' => $rawInfo['return_description'],

            'returnTypeHint'    => $rawInfo['return_type_hint'],
            'returnTypes'       => $this->getReturnTypeDataForSerializedTypes($rawInfo['return_types_serialized'])
        ];
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function getPropertyInfo(array $rawInfo)
    {
        return [
            'name'               => $rawInfo['name'],
            'startLine'          => (int) $rawInfo['start_line'],
            'endLine'            => (int) $rawInfo['end_line'],
            'defaultValue'       => $rawInfo['default_value'],
            'isMagic'            => !!$rawInfo['is_magic'],
            'isPublic'           => ($rawInfo['access_modifier'] === 'public'),
            'isProtected'        => ($rawInfo['access_modifier'] === 'protected'),
            'isPrivate'          => ($rawInfo['access_modifier'] === 'private'),
            'isStatic'           => !!$rawInfo['is_static'],
            'isDeprecated'       => !!$rawInfo['is_deprecated'],
            'hasDocblock'        => !!$rawInfo['has_docblock'],
            'hasDocumentation'   => !!$rawInfo['has_docblock'],

            'shortDescription'  => $rawInfo['short_description'],
            'longDescription'   => $rawInfo['long_description'],
            'typeDescription'   => $rawInfo['type_description'],

            'types'             => $this->getReturnTypeDataForSerializedTypes($rawInfo['types_serialized']),

            'override'           => null,
            'declaringClass'     => null,
            'declaringStructure' => null
        ];
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function getConstantInfo(array $rawInfo)
    {
        return [
            'name'              => $rawInfo['name'],
            'fqcn'              => $rawInfo['fqcn'],
            'isBuiltin'         => !!$rawInfo['is_builtin'],
            'startLine'         => (int) $rawInfo['start_line'],
            'endLine'           => (int) $rawInfo['end_line'],
            'defaultValue'      => $rawInfo['default_value'],
            'filename'          => $rawInfo['path'],

            'isPublic'          => true,
            'isProtected'       => false,
            'isPrivate'         => false,
            'isStatic'          => true,
            'isDeprecated'      => !!$rawInfo['is_deprecated'],
            'hasDocblock'       => !!$rawInfo['has_docblock'],
            'hasDocumentation'  => !!$rawInfo['has_docblock'],

            'shortDescription'  => $rawInfo['short_description'],
            'longDescription'   => $rawInfo['long_description'],
            'typeDescription'   => $rawInfo['type_description'],

            'types'             => $this->getReturnTypeDataForSerializedTypes($rawInfo['types_serialized'])
        ];
    }

    /**
     * @param array[] $serializedTypes
     *
     * @return array[]
     */
    protected function getReturnTypeDataForSerializedTypes($serializedTypes)
    {
        $types = [];

        $rawTypes = unserialize($serializedTypes);

        foreach ($rawTypes as $rawType) {
            $types[] = [
                'type'         => $rawType['type'],
                'fqcn'         => $rawType['fqcn'],
                'resolvedType' => $rawType['fqcn']
            ];
        }

        return $types;
    }

    /**
     * Returns a boolean indicating whether the specified item will inherit documentation from a parent item (if
     * present).
     *
     * @param array $processedData
     *
     * @return bool
     */
    protected function isInheritingDocumentation(array $processedData)
    {
        return
            !$processedData['hasDocblock'] ||
            $this->getDocblockAnalyzer()->isFullInheritDocSyntax($processedData['shortDescription']);
    }

    /**
     * Resolves the inheritDoc tag for the specified description.
     *
     * Note that according to phpDocumentor this only works for the long description (not the so-called 'summary' or
     * short description).
     *
     * @param string $description
     * @param string $parentDescription
     *
     * @return string
     */
    protected function resolveInheritDoc($description, $parentDescription)
    {
        return str_replace(DocblockParser::INHERITDOC, $parentDescription, $description);
    }

    /**
     * Extracts data from the specified (processed, i.e. already in the output format) property that is inheritable.
     *
     * @param array $processedData
     *
     * @return array
     */
    protected function extractInheritedPropertyInfo(array $processedData)
    {
        $info = [];

        $inheritedKeys = [
            'hasDocumentation',
            'isDeprecated',
            'shortDescription',
            'longDescription',
            'typeDescription',
            'types'
        ];

        foreach ($processedData as $key => $value) {
            if (in_array($key, $inheritedKeys)) {
                $info[$key] = $value;
            }
        }

        return $info;
    }

    /**
     * Extracts data from the specified (processed, i.e. already in the output format) method that is inheritable.
     *
     * @param array $processedData
     * @param array $inheritingMethodData
     *
     * @return array
     */
    protected function extractInheritedMethodInfo(array $processedData, array $inheritingMethodData)
    {
        $info = [];

        $inheritedKeys = [
            'hasDocumentation',
            'isDeprecated',
            'shortDescription',
            'longDescription',
            'returnDescription',
            'returnTypes',
            'throws'
        ];

        // Normally parameters are inherited from the parent docblock. However, this causes problems when an overridden
        // method adds an additional optional parameter or a subclass constructor uses completely different parameters.
        // In either of these cases, we don't want to inherit the docblock parameters anymore, because it isn't
        // correct anymore (and the developer should specify a new docblock specifying the changed parameters).
        $inheritedMethodParameterNames = array_map(function (array $parameter) {
            return $parameter['name'];
        }, $processedData['parameters']);

        $inheritingMethodParameterNames = array_map(function (array $parameter) {
            return $parameter['name'];
        }, $inheritingMethodData['parameters']);

        // We need elements that are present in either A or B, but not in both. array_diff only returns items that
        // are present in A, but not in B.
        $parameterNameDiff1 = array_diff($inheritedMethodParameterNames, $inheritingMethodParameterNames);
        $parameterNameDiff2 = array_diff($inheritingMethodParameterNames, $inheritedMethodParameterNames);

        if (empty($parameterNameDiff1) && empty($parameterNameDiff2)) {
            $inheritedKeys[] = 'parameters';
        }

        foreach ($processedData as $key => $value) {
            if (in_array($key, $inheritedKeys)) {
                $info[$key] = $value;
            }
        }

        return $info;
    }

    /**
     * Retrieves an instance of DocblockAnalyzer. The object will only be created once if needed.
     *
     * @return DocblockAnalyzer
     */
    protected function getDocblockAnalyzer()
    {
        if (!$this->docblockAnalyzer instanceof DocblockAnalyzer) {
            $this->docblockAnalyzer = new DocblockAnalyzer();
        }

        return $this->docblockAnalyzer;
    }

    /**
     * Retrieves an instance of TypeAnalyzer. The object will only be created once if needed.
     *
     * @return TypeAnalyzer
     */
    protected function getTypeAnalyzer()
    {
        if (!$this->typeAnalyzer instanceof TypeAnalyzer) {
            $this->typeAnalyzer = new TypeAnalyzer();
        }

        return $this->typeAnalyzer;
    }
}
