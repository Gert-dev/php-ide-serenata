<?php

namespace PhpIntegrator;

/**
 * Parser for PHP documentation
 */
class DocParser
{
    const RETURN_VALUE = '@return';
    const PARAM_TYPE = '@param';
    const VAR_TYPE = '@var';
    const DEPRECATED = '@deprecated';
    const THROWS = '@throws';
    const DESCRIPTION = 'description';
    const INHERITDOC = '{@inheritDoc}';

    const TYPE_SPLITTER = '|';
    const TAG_START_REGEX = '/^\s*\*\s*\@[^@]+$/';

    /**
     * Get data for the given class
     * @param  string|null $className Full class namespace, required for methods and properties
     * @param  string      $type      Type searched (method, property)
     * @param  string      $name      Name of the method or property
     * @param  array       $filters   Fields to get
     * @return array
     */
    public function get($className, $type, $name, $filters)
    {
        $isConstructor = false;

        switch($type) {
            case 'function':
                $reflection = new \ReflectionFunction($name);
                break;

            case 'method':
                $isConstructor = ($name === '__construct');
                $reflection = new \ReflectionMethod($className, $name);
                break;

            case 'property':
                $reflection = new \ReflectionProperty($className, $name);
                break;

            default:
                throw new \Exception(sprintf('Unknown type %s', $type));
        }

        $comment = $reflection->getDocComment();
        return $this->parse($comment, $filters, $isConstructor);
    }

    /**
     * Parse the comment string to get its elements
     *
     * @param string|false|null $comment       The docblock to parse. If null, the return array will be filled up with the
     *                                         correct keys, but they will be empty.
     * @param array             $filters       Elements to search (see constants).
     * @param bool              $isConstructor Whether or not the method the docblock is for is a constructor.
     *
     * @return array
     */
    public function parse($comment, array $filters, $isConstructor)
    {
        $comment = is_string($comment) ? $comment : null;

        if ($comment) {
            $strippedComment = str_replace(array('*', '/'), '', $comment);
            $escapedComment = $this->replaceNewlines($strippedComment, ' ');
        }

        $result = array();

        foreach($filters as $filter) {
            switch ($filter) {
                case self::VAR_TYPE:
                    $result['var'] = null;

                    if ($comment) {
                        $var = $this->parseVar($escapedComment);
                        $result['var'] = $var ?: null;
                    }

                    break;

                case self::RETURN_VALUE:
                    $result['return'] = null;

                    if ($comment) {
                        $return = $this->parseVar($escapedComment, self::RETURN_VALUE);

                        if ($return) {
                            $result['return'] = $return;
                        } else {
                            // According to http://www.phpdoc.org/docs/latest/guides/docblocks.html, a method that does
                            // have a docblock, but no explicit return type returns void. Constructors, however, must
                            // return self. If there is no docblock at all, we can't assume either of these types.
                            $result['return'] = $isConstructor ? 'self' : 'void';
                        }
                    }

                    break;

                case self::PARAM_TYPE:
                    $result['params'] = array();

                    if ($comment) {
                        $res = $escapedComment;

                        while (null !== $ret = $this->parseParams($res)) {
                            $result['params'][$ret['name']] = $ret['type'];
                            $res = $ret['string'];
                        }
                    }

                    break;

                case self::THROWS:
                    $result['throws'] = array();

                    if ($comment) {
                        $res = $escapedComment;

                        while (null !== $ret = $this->parseThrows($res)) {
                            $res = $ret['string'];
                            $result['throws'][$ret['type']] = $ret['description'];
                        }
                    }

                    break;

                case self::DESCRIPTION:
                    $result['descriptions'] = array(
                        'short' => '',
                        'long'  => ''
                    );

                    if ($comment) {
                        list($summary, $description) = $this->parseDescription($comment);

                        $result['descriptions']['short'] = $summary;
                        $result['descriptions']['long']  = $description;
                    }

                    break;

                case self::DEPRECATED:
                    $result['deprecated'] = false;

                    if ($comment) {
                        $result['deprecated'] = (false !== strpos($escapedComment, self::DEPRECATED));
                    }

                    break;
            }
        }

        return $result;
    }

    /**
     * Retrieves the specified string with its line separators replaced with the specifed separator.
     *
     * @param  string $string
     * @param  string $replacement
     *
     * @return string
     */
    private function replaceNewlines($string, $replacement)
    {
        return str_replace(array('\n', '\r\n', PHP_EOL), $replacement, $string);
    }

    /**
     * Normalizes all types of newlines to the "\n" separator.
     *
     * @param  string $string
     *
     * @return string
     */
    private function normalizeNewlines($string)
    {
        return $this->replaceNewlines($string, "\n");
    }

    /**
     * Search for the long and short description on a method or attribute
     *
     * @param string $comment Comment
     *
     * @return array ('short' => short description, 'long' => long description)
     */
    private function parseDescription($comment)
    {
        $summary = '';
        $description = '';

        $collapsedComment = $this->normalizeNewlines($comment);

        $lines = explode("\n", $collapsedComment);

        $isReadingSummary = true;

        foreach ($lines as $i => $line) {
            if (preg_match(self::TAG_START_REGEX, $line) === 1) {
                break; // Found the start of a tag, the summary and description are finished.
            }

            // Remove the opening and closing tags.
            $line = preg_replace('/^\s*(?:\/)?\*+(?:\/)?/', '', $line);
            $line = preg_replace('/\s*\*+\/$/', '', $line);

            $line = trim($line);

            if ($isReadingSummary && empty($line) && !empty($summary)) {
                $isReadingSummary = false;
            } elseif ($isReadingSummary) {
                $summary = empty($summary) ? $line : ($summary . "\n" . $line);
            } else {
                $description = empty($description) ? $line : ($description . "\n" . $line);
            }
        }

        return array(
            trim($summary),
            trim($description)
        );
    }

    /**
     * Search for a $type in the comment and its value
     * @param string $string comment string
     * @param string $type   annotation type searched
     * @return string
     */
    private function parseVar($string, $type = self::VAR_TYPE)
    {
        if (false === $pos = strpos($string, $type)) {
            return null;
        }

        $varSubstring = substr(
            $string,
            $pos + strlen($type),
            strlen($string)-1
        );
        $varSubstring = trim($varSubstring);

        if (empty($varSubstring)) {
            return null;
        }

        $elements = explode(' ', $varSubstring);
        return $elements[0];
    }

    /**
     * Search all @param annotations in the given string
     * @param string $string String comment to search
     * @return string
     */
    private function parseParams($string)
    {
        if (false === $pos = strpos($string, self::PARAM_TYPE)) {
            return null;
        }

        $paramSubstring = substr(
            $string,
            $pos + strlen(self::PARAM_TYPE),
            strlen($string)-1
        );
        $paramSubstring = trim($paramSubstring);

        if (empty($paramSubstring)) {
            return null;
        }

        $elements = explode(' ', $paramSubstring);
        if (count($elements) < 2) {
            return null;
        }

        return array(
            'name' => $elements[1],
            'type' => $elements[0],
            'string' => $paramSubstring
        );
    }

    /**
     * Search all @throws annotations in the given string
     * @param string $string String comment to search
     * @return string
     */
    private function parseThrows($string)
    {
        if (false === $pos = strpos($string, self::THROWS)) {
            return null;
        }

        $throwsSubstring = substr(
            $string,
            $pos + strlen(self::THROWS),
            strlen($string)-1
        );
        $throwsSubstring = trim($throwsSubstring);

        if (empty($throwsSubstring)) {
            return null;
        }

        // Make sure we don't use the rest of the docblock as description of the exception type.
        // NOTE: The next tag detection can probably be improved at a later stage.
        $substringToExplode = $throwsSubstring;
        $nextTag = strpos($throwsSubstring, '@');

        if ($nextTag !== false) {
            $substringToExplode = substr($throwsSubstring, 0, $nextTag);
        }

        $elements = explode(' ', $substringToExplode);

        return array(
            'type' => trim(array_shift($elements)),
            'description' => !empty($elements) ? trim(implode(' ', $elements)) : null,
            'string' => $throwsSubstring
        );
    }
}
