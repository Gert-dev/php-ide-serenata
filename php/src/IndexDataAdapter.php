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
     * @var IndexStorageInterface
     */
    protected $storage;

    /**
     * Constructor.
     *
     * @param IndexStorageInterface $storage
     */
    public function __construct(IndexStorageInterface $storage)
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
            $this->storage->getParentFqsens($id),
            $this->storage->getStructuralElementRawInterfaces($id),
            $this->storage->getStructuralElementRawTraits($id),
            $this->storage->getStructuralElementRawConstants($id),
            $this->storage->getStructuralElementRawProperties($id),
            $this->storage->getStructuralElementRawMethods($id)
        );
    }

    /**
     * Resolves structural element information from the specified raw data.
     *
     * @param array|\Traversable $element
     * @param array|\Traversable $parentFqsens
     * @param array|\Traversable $interfaces
     * @param array|\Traversable $traits
     * @param array|\Traversable $constants
     * @param array|\Traversable $properties
     * @param array|\Traversable $methods
     *
     * @return array
     */
    public function resolveStructuralElement(
        $element,
        $parentFqsens,
        $interfaces,
        $traits,
        $constants,
        $properties,
        $methods
    ) {
        $result = [
            'class'        => $element['fqsen'],
            'wasFound'     => true,
            'startLine'    => $element['start_line'],
            'name'         => $element['fqsen'],
            'shortName'    => $element['name'],
            'filename'     => $element['path'],
            'isTrait'      => ($element['type_name'] === 'trait'),
            'isClass'      => ($element['type_name'] === 'class'),
            'isInterface'  => ($element['type_name'] === 'interface'),
            'isAbstract'   => !!$element['is_abstract'],
            'parents'      => array_values($parentFqsens),
            'deprecated'   => !!$element['is_deprecated'],
            'descriptions' => [
                'short' => $element['short_description'],
                'long'  => $element['long_description']
            ],
            'constants'    => [],
            'properties'   => [],
            'methods'      => []
        ];

        // Take all members from the base class as a starting point.
        $baseClassInfo = !empty($parentFqsens) ? $this->getStructuralElementInfo(array_keys($parentFqsens)[0]) : null;

        if ($baseClassInfo) {
            $result['constants']  = $baseClassInfo['constants'];
            $result['properties'] = $baseClassInfo['properties'];
            $result['methods']    = $baseClassInfo['methods'];
        }

        // Append members from direct interfaces to the pool of members. These only supply additional members, but will
        // never overwrite any existing members as they have a lower priority than inherited members.
        foreach ($interfaces as $interface) {
            $interface = $this->getStructuralElementInfo($interface['id']);

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

        foreach ($traits as $trait) {
            $trait = $this->getStructuralElementInfo($trait['id']);

            foreach ($trait['constants'] as $constant) {
                if (isset($result['constants'][$constant['name']])) {
                    // TODO: Inherit description from existing member if not present.
                }

                $result['constants'][$constant['name']] = array_merge($constant, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => $element['start_line'],
                        'isTrait'         => ($element['type_name'] === 'trait'),
                        'isClass'         => ($element['type_name'] === 'class'),
                        'isInterface'     => ($element['type_name'] === 'interface')
                    ]
                ]);
            }

            foreach ($trait['properties'] as $property) {
                if (isset($result['properties'][$property['name']])) {
                    // TODO: Inherit description from existing member if not present.
                }

                $result['constants'][$property['name']] = array_merge($property, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => $element['start_line'],
                        'isTrait'         => ($element['type_name'] === 'trait'),
                        'isClass'         => ($element['type_name'] === 'class'),
                        'isInterface'     => ($element['type_name'] === 'interface')
                    ]
                ]);
            }

            foreach ($trait['methods'] as $method) {
                if (isset($result['methods'][$method['name']])) {
                    // TODO: Inherit description from existing member if not present.
                }

                $result['constants'][$method['name']] = array_merge($method, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => $element['start_line'],
                        'isTrait'         => ($element['type_name'] === 'trait'),
                        'isClass'         => ($element['type_name'] === 'class'),
                        'isInterface'     => ($element['type_name'] === 'interface')
                    ]
                ]);
            }
        }

        foreach ($constants as $constant) {
            if (isset($result['constants'][$constant['name']])) {
                // TODO: Inherit description from existing member if not present.
            }

            $result['constants'][$constant['name']] = array_merge($this->getConstantInfo($constant), [
                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface')
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface'),
                    'startLineMember' => $constant['start_line']
                ]
            ]);
        }

        foreach ($properties as $property) {
            $overriddenProperty = null;

            if (isset($result['properties'][$property['name']])) {
                // TODO: Inherit description from existing member if not present.

                $existingProperty = $result['properties'][$property['name']];

                $overriddenProperty = [
                    'declaringClass'     => $existingProperty['declaringClass'],
                    'declaringStructure' => $existingProperty['declaringStructure'],
                    'startLine'          => $existingProperty['startLine']
                ];
            }

            $propertyInfo = $this->getPropertyInfo($property);

            if ($propertyInfo['return']['type'] === 'self') {
                $propertyInfo['return']['resolvedType'] = $element['fqsen'];
            }

            $result['properties'][$property['name']] = array_merge($propertyInfo, [
                'override'       => $overriddenProperty,
                'implementation' => null,

                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface')
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface'),
                    'startLineMember' => $property['start_line']
                ]
            ]);
        }

        foreach ($methods as $method) {
            $overriddenMethod = null;
            $implementedMethod = null;

            if (isset($result['methods'][$method['name']])) {
                // TODO: Inherit description from existing member if not present.

                $existingMethod = $result['methods'][$method['name']];

                if ($existingMethod['declaringStructure']['isInterface']) {
                    $implementedMethod = [
                        'declaringClass'     => $existingMethod['declaringClass'],
                        'declaringStructure' => $existingMethod['declaringStructure'],
                        'startLine'          => $existingMethod['startLine']
                    ];
                } else {
                    $overriddenMethod = [
                        'declaringClass'     => $existingMethod['declaringClass'],
                        'declaringStructure' => $existingMethod['declaringStructure'],
                        'startLine'          => $existingMethod['startLine']
                    ];
                }
            }

            $methodInfo = $this->getMethodInfo($method);

            if ($methodInfo['return']['type'] === 'self') {
                $methodInfo['return']['resolvedType'] = $element['fqsen'];
            }

            $result['methods'][$method['name']] = array_merge($methodInfo, [
                'override'       => $overriddenMethod,
                'implementation' => $implementedMethod,

                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface')
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface'),
                    'startLineMember' => $method['start_line']
                ]
            ]);
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
            'isMethod'           => true,
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
        $rawParameters = $this->storage->getFunctionParameters($rawInfo['id']);

        $optionals = [];
        $parameters = [];

        foreach ($rawParameters as $rawParameter) {
            $name = '';

            if ($rawParameter['is_reference']) {
                $name .= '&';
            }

            $name .= '$' . $rawParameter['name'];

            if ($rawParameter['is_variadic']) {
                $name .= '...';
            }

            if ($rawParameter['is_optional']) {
                $optionals[] = $name;
            } else {
                $parameters[] = $name;
            }
        }

        $throws = $this->storage->getFunctionThrows($rawInfo['id']);

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
                'type'         => $rawInfo['return_type'],
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
            'isProperty'         => true,
            'startLine'          => $rawInfo['start_line'],
            'isMagic'            => !!$rawInfo['is_magic'],
            'isPublic'           => ($rawInfo['access_modifier'] === 'public'),
            'isProtected'        => ($rawInfo['access_modifier'] === 'protected'),
            'isPrivate'          => ($rawInfo['access_modifier'] === 'private'),
            'isStatic'           => !!$rawInfo['is_static'],
            'deprecated'         => !!$rawInfo['is_deprecated'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'         => $rawInfo['return_type'],
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
            'name'        => $rawInfo['name'],
            'isBuiltin'   => !!$rawInfo['is_builtin'],
            'isPublic'    => true,
            'isProtected' => false,
            'isPrivate'   => false,
            'isStatic'    => true,
            'deprecated'  => !!$rawInfo['is_deprecated'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'         => $rawInfo['return_type'],
                'description'  => $rawInfo['return_description']
            ],
        ];
    }
}
