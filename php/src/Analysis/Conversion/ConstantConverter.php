<?php

namespace PhpIntegrator\Analysis\Conversion;

/**
 * Converts raw constant data from the index to more useful data.
 */
class ConstantConverter extends AbstractConverter
{
    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function convert(array $rawInfo)
    {
        return [
            'name'              => $rawInfo['name'],
            'fqcn'              => $rawInfo['fqcn'],
            'isBuiltin'         => !!$rawInfo['is_builtin'],
            'startLine'         => (int) $rawInfo['start_line'],
            'endLine'           => (int) $rawInfo['end_line'],
            'defaultValue'      => $rawInfo['default_value'],
            'filename'          => $rawInfo['path'],

            'isPublic'          => true,
            'isProtected'       => false,
            'isPrivate'         => false,
            'isStatic'          => true,
            'isDeprecated'      => !!$rawInfo['is_deprecated'],
            'hasDocblock'       => !!$rawInfo['has_docblock'],
            'hasDocumentation'  => !!$rawInfo['has_docblock'],

            'shortDescription'  => $rawInfo['short_description'],
            'longDescription'   => $rawInfo['long_description'],
            'typeDescription'   => $rawInfo['type_description'],

            'types'             => $this->getReturnTypeDataForSerializedTypes($rawInfo['types_serialized'])
        ];
    }
}
