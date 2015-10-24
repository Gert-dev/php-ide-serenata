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
        switch($type) {
            case 'function':
                $reflection = new \ReflectionFunction($name);
                break;

            case 'method':
                $reflection = new \ReflectionMethod($className, $name);
                break;

            case 'property':
                $reflection = new \ReflectionProperty($className, $name);
                break;

            default:
                throw new \Exception(sprintf('Unknown type %s', $type));
        }

        $comment = $reflection->getDocComment();
        return $this->parse($comment, $filters, $name);
    }

    /**
     * Parse the comment string to get its elements
     *
     * @param string|false|null $docblock The docblock to parse. If null, the return array will be filled up with the
     *                                    correct keys, but they will be empty.
     * @param array             $filters  Elements to search (see constants).
     * @param string            $itemName The name of the item (method, class, ...) the docblock is for.
     *
     * @return array
     */
    public function parse($docblock, array $filters, $itemName)
    {
        if (empty($filters)) {
            return array();
        }

        $tags = array();
        $result = array();
        $matches = array();

        $docblock = is_string($docblock) ? $docblock : null;

        if ($docblock) {
            preg_match_all('/\*\s+(@[a-z-]+)([^@]*)\n/', $docblock, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                if (!isset($tags[$match[1]])) {
                    $tags[$match[1]] = array();
                }

                $tagValue = $match[2];
                $tagValue = $this->normalizeNewlines($tagValue);

                // Remove the delimiters of the docblock itself at the start of each line, if any.
                $tagValue = preg_replace('/\n\s+\*\s*/', ' ', $tagValue);

                // Collapse multiple spaces, just like HTML does.
                $tagValue = preg_replace('/\s\s+/', ' ', $tagValue);

                $tags[$match[1]][] = trim($tagValue);
            }
        }

        $filterMethodMap = array(
            static::RETURN_VALUE => 'filterReturn',
            static::PARAM_TYPE   => 'filterParams',
            static::VAR_TYPE     => 'filterVar',
            static::DEPRECATED   => 'filterDeprecated',
            static::THROWS       => 'filterThrows',
            static::DESCRIPTION  => 'filterDescription'
        );

        foreach ($filters as $filter) {
            if (!isset($filterMethodMap[$filter])) {
                throw new \UnexpectedValueException('Unknown filter passed!');
            }

            $result = array_merge(
                $result,
                $this->{$filterMethodMap[$filter]}($docblock, $methodName, $tags)
            );
        }

        return $result;
    }

    /**
     * Returns an array of three values, the first value will go up until the first space, the second value will go up
     * until the second space, and the third value will contain the rest of the string. Convenience method for tags that
     * consist of three parameters.
     *
     * @param string $value
     *
     * @return string[]
     */
    protected function filterThreeParameterTag($value)
    {
        $parts = explode(' ', $value);

        $firstPart = trim(array_shift($parts));
        $secondPart = trim(array_shift($parts));

        if (!empty($parts)) {
            $thirdPart = trim(implode(' ', $parts));
        } else {
            $thirdPart = null;
        }

        return array($firstPart ?: null, $secondPart ?: null, $thirdPart);
    }

    /**
     * Returns an array of two values, the first value will go up until the first space and the second value will
     * contain the rest of the string. Convenience method for tags that consist of two parameters.
     *
     * @param string $value
     *
     * @return string[]
     */
    protected function filterTwoParameterTag($value)
    {
        list($firstPart, $secondPart, $thirdPart) = $this->filterThreeParameterTag($value);

        return array($firstPart, trim($secondPart . ' ' . $thirdPart));
    }

    /**
     * Filters out information about the return value of the function or method.
     *
     * @param string $docblock
     * @param string $methodName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterReturn($docblock, $methodName, array $tags)
    {
        if (isset($tags[static::RETURN_VALUE])) {
            list($type, $description) = $this->filterTwoParameterTag($tags[static::RETURN_VALUE][0]);
        } else {
            // According to http://www.phpdoc.org/docs/latest/guides/docblocks.html, a method that does
            // have a docblock, but no explicit return type returns void. Constructors, however, must
            // return self. If there is no docblock at all, we can't assume either of these types.
            $type = ($methodName === '__construct') ? 'self' : 'void';
            $description = null;
        }

        return array(
            'return' => array(
                'type'        => $type,
                'description' => $description
            )
        );
    }

    /**
     * Filters out information about the parameters of the function or method.
     *
     * @param string $docblock
     * @param string $methodName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterParams($docblock, $methodName, array $tags)
    {
        $params = array();

        if (isset($tags[static::PARAM_TYPE])) {
            foreach ($tags[static::PARAM_TYPE] as $tag) {
                list($type, $variableName, $description) = $this->filterThreeParameterTag($tag);

                $params[$variableName] = array(
                    'type'        => $type,
                    'description' => $description
                );
            }
        }

        return array(
            'params' => $params
        );
    }

    /**
     * Filters out information about the variable.
     *
     * @param string $docblock
     * @param string $methodName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterVar($docblock, $methodName, array $tags)
    {
        if (isset($tags[static::VAR_TYPE])) {
            list($type, $description) = $this->filterTwoParameterTag($tags[static::VAR_TYPE][0]);
        } else {
            $type = null;
        }

        return array(
            'var' => array(
                'type'        => $type,
                'description' => $description
            )
        );
    }

    /**
     * Filters out deprecation information.
     *
     * @param string $docblock
     * @param string $methodName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterDeprecated($docblock, $methodName, array $tags)
    {
        return array(
            'deprecated' => isset($tags[static::DEPRECATED])
        );
    }

    /**
     * Filters out information about what exceptions the method can throw.
     *
     * @param string $docblock
     * @param string $methodName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterThrows($docblock, $methodName, array $tags)
    {
        $throws = array();

        if (isset($tags[static::THROWS])) {
            foreach ($tags[static::THROWS] as $tag) {
                list($type, $description) = $this->filterTwoParameterTag($tag);

                $throws[$type] = $description;
            }
        }

        return array(
            'throws' => $throws
        );
    }

    /**
     * Filters out information about the description.
     *
     * @param string $docblock
     * @param string $methodName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterDescription($docblock, $methodName, array $tags)
    {
        $summary = '';
        $description = '';

        $lines = explode("\n", $docblock);

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
            'descriptions' => array(
                'short' => trim($summary),
                'long'  => trim($description)
            )
        );
    }

    /**
     * Retrieves the specified string with its line separators replaced with the specifed separator.
     *
     * @param  string $string
     * @param  string $replacement
     *
     * @return string
     */
    protected function replaceNewlines($string, $replacement)
    {
        return str_replace(array("\n", "\r\n", PHP_EOL), $replacement, $string);
    }

    /**
     * Normalizes all types of newlines to the "\n" separator.
     *
     * @param  string $string
     *
     * @return string
     */
    protected function normalizeNewlines($string)
    {
        return $this->replaceNewlines($string, "\n");
    }
}
