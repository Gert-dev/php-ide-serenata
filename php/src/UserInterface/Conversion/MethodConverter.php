<?php

namespace PhpIntegrator\UserInterface\Conversion;

/**
 * Converts raw method data from the index to more useful data.
 */
class MethodConverter extends FunctionConverter
{
    /**
     * @param array $rawInfo
     *
     * @return array
     */
    public function convert(array $rawInfo)
    {
        return array_merge(parent::convert($rawInfo), [
            'isMagic'            => !!$rawInfo['is_magic'],
            'isPublic'           => ($rawInfo['access_modifier'] === 'public'),
            'isProtected'        => ($rawInfo['access_modifier'] === 'protected'),
            'isPrivate'          => ($rawInfo['access_modifier'] === 'private'),
            'isStatic'           => !!$rawInfo['is_static'],
            'isAbstract'         => !!$rawInfo['is_abstract'],
            'isFinal'            => !!$rawInfo['is_final']
        ]);
    }
}
