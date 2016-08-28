<?php

namespace PhpIntegrator\Analysis\Conversion;

/**
 * Converts raw classlike data from the index to more useful data.
 */
class ClasslikeConverter extends AbstractConverter
{
    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function convert(array $rawInfo)
    {
        return [
            'name'               => $rawInfo['fqcn'],
            'startLine'          => (int) $rawInfo['start_line'],
            'endLine'            => (int) $rawInfo['end_line'],
            'shortName'          => $rawInfo['name'],
            'filename'           => $rawInfo['path'],
            'type'               => $rawInfo['type_name'],
            'isAbstract'         => !!$rawInfo['is_abstract'],
            'isFinal'            => !!$rawInfo['is_final'],
            'isBuiltin'          => !!$rawInfo['is_builtin'],
            'isDeprecated'       => !!$rawInfo['is_deprecated'],
            'isAnnotation'       => !!$rawInfo['is_annotation'],
            'hasDocblock'        => !!$rawInfo['has_docblock'],
            'hasDocumentation'   => !!$rawInfo['has_docblock'],
            'shortDescription'   => $rawInfo['short_description'],
            'longDescription'    => $rawInfo['long_description']
        ];
    }
}
