<?php

namespace PhpIntegrator\UserInterface\Conversion;

use ArrayAccess;

/**
 * Converts raw method data from the index to more useful data.
 */
class MethodConverter extends FunctionConverter
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
            'isMagic'         => !!$rawInfo['is_magic'],
            'isPublic'        => ($rawInfo['access_modifier'] === 'public'),
            'isProtected'     => ($rawInfo['access_modifier'] === 'protected'),
            'isPrivate'       => ($rawInfo['access_modifier'] === 'private'),
            'isStatic'        => !!$rawInfo['is_static'],
            'isAbstract'      => !!$rawInfo['is_abstract'],
            'isFinal'         => !!$rawInfo['is_final'],

            'override'       => null,
            'implementation' => null,

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
                'startLineMember' => $data['startLine'],
                'endLineMember'   => $data['endLine']
            ]
        ]);
    }
}
