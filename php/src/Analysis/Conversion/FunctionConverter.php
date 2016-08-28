<?php

namespace PhpIntegrator\Analysis\Conversion;

/**
 * Converts raw function data from the index to more useful data.
 */
class FunctionConverter extends AbstractConverter
{
    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function convert(array $rawInfo)
    {
        $rawParameters = unserialize($rawInfo['parameters_serialized']);

        $parameters = [];

        foreach ($rawParameters as $rawParameter) {
            $parameters[] = [
                'name'         => $rawParameter['name'],
                'typeHint'     => $rawParameter['type_hint'],
                'types'        => $this->getReturnTypeDataForSerializedTypes($rawParameter['types_serialized']),
                'description'  => $rawParameter['description'],
                'defaultValue' => $rawParameter['default_value'],
                'isNullable'   => !!$rawParameter['is_nullable'],
                'isReference'  => !!$rawParameter['is_reference'],
                'isVariadic'   => !!$rawParameter['is_variadic'],
                'isOptional'   => !!$rawParameter['is_optional']
            ];
        }

        $throws = unserialize($rawInfo['throws_serialized']);

        $throwsAssoc = [];

        foreach ($throws as $throws) {
            $throwsAssoc[$throws['type']] = $throws['description'];
        }

        return [
            'name'              => $rawInfo['name'],
            'fqcn'              => $rawInfo['fqcn'],
            'isBuiltin'         => !!$rawInfo['is_builtin'],
            'startLine'         => (int) $rawInfo['start_line'],
            'endLine'           => (int) $rawInfo['end_line'],
            'filename'          => $rawInfo['path'],

            'parameters'        => $parameters,
            'throws'            => $throwsAssoc,
            'isDeprecated'      => !!$rawInfo['is_deprecated'],
            'hasDocblock'       => !!$rawInfo['has_docblock'],
            'hasDocumentation'  => !!$rawInfo['has_docblock'],

            'shortDescription'  => $rawInfo['short_description'],
            'longDescription'   => $rawInfo['long_description'],
            'returnDescription' => $rawInfo['return_description'],

            'returnTypeHint'    => $rawInfo['return_type_hint'],
            'returnTypes'       => $this->getReturnTypeDataForSerializedTypes($rawInfo['return_types_serialized'])
        ];
    }
}
