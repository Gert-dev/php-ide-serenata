<?php

namespace PhpIntegrator\Analysis\Relations;

use ArrayObject;

/**
 * Deals with resolving implementation of interfaces for classlikes.
 */
class InterfaceImplementationResolver extends AbstractResolver
{
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
     * @param array       $parentMethodData
     * @param ArrayObject $class
     */
    protected function resolveImplementationOfMethod(array $parentMethodData, ArrayObject $class)
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
