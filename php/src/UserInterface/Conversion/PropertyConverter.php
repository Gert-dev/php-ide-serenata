<?php

namespace PhpIntegrator\UserInterface\Conversion;

/**
 * Converts raw property data from the index to more useful data.
 */
class PropertyConverter extends AbstractConverter
{
    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function convert(array $rawInfo)
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

            'types'             => $this->getReturnTypeDataForSerializedTypes($rawInfo['types_serialized'])
        ];
    }
}
