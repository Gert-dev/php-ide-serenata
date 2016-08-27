<?php

namespace PhpIntegrator;

use UnexpectedValueException;

/**
 * Contains static utility functionality for dealing with source code.
 */
class SourceCodeHelpers
{
    /**
     * Calculates the 1-indexed line the specified byte offset is located at.
     *
     * @param string $source
     * @param int    $offset
     *
     * @return int
     */
    public static function calculateLineByOffset($source, $offset)
    {
        if (!$offset) {
            return 1;
        }

        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * Retrieves the character offset from the specified byte offset in the specified string. The result will always be
     * smaller than or equal to the passed in value, depending on the amount of multi-byte characters encountered.
     *
     * @param int    $byteOffset
     * @param string $string
     *
     * @return int
     */
    public static function getCharacterOffsetFromByteOffset($byteOffset, $string)
    {
        return mb_strlen(mb_strcut($string, 0, $byteOffset));
    }

    /**
     * Retrieves the byte offset from the specified character offset in the specified string. The result will always be
     * larger than or equal to the passed in value, depending on the amount of multi-byte characters encountered.
     *
     * @param int    $characterOffset
     * @param string $string
     *
     * @return int
     */
    public static function getByteOffsetFromCharacterOffset($characterOffset, $string)
    {
        return strlen(mb_substr($string, 0, $characterOffset));
    }
}
