<?php

namespace PhpIntegrator\Analysis\Relations;

use ArrayObject;

/**
 * Deals with resolving inheritance for classlikes.
 */
class InheritanceResolver extends AbstractResolver
{
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
     * @param array       $parentConstantData
     * @param ArrayObject $class
     */
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

    /**
     * @param array       $parentPropertyData
     * @param ArrayObject $class
     */
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

    /**
     * @param array       $parentMethodData
     * @param ArrayObject $class
     */
    protected function resolveInheritanceOfMethod(array $parentMethodData, ArrayObject $class)
    {
        $inheritedData = [];
        $childMethod = null;
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
}
