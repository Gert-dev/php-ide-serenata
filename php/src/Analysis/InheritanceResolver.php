<?php

namespace PhpIntegrator\Analysis;

use ArrayObject;

use PhpIntegrator\Analysis\DocblockAnalyzer;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Parsing\DocblockParser;

/**
 * Deals with resolving inheritance for classlikes.
 */
class InheritanceResolver
{
    /**
     * @var DocblockAnalyzer
     */
    protected $docblockAnalyzer;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @param DocblockAnalyzer $docblockAnalyzer
     * @param TypeAnalyzer     $typeAnalyzer
     */
    public function __construct(DocblockAnalyzer $docblockAnalyzer, TypeAnalyzer $typeAnalyzer)
    {
        $this->docblockAnalyzer = $docblockAnalyzer;
        $this->typeAnalyzer = $typeAnalyzer;
    }

    /**
     * @param ArrayObject $parent
     * @param ArrayObject $class
     */
    public function resolveInheritanceOf(ArrayObject $parent, ArrayObject $class)
    {
        if (!$class['shortDescription']) {
            $class['shortDescription'] = $parent['shortDescription'];
        }

        if (!$class['longDescription']) {
            $class['longDescription'] = $parent['longDescription'];
        } else {
            $class['longDescription'] = $this->resolveInheritDoc($class['longDescription'], $parent['longDescription']);
        }

        $class['hasDocumentation'] = $class['hasDocumentation'] || $parent['hasDocumentation'];

        $class['traits']     = array_merge($class['traits'], $parent['traits']);
        $class['interfaces'] = array_merge($class['interfaces'], $parent['interfaces']);
        $class['parents']    = array_merge($class['parents'], $parent['parents']);

        foreach ($parent['constants'] as $constant) {
            $this->resolveInheritanceOfConstant($constant, $class);
        }

        foreach ($parent['properties'] as $property) {
            $this->resolveInheritanceOfProperty($property, $class);
        }

        foreach ($parent['methods'] as $method) {
            $this->resolveInheritanceOfMethod($method, $class);
        }
    }

    /**
     * @param ArrayObject $interface
     * @param ArrayObject $class
     *
     * @return ArrayObject
     */
    public function resolveImplementationOf(ArrayObject $interface, ArrayObject $class)
    {
        foreach ($interface['constants'] as $constant) {
            $this->resolveInheritanceOfConstant($constant, $class);
        }

        foreach ($interface['methods'] as $method) {
            $this->resolveImplementationOfMethod($method, $class);
        }
    }

    /**
     * @param ArrayObject $trait
     * @param ArrayObject $class
     * @param array       $traitAliases
     * @param array       $traitPrecedences
     *
     * @return ArrayObject
     */
    public function resolveUseOf(ArrayObject $trait, ArrayObject $class, array $traitAliases, array $traitPrecedences)
    {
        foreach ($trait['properties'] as $property) {
            $this->resolveTraitUseOfProperty($property, $class);
        }

        foreach ($trait['methods'] as $method) {
            // If the method was aliased, pretend it has another name and access modifier before "inheriting" it.
            if (isset($traitAliases[$method['name']])) {
                $alias = $traitAliases[$method['name']];

                if ($alias['trait_fqcn'] === null || $alias['trait_fqcn'] === $trait['name']) {
                    $method['name']        = $alias['alias'] ?: $method['name'];
                    $method['isPublic']    = ($alias['access_modifier'] === 'public');
                    $method['isProtected'] = ($alias['access_modifier'] === 'protected');
                    $method['isPrivate']   = ($alias['access_modifier'] === 'private');
                }
            }

            if (isset($class['methods'][$method['name']])) {
                $existingMethod = $class['methods'][$method['name']];

                if ($existingMethod['declaringStructure']['type'] === 'trait') {
                    if (isset($traitPrecedences[$method['name']])) {
                        if ($traitPrecedences[$method['name']]['trait_fqcn'] !== $trait['name']) {
                            // The method is present in multiple used traits and precedences indicate that the one
                            // from this trait should not be imported.
                            continue;
                        }
                    }
                }
            }

            $this->resolveTraitUseOfMethod($method, $class);
        }
    }







    protected function resolveInheritanceOfConstant(array $parentConstantData, ArrayObject $class)
    {
        $class['constants'][$parentConstantData['name']] = $parentConstantData + [
            'declaringClass' => [
                'name'      => $class['name'],
                'filename'  => $class['filename'],
                'startLine' => $class['startLine'],
                'endLine'   => $class['endLine'],
                'type'      => $class['type']
            ],

            'declaringStructure' => [
                'name'            => $class['name'],
                'filename'        => $class['filename'],
                'startLine'       => $class['startLine'],
                'endLine'         => $class['endLine'],
                'type'            => $class['type'],
                'startLineMember' => $parentConstantData['startLine'],
                'endLineMember'   => $parentConstantData['endLine']
            ]
        ];
    }

    protected function resolveInheritanceOfProperty(array $parentPropertyData, ArrayObject $class)
    {
        $inheritedData = [];
        $childProperty = null;
        $overriddenPropertyData = null;

        if (isset($class['properties'][$parentPropertyData['name']])) {
            $childProperty = $class['properties'][$parentPropertyData['name']];

            $overriddenPropertyData = [
                'declaringClass'     => $parentPropertyData['declaringClass'],
                'declaringStructure' => $parentPropertyData['declaringStructure'],
                'startLine'          => $parentPropertyData['startLine'],
                'endLine'            => $parentPropertyData['endLine']
            ];

            if ($parentPropertyData['hasDocumentation'] && $this->isInheritingFullDocumentation($childProperty)) {
                $inheritedData = $this->extractInheritedPropertyInfo($parentPropertyData);
            } else {
                $inheritedData['longDescription'] = $this->resolveInheritDoc(
                    $childProperty['longDescription'],
                    $parentPropertyData['longDescription']
                );
            }
        }

        $class['properties'][$parentPropertyData['name']] = array_merge($parentPropertyData, $inheritedData, [
            'override'       => $overriddenPropertyData,

            'declaringClass' => [
                'name'            => $class['name'],
                'filename'        => $class['filename'],
                'startLine'       => $class['startLine'],
                'endLine'         => $class['endLine'],
                'type'            => $class['type'],
            ],

            'declaringStructure' => [
                'name'            => $class['name'],
                'filename'        => $class['filename'],
                'startLine'       => $class['startLine'],
                'endLine'         => $class['endLine'],
                'type'            => $class['type'],
                'startLineMember' => $parentPropertyData['startLine'],
                'endLineMember'   => $parentPropertyData['endLine']
            ]
        ]);
    }

    protected function resolveTraitUseOfProperty(array $parentPropertyData, ArrayObject $class)
    {
        $inheritedData = [];
        $childProperty = null;
        $overriddenPropertyData = null;

        if (isset($class['properties'][$parentPropertyData['name']])) {
            $childProperty = $class['properties'][$parentPropertyData['name']];

            $overriddenPropertyData = [
                'declaringClass'     => $childProperty['declaringClass'],
                'declaringStructure' => $parentPropertyData['declaringStructure'],
                'startLine'          => $parentPropertyData['startLine'],
                'endLine'            => $parentPropertyData['endLine']
            ];

            if ($parentPropertyData['hasDocumentation'] && $this->isInheritingFullDocumentation($childProperty)) {
                $inheritedData = $this->extractInheritedPropertyInfo($parentPropertyData);
            } else {
                $inheritedData['longDescription'] = $this->resolveInheritDoc(
                    $childProperty['longDescription'],
                    $parentPropertyData['longDescription']
                );
            }
        }

        $class['properties'][$parentPropertyData['name']] = array_merge($parentPropertyData, $inheritedData, [
            'override'       => $overriddenPropertyData,

            'declaringClass' => [
                'name'            => $class['name'],
                'filename'        => $class['filename'],
                'startLine'       => $class['startLine'],
                'endLine'         => $class['endLine'],
                'type'            => $class['type'],
            ],

            'declaringStructure' => [
                'name'            => $parentPropertyData['declaringStructure']['name'],
                'filename'        => $parentPropertyData['declaringStructure']['filename'],
                'startLine'       => $parentPropertyData['declaringStructure']['startLine'],
                'endLine'         => $parentPropertyData['declaringStructure']['endLine'],
                'type'            => $parentPropertyData['declaringStructure']['type'],
                'startLineMember' => $parentPropertyData['startLine'],
                'endLineMember'   => $parentPropertyData['endLine']
            ]
        ]);
    }

    protected function resolveInheritanceOfMethod(array $parentMethodData, ArrayObject $class)
    {
        $inheritedData = [];
        $childMethod = null;
        $dataToMaintain = [];
        $overriddenMethodData = null;
        $implementedMethodData = null;

        if (isset($class['methods'][$parentMethodData['name']])) {
            $childMethod = $class['methods'][$parentMethodData['name']];

            if ($parentMethodData['declaringStructure']['type'] === 'interface') {
                $implementedMethodData = [
                    'declaringClass'     => $parentMethodData['declaringClass'],
                    'declaringStructure' => $parentMethodData['declaringStructure'],
                    'startLine'          => $parentMethodData['startLine'],
                    'endLine'            => $parentMethodData['endLine']
                ];
            } else {
                $overriddenMethodData = [
                    'declaringClass'     => $parentMethodData['declaringClass'],
                    'declaringStructure' => $parentMethodData['declaringStructure'],
                    'startLine'          => $parentMethodData['startLine'],
                    'endLine'            => $parentMethodData['endLine'],
                    'wasAbstract'        => $parentMethodData['isAbstract']
                ];
            }

            if ($parentMethodData['hasDocumentation'] && $this->isInheritingFullDocumentation($childMethod)) {
                $inheritedData = $this->extractInheritedMethodInfo($parentMethodData, $childMethod);
            } else {
                // Overridden methods usually have the same parameter list as the parent method, but they can add
                // optional parameters or, for methods such as __construct, even used completely different parameters.
                $dataToMaintain['parameters'] = $childMethod['parameters'];

                $dataToMaintain['longDescription'] = $this->resolveInheritDoc(
                    $childMethod['longDescription'],
                    $parentMethodData['longDescription']
                );
            }
        }

        $class['methods'][$parentMethodData['name']] = array_merge($parentMethodData, $inheritedData, $dataToMaintain, [
            'override'       => $overriddenMethodData,
            'implementation' => $implementedMethodData,

            'declaringClass' => [
                'name'            => $class['name'],
                'filename'        => $class['filename'],
                'startLine'       => $class['startLine'],
                'endLine'         => $class['endLine'],
                'type'            => $class['type'],
            ],

            'declaringStructure' => [
                'name'            => $class['name'],
                'filename'        => $class['filename'],
                'startLine'       => $class['startLine'],
                'endLine'         => $class['endLine'],
                'type'            => $class['type'],
                'startLineMember' => $parentMethodData['startLine'],
                'endLineMember'   => $parentMethodData['endLine']
            ]
        ]);
    }

    protected function resolveImplementationOfMethod(array $parentMethodData, ArrayObject $class)
    {
        $inheritedData = [];
        $childMethod = null;
        $dataToMaintain = [];
        $overriddenMethodData = null;
        $implementedMethodData = null;

        if (isset($class['methods'][$parentMethodData['name']])) {
            $childMethod = $class['methods'][$parentMethodData['name']];

            if ($parentMethodData['declaringStructure']['type'] === 'interface') {
                $implementedMethodData = [
                    'declaringClass'     => $childMethod['declaringClass'],
                    'declaringStructure' => $parentMethodData['declaringStructure'],
                    'startLine'          => $parentMethodData['startLine'],
                    'endLine'            => $parentMethodData['endLine']
                ];
            } else {
                $overriddenMethodData = [
                    'declaringClass'     => $childMethod['declaringClass'],
                    'declaringStructure' => $parentMethodData['declaringStructure'],
                    'startLine'          => $parentMethodData['startLine'],
                    'endLine'            => $parentMethodData['endLine'],
                    'wasAbstract'        => $parentMethodData['isAbstract']
                ];
            }

            if ($parentMethodData['hasDocumentation'] && $this->isInheritingFullDocumentation($childMethod)) {
                $inheritedData = $this->extractInheritedMethodInfo($parentMethodData, $childMethod);
            } else {
                // Overridden methods usually have the same parameter list as the parent method, but they can add
                // optional parameters or, for methods such as __construct, even used completely different parameters.
                $dataToMaintain['parameters'] = $childMethod['parameters'];

                $dataToMaintain['longDescription'] = $this->resolveInheritDoc(
                    $childMethod['longDescription'],
                    $parentMethodData['longDescription']
                );
            }
        }

        $class['methods'][$parentMethodData['name']] = array_merge($parentMethodData, $inheritedData, $dataToMaintain, [
            'override'       => $overriddenMethodData,
            'implementation' => $implementedMethodData,

            'declaringClass' => [
                'name'            => $class['name'],
                'filename'        => $class['filename'],
                'startLine'       => $class['startLine'],
                'endLine'         => $class['endLine'],
                'type'            => $class['type'],
            ],

            'declaringStructure' => [
                'name'            => $parentMethodData['declaringStructure']['name'],
                'filename'        => $parentMethodData['declaringStructure']['filename'],
                'startLine'       => $parentMethodData['declaringStructure']['startLine'],
                'endLine'         => $parentMethodData['declaringStructure']['endLine'],
                'type'            => $parentMethodData['declaringStructure']['type'],
                'startLineMember' => $parentMethodData['startLine'],
                'endLineMember'   => $parentMethodData['endLine']
            ]
        ]);
    }










    protected function resolveTraitUseOfMethod(array $parentMethodData, ArrayObject $class)
    {
        $inheritedData = [];
        $childMethod = null;
        $dataToMaintain = [];
        $overriddenMethodData = null;
        $implementedMethodData = null;

        if (isset($class['methods'][$parentMethodData['name']])) {
            $childMethod = $class['methods'][$parentMethodData['name']];

            if ($parentMethodData['declaringStructure']['type'] === 'interface') {
                $implementedMethodData = [
                    'declaringClass'     => $childMethod['declaringClass'],
                    'declaringStructure' => $parentMethodData['declaringStructure'],
                    'startLine'          => $parentMethodData['startLine'],
                    'endLine'            => $parentMethodData['endLine']
                ];
            } else {
                $overriddenMethodData = [
                    'declaringClass'     => $childMethod['declaringClass'],
                    'declaringStructure' => $parentMethodData['declaringStructure'],
                    'startLine'          => $parentMethodData['startLine'],
                    'endLine'            => $parentMethodData['endLine'],
                    'wasAbstract'        => $parentMethodData['isAbstract']
                ];
            }

            if ($parentMethodData['hasDocumentation'] && $this->isInheritingFullDocumentation($childMethod)) {
                $inheritedData = $this->extractInheritedMethodInfo($parentMethodData, $childMethod);
            } else {
                // Overridden methods usually have the same parameter list as the parent method, but they can add
                // optional parameters or, for methods such as __construct, even used completely different parameters.
                $dataToMaintain['parameters'] = $childMethod['parameters'];

                $dataToMaintain['longDescription'] = $this->resolveInheritDoc(
                    $childMethod['longDescription'],
                    $parentMethodData['longDescription']
                );
            }
        }

        $class['methods'][$parentMethodData['name']] = array_merge($parentMethodData, $inheritedData, $dataToMaintain, [
            'override'       => $overriddenMethodData,
            'implementation' => $implementedMethodData,

            'declaringClass' => [
                'name'            => $class['name'],
                'filename'        => $class['filename'],
                'startLine'       => $class['startLine'],
                'endLine'         => $class['endLine'],
                'type'            => $class['type'],
            ],

            'declaringStructure' => [
                'name'            => $parentMethodData['declaringStructure']['name'],
                'filename'        => $parentMethodData['declaringStructure']['filename'],
                'startLine'       => $parentMethodData['declaringStructure']['startLine'],
                'endLine'         => $parentMethodData['declaringStructure']['endLine'],
                'type'            => $parentMethodData['declaringStructure']['type'],
                'startLineMember' => $parentMethodData['startLine'],
                'endLineMember'   => $parentMethodData['endLine']
            ]
        ]);
    }








    /**
     * Returns a boolean indicating whether the specified item will inherit documentation from a parent item (if
     * present).
     *
     * @param array $processedData
     *
     * @return bool
     */
    protected function isInheritingFullDocumentation(array $processedData)
    {
        return
            !$processedData['hasDocblock'] ||
            $this->docblockAnalyzer->isFullInheritDocSyntax($processedData['shortDescription']);
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
     * @param array $propertyData
     *
     * @return array
     */
    protected function extractInheritedPropertyInfo(array $propertyData)
    {
        $inheritedKeys = [
            'hasDocumentation',
            'isDeprecated',
            'shortDescription',
            'longDescription',
            'typeDescription',
            'types'
        ];

        $info = [];

        foreach ($propertyData as $key => $value) {
            if (in_array($key, $inheritedKeys)) {
                $info[$key] = $value;
            }
        }

        return $info;
    }

    /**
     * @param array $methodData
     * @param array $inheritingMethodData
     *
     * @return array
     */
    protected function extractInheritedMethodInfo(array $methodData, array $inheritingMethodData)
    {
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
        }, $methodData['parameters']);

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

        $info = [];

        foreach ($methodData as $key => $value) {
            if (in_array($key, $inheritedKeys)) {
                $info[$key] = $value;
            }
        }

        return $info;
    }
}
