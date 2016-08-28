<?php

namespace PhpIntegrator\Analysis\Conversion;

use ArrayAccess;

/**
 * Converts raw class constant data from the index to more useful data.
 */
class ClasslikeConstantConverter extends ConstantConverter
{
    /**
     * @param array       $rawInfo
     * @param ArrayAccess $class
     *
     * @return array
     */
    public function convertForClass(array $rawInfo, ArrayAccess $class)
    {
        $data = parent::convert($rawInfo);

        return array_merge($data, [
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
                'startLineMember' => $data['startLine'],
                'endLineMember'   => $data['endLine']
            ]
        ]);
    }
}
