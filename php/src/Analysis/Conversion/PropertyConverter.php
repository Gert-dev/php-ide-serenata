<?php

namespace PhpIntegrator\Analysis\Conversion;

use ArrayAccess;

/**
 * Converts raw property data from the index to more useful data.
 */
class PropertyConverter extends AbstractConverter
{
    /**
     * @param array       $rawInfo
     * @param ArrayAccess $class
     *
     * @return array
     */
    public function convertForClass(array $rawInfo, ArrayAccess $class)
    {
        $data = [
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
        ];

        return array_merge($data, [
            'override'          => null,

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
