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

    /**
     * Retrieves the start of the expression that ends at the end of the specified source code string.
     *
     * @param string $code
     *
     * @return int
     */
    public function getStartOfExpression($code)
    {
        // TODO
    }

    /**
     * @param string $source
     *
     * @return string[]
     */
    public function retrieveSanitizedCallStackAt($source)
    {
        $boundary = $this->getStartOfExpression($source);

        $expression = mb_substr($source, $boundary);

        return $this->retrieveSanitizedCallStack($expression);
    }

    /**
     * @param string $text
     *
     * @return string[]
     */
    protected function retrieveSanitizedCallStack($text)
    {
        // FIXME: Rough translation of CoffeeScript method.

        $text = trim($text);
        $text = preg_replace('/\/\/.*\n/', '', $text);              // Remove singe line comments.
        $text = preg_replace('/\/\*(.|\n)*?\*\//', '', $text);      // Remove multi-line comments.

        // The start of the call stack may be wrapped in parentheses, e.g. ""(new Foo())->test", unwrap them. Note that
        // "($this)->" is invalid (at least in PHP 5.6).
        $text = preg_replace_callback('/^\(new\s+(.|\n)+?\)/', function ($match) {
            return mb_substr($match[0], 1, -1);
        }, $text);

        if (preg_match('/function\s+([A-Za-z0-9_]\s*)?\(/', $text) === 1) {
            $text = $this->stripPairContent($text, '{', '}');
        }

        // Remove content inside parantheses (including nested parantheses).
        $text = $this->stripPairContent($text, '(', ')');

        if (!$text) {
            return [];
        }

        $newElements = [];

        foreach (explode('->', $text) as $part) {
            foreach (explode('::', $part) as $subPart) {
                $newElements[] = trim($subPart);
            }
        }

        return $newElements;
    }

    /**
     * @param string $text
     * @param string $openCharacter
     * @param string $closeCharacter
     *
     * @return string
     */
    protected function stripPairContent($text, $openCharacter, $closeCharacter)
    {
        // FIXME: Rough translation of CoffeeScript method.

        $i = 0;
        $openCount = 0;
        $closeCount = 0;
        $startIndex = -1;

        while ($i < mb_strlen($text)) {
            if ($text[$i] === $openCharacter) {
                ++$openCount;

                if ($openCount === 1) {
                    $startIndex = $i;
                }
            } elseif ($text[$i] === $closeCharacter) {
                ++$closeCount;

                if ($closeCount === $openCount) {
                    $originalLength = mb_strlen($text);
                    $text = mb_substr($text, 0, $startIndex + 1) . mb_substr($text, $i, $originalLength);

                    $i -= ($originalLength - mb_strlen($text));

                    $openCount = 0;
                    $closeCount = 0;
                }
            }

            ++$i;
        }

        return $text;
    }
}
