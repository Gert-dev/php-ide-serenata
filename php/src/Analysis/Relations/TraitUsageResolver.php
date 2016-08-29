<?php

namespace PhpIntegrator\Analysis\Relations;

use ArrayObject;

/**
 * Deals with resolving trait usage for classlikes.
 */
class TraitUsageResolver extends AbstractResolver
{
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

    /**
     * @param array       $parentPropertyData
     * @param ArrayObject $class
     */
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
        } else {
            $childProperty = [];
        }

        $class['properties'][$parentPropertyData['name']] = array_merge($parentPropertyData, $childProperty, $inheritedData, [
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

    /**
     * @param array       $parentMethodData
     * @param ArrayObject $class
     */
    protected function resolveTraitUseOfMethod(array $parentMethodData, ArrayObject $class)
    {
        $inheritedData = [];
        $childMethod = null;
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
                $inheritedData['longDescription'] = $this->resolveInheritDoc(
                    $childMethod['longDescription'],
                    $parentMethodData['longDescription']
                );
            }
        } else {
            $childMethod = [];
        }

        $class['methods'][$parentMethodData['name']] = array_merge($parentMethodData, $childMethod, $inheritedData, [
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
}
