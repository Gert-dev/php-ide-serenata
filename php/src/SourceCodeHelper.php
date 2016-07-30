<?php

namespace PhpIntegrator;

use UnexpectedValueException;

/**
 * Contains utility functionality for dealing with source code.
 */
class SourceCodeHelper
{
    /**
     * @param string|null $file
     * @param bool        $isStdin
     *
     * @throws UnexpectedValueException
     */
    public function getSourceCode($file, $isStdin)
    {
        $code = null;

        if ($isStdin) {
            // NOTE: This call is blocking if there is no input!
            $code = file_get_contents('php://stdin');
        } else {
            if (!$file) {
                throw new UnexpectedValueException('The specified file does not exist!');
            }

            $code = @file_get_contents($file);
        }

        $encoding = mb_detect_encoding($code, null, true);

        if (!in_array($encoding, ['UTF-8', 'ASCII'], true)) {
            $code = mb_convert_encoding($code, 'UTF-8', $encoding);
        }

        return $code;
    }

    /**
     * Calculates the 1-indexed line the specified byte offset is located at.
     *
     * @param string $source
     * @param int    $offset
     *
     * @return int
     */
    public function calculateLineByOffset($source, $offset)
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * Retrieves the character offset from the specified byte offset in the specified string. The result will always be
     * smaller than or equal to the passed in value, depending on the amount of multi-byte characters encountered.
     *
     * @param string $string
     * @param int    $byteOffset
     *
     * @return int
     */
    public function getCharacterOffsetFromByteOffset($byteOffset, $string)
    {
        return mb_strlen(mb_strcut($string, 0, $byteOffset));
    }
}
