<?php

namespace PhpIntegrator;

/**
 * Adapts and resolves data from the index as needed to receive an appropriate output data format.
 */
class IndexDataAdapter
{
    /**
     * The storage to use for accessing index data.
     *
     * @var IndexDataAdapter\ProviderInterface
     */
    protected $storage;

    /**
     * Constructor.
     *
     * @param IndexDataAdapter\ProviderInterface $storage
     */
    public function __construct(IndexDataAdapter\ProviderInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Retrieves information about the specified structural element.
     *
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementInfo($id)
    {
        return $this->resolveStructuralElement(
            $this->storage->getStructuralElementRawInfo($id),
            $this->storage->getStructuralElementRawParents($id),
            $this->storage->getStructuralElementRawChildren($id),
            $this->storage->getStructuralElementRawInterfaces($id),
            $this->storage->getStructuralElementRawImplementors($id),
            $this->storage->getStructuralElementRawTraits($id),
            $this->storage->getStructuralElementRawTraitUsers($id),
            $this->storage->getStructuralElementRawConstants($id),
            $this->storage->getStructuralElementRawProperties($id),
            $this->storage->getStructuralElementRawMethods($id)
        );
    }

    /**
     * Resolves structural element information from the specified raw data.
     *
     * @param array|\Traversable $element
     * @param array|\Traversable $parents
     * @param array|\Traversable $children
     * @param array|\Traversable $interfaces
     * @param array|\Traversable $implementors
     * @param array|\Traversable $traits
     * @param array|\Traversable $traitUsers
     * @param array|\Traversable $constants
     * @param array|\Traversable $properties
     * @param array|\Traversable $methods
     *
     * @todo Could mayhaps benefit from some refactoring...
     *
     * @return array
     */
    public function resolveStructuralElement(
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
        $result = [
            'name'               => $element['fqsen'],
            'startLine'          => (int) $element['start_line'],
            'endLine'            => (int) $element['end_line'],
            'shortName'          => $element['name'],
            'filename'           => $element['path'],
            'type'               => $element['type_name'],
            'isAbstract'         => !!$element['is_abstract'],
            'isBuiltin'          => !!$element['is_builtin'],
            'isDeprecated'       => !!$element['is_deprecated'],

            'descriptions'       => [
                'short' => $element['short_description'],
                'long'  => $element['long_description']
            ],

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
        ];

        foreach ($children as $child) {
            $result['directChildren'][] = $child['fqsen'];
        }

        foreach ($implementors as $implementor) {
            $result['directImplementors'][] = $implementor['fqsen'];
        }

        foreach ($traitUsers as $trait) {
            $result['directTraitUsers'][] = $trait['fqsen'];
        }

        // Take all members from the base class as a starting point. Note that there can only be multiple base classes
        // for interfaces.
        foreach ($parents as $parent) {
            $parentInfo = $this->getStructuralElementInfo($parent['id']);

            if ($parentInfo) {
                if (!$result['descriptions']['short']) {
                    $result['descriptions']['short'] = $parentInfo['descriptions']['short'];
                }

                if (!$result['descriptions']['long']) {
                    $result['descriptions']['long'] = $parentInfo['descriptions']['long'];
                }

                $result['constants']  = array_merge($result['constants'], $parentInfo['constants']);
                $result['properties'] = array_merge($result['properties'], $parentInfo['properties']);
                $result['methods']    = array_merge($result['methods'], $parentInfo['methods']);

                $result['traits']     = array_merge($result['traits'], $parentInfo['traits']);
                $result['interfaces'] = array_merge($result['interfaces'], $parentInfo['interfaces']);
                $result['parents']    = array_merge($result['parents'], [$parentInfo['name']], $parentInfo['parents']);

                $result['directParents'][] = $parentInfo['name'];
            }
        }

        // Append members from direct interfaces to the pool of members. These only supply additional members, but will
        // never overwrite any existing members as they have a lower priority than inherited members.
        foreach ($interfaces as $interface) {
            $interface = $this->getStructuralElementInfo($interface['id']);

            $result['interfaces'][] = $interface['name'];
            $result['directInterfaces'][] = $interface['name'];

            foreach ($interface['constants'] as $constant) {
                if (!isset($result['constants'][$constant['name']])) {
                    $result['constants'][$constant['name']] = $constant;
                }
            }

            foreach ($interface['properties'] as $property) {
                if (!isset($result['properties'][$property['name']])) {
                    $result['properties'][$property['name']] = $property;
                }
            }

            foreach ($interface['methods'] as $method) {
                if (!isset($result['methods'][$method['name']])) {
                    $result['methods'][$method['name']] = $method;
                }
            }
        }

        $traitAliases = $this->storage->getStructuralElementTraitAliasesAssoc($element['id']);
        $traitPrecedences = $this->storage->getStructuralElementTraitPrecedencesAssoc($element['id']);

        foreach ($traits as $trait) {
            $trait = $this->getStructuralElementInfo($trait['id']);

            $result['traits'][] = $trait['name'];
            $result['directTraits'][] = $trait['name'];

            foreach ($trait['constants'] as $constant) {
                if (isset($traitAliases[$constant['name']])) {
                    $alias = $traitAliases[$constant['name']];

                    if ($alias['trait_fqsen'] === null || $alias['trait_fqsen'] === $trait['name']) {
                        $constant['name']        = $alias['alias'] ?: $constant['name'];
                        $constant['isPublic']    = ($alias['access_modifier'] === 'public');
                        $constant['isProtected'] = ($alias['access_modifier'] === 'protected');
                        $constant['isPrivate']   = ($alias['access_modifier'] === 'private');
                    }
                }

                if (isset($result['constants'][$constant['name']])) {
                    $existingConstant = $result['constants'][$constant['name']];

                    if ($existingConstant['declaringStructure']['type'] === 'trait') {
                        if (isset($traitPrecedences[$constant['name']])) {
                            if ($traitPrecedences[$constant['name']]['trait_fqsen'] !== $trait['name']) {
                                // The constant is present in multiple used traits and precedences indicate that the one
                                // from this trait should not be imported.
                                continue;
                            }
                        }
                    }
                }

                $result['constants'][$constant['name']] = array_merge($constant, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => (int) $element['start_line'],
                        'endLine'         => (int) $element['end_line'],
                        'type'            => $element['type_name']
                    ]
                ]);
            }

            foreach ($trait['properties'] as $property) {
                if (isset($traitAliases[$property['name']])) {
                    $alias = $traitAliases[$property['name']];

                    if ($alias['trait_fqsen'] === null || $alias['trait_fqsen'] === $trait['name']) {
                        $property['name']        = $alias['alias'] ?: $property['name'];
                        $property['isPublic']    = ($alias['access_modifier'] === 'public');
                        $property['isProtected'] = ($alias['access_modifier'] === 'protected');
                        $property['isPrivate']   = ($alias['access_modifier'] === 'private');
                    }
                }

                $inheritedData = [];
                $existingProperty = null;

                if (isset($result['properties'][$property['name']])) {
                    $existingProperty = $result['properties'][$property['name']];

                    if ($existingProperty['declaringStructure']['type'] === 'trait') {
                        if (isset($traitPrecedences[$property['name']])) {
                            if ($traitPrecedences[$property['name']]['trait_fqsen'] !== $trait['name']) {
                                // The property is present in multiple used traits and precedences indicate that the one
                                // from this trait should not be imported.
                                continue;
                            }
                        }
                    }

                    if ($this->isInheritingDocumentation($property)) {
                        $inheritedData = $this->extractInheritedPropertyInfo($existingProperty);
                    }
                }

                $resultingProperty = array_merge($property, $inheritedData, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => (int) $element['start_line'],
                        'endLine'         => (int) $element['end_line'],
                        'type'            => $element['type_name'],
                    ]
                ]);

                if ($existingProperty) {
                    $resultingProperty['descriptions']['long'] = $this->resolveInheritDoc(
                        $resultingProperty['descriptions']['long'],
                        $existingProperty['descriptions']['long']
                    );
                }

                $result['properties'][$property['name']] = $resultingProperty;
            }

            foreach ($trait['methods'] as $method) {
                if (isset($traitAliases[$method['name']])) {
                    $alias = $traitAliases[$method['name']];

                    if ($alias['trait_fqsen'] === null || $alias['trait_fqsen'] === $trait['name']) {
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
                            if ($traitPrecedences[$method['name']]['trait_fqsen'] !== $trait['name']) {
                                // The method is present in multiple used traits and precedences indicate that the one
                                // from this trait should not be imported.
                                continue;
                            }
                        }
                    }

                    if ($this->isInheritingDocumentation($method)) {
                        $inheritedData = $this->extractInheritedMethodInfo($existingMethod);
                    }
                }

                $resultingMethod = array_merge($method, $inheritedData, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => (int) $element['start_line'],
                        'endLine'         => (int) $element['end_line'],
                        'type'            => $element['type_name'],
                    ]
                ]);

                if ($existingMethod) {
                    $resultingMethod['descriptions']['long'] = $this->resolveInheritDoc(
                        $resultingMethod['descriptions']['long'],
                        $existingMethod['descriptions']['long']
                    );
                }

                $result['methods'][$method['name']] = $resultingMethod;
            }
        }

        foreach ($constants as $rawConstantData) {
            $result['constants'][$rawConstantData['name']] = array_merge($this->getConstantInfo($rawConstantData), [
                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                    'startLineMember' => (int) $rawConstantData['start_line'],
                    'endLineMember'   => (int) $rawConstantData['end_line']
                ]
            ]);
        }

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
                    'endLine'            => (int) $existingProperty['end_line']
                ];

                if ($this->isInheritingDocumentation($property)) {
                    $inheritedData = $this->extractInheritedPropertyInfo($existingProperty);
                }
            }

            $resultingProperty = array_merge($property, $inheritedData, [
                'override'       => $overriddenPropertyData,

                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                    'startLineMember' => (int) $rawPropertyData['start_line'],
                    'endLineMember'   => (int) $rawPropertyData['end_line']
                ]
            ]);

            if ($resultingProperty['return']['type'] === 'self') {
                $resultingProperty['return']['resolvedType'] = $element['fqsen'];
            }

            if ($existingProperty) {
                $resultingProperty['descriptions']['long'] = $this->resolveInheritDoc(
                    $resultingProperty['descriptions']['long'],
                    $existingProperty['descriptions']['long']
                );
            }

            $result['properties'][$property['name']] = $resultingProperty;
        }

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
                        'endLine'            => (int) $existingMethod['endLine']
                    ];
                }

                if ($this->isInheritingDocumentation($method)) {
                    $inheritedData = $this->extractInheritedMethodInfo($existingMethod);
                }
            }

            $resultingMethod = array_merge($method, $inheritedData, [
                'override'       => $overriddenMethodData,
                'implementation' => $implementedMethodData,

                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => (int) $element['start_line'],
                    'endLine'         => (int) $element['end_line'],
                    'type'            => $element['type_name'],
                    'startLineMember' => (int) $rawMethodData['start_line'],
                    'endLineMember'   => (int) $rawMethodData['end_line']
                ]
            ]);

            if ($resultingMethod['return']['type'] === 'self') {
                $resultingMethod['return']['resolvedType'] = $element['fqsen'];
            }

            if ($existingMethod) {
                $resultingMethod['descriptions']['long'] = $this->resolveInheritDoc(
                    $resultingMethod['descriptions']['long'],
                    $existingMethod['descriptions']['long']
                );
            }

            $result['methods'][$method['name']] = $resultingMethod;
        }

        // Resolve return types.
        foreach ($result['methods'] as $name => &$method) {
            if ($method['return']['type'] === '$this' || $method['return']['type'] === 'static') {
                $method['return']['resolvedType'] = $element['fqsen'];
            } elseif (!isset($method['return']['resolvedType'])) {
                $method['return']['resolvedType'] = $method['return']['type'];
            }
        }

        foreach ($result['properties'] as $name => &$property) {
            if ($property['return']['type'] === '$this' || $property['return']['type'] === 'static') {
                $property['return']['resolvedType'] = $element['fqsen'];
            } elseif (!isset($property['return']['resolvedType'])) {
                $property['return']['resolvedType'] = $property['return']['type'];
            }
        }

        return $result;
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
                'name'        => $rawParameter['name'],
                'type'        => $rawParameter['type'],
                'fullType'    => $rawParameter['full_type'],
                'description' => $rawParameter['description'],
                'isReference' => !!$rawParameter['is_reference'],
                'isVariadic'  => !!$rawParameter['is_variadic'],
                'isOptional'  => !!$rawParameter['is_optional']
            ];
        }

        $throws = unserialize($rawInfo['throws_serialized']);

        $throwsAssoc = [];

        foreach ($throws as $throws) {
            $throwsAssoc[$throws['type']] = $throws['description'];
        }

        return [
            'name'          => $rawInfo['name'],
            'isBuiltin'     => false,
            'startLine'     => (int) $rawInfo['start_line'],
            'endLine'       => (int) $rawInfo['end_line'],
            'filename'      => $rawInfo['path'],

            'parameters'    => $parameters,
            'throws'        => $throwsAssoc,
            'isDeprecated'  => !!$rawInfo['is_deprecated'],
            'hasDocblock'   => !!$rawInfo['has_docblock'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'         => $rawInfo['return_type'],
                'resolvedType' => $rawInfo['full_return_type'],
                'description'  => $rawInfo['return_description']
            ]
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
            'isMagic'            => !!$rawInfo['is_magic'],
            'isPublic'           => ($rawInfo['access_modifier'] === 'public'),
            'isProtected'        => ($rawInfo['access_modifier'] === 'protected'),
            'isPrivate'          => ($rawInfo['access_modifier'] === 'private'),
            'isStatic'           => !!$rawInfo['is_static'],
            'isDeprecated'       => !!$rawInfo['is_deprecated'],
            'hasDocblock'        => !!$rawInfo['has_docblock'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'         => $rawInfo['return_type'],
                'resolvedType' => $rawInfo['full_return_type'],
                'description'  => $rawInfo['return_description']
            ],

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
            'name'         => $rawInfo['name'],
            'isBuiltin'    => !!$rawInfo['is_builtin'],
            'startLine'    => (int) $rawInfo['start_line'],
            'endLine'      => (int) $rawInfo['end_line'],
            'filename'     => $rawInfo['path'],

            'isPublic'     => true,
            'isProtected'  => false,
            'isPrivate'    => false,
            'isStatic'     => true,
            'isDeprecated' => !!$rawInfo['is_deprecated'],
            'hasDocblock'  => !!$rawInfo['has_docblock'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'         => $rawInfo['return_type'],
                'resolvedType' => $rawInfo['full_return_type'],
                'description'  => $rawInfo['return_description']
            ],
        ];
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
        // Ticket #86 - Add support for inheriting the entire docblock from the parent if the current docblock contains
        // nothing but these tags. Note that, according to draft PSR-5 and phpDocumentor's implementation, this is
        // incorrect. However, some large frameworks (such as Symfony) use this and it thus makes life easier for many
        // developers, hence this workaround.
        return !$processedData['hasDocblock'] || in_array($processedData['descriptions']['short'], [
            '{@inheritdoc}', '{@inheritDoc}'
        ]);
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
        return str_replace(DocParser::INHERITDOC, $parentDescription, $description);
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
        return array_filter($processedData, function ($key) {
            return in_array($key, [
                'isDeprecated',
                'descriptions',
                'return'
            ]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Extracts data from the specified (processed, i.e. already in the output format) method that is inheritable.
     *
     * @param array $processedData
     *
     * @return array
     */
    protected function extractInheritedMethodInfo(array $processedData)
    {
        return array_filter($processedData, function ($key) {
            return in_array($key, [
                'isDeprecated',
                'descriptions',
                'return',
                'parameters',
                'throws'
            ]);
        }, ARRAY_FILTER_USE_KEY);
    }
}
