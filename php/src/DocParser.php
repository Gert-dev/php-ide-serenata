<?php

namespace PhpIntegrator;

/**
 * Parser for PHP documentation.
 */
class DocParser
{
    const VAR_TYPE        = '@var';
    const PARAM_TYPE      = '@param';
    const THROWS          = '@throws';
    const RETURN_VALUE    = '@return';
    const DEPRECATED      = '@deprecated';

    const METHOD          = "@method";

    const PROPERTY        = '@property';
    const PROPERTY_READ   = '@property-read';
    const PROPERTY_WRITE  = '@property-write';

    const CATEGORY        = '@category';
    const SUBPACKAGE      = '@subpackage';
    const LINK            = '@link';

    const DESCRIPTION     = 'description';
    const INHERITDOC      = '{@inheritDoc}';

    const TYPE_SPLITTER   = '|';
    const TAG_START_REGEX = '/^\s*(?:\/\*)?\*\s+(\@.+)(?:\*\/)?$/';

    /**
     * @var DocblockAnalyzer|null
     */
    protected $docblockAnalyzer;

    /**
     * Parse the comment string to get its elements.
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
            return [];
        }

        $tags = [];
        $result = [];
        $matches = [];

        $docblock = is_string($docblock) ? $docblock : null;

        if ($docblock) {
            preg_match_all('/\*\s+(@[a-z-]+)([^@]*)(?:\n|\*\/)/', $docblock, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                if (!isset($tags[$match[1]])) {
                    $tags[$match[1]] = [];
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

        $filterMethodMap = [
            static::RETURN_VALUE   => 'filterReturn',
            static::PARAM_TYPE     => 'filterParams',
            static::VAR_TYPE       => 'filterVar',
            static::DEPRECATED     => 'filterDeprecated',
            static::THROWS         => 'filterThrows',
            static::DESCRIPTION    => 'filterDescription',

            static::METHOD         => 'filterMethod',

            static::PROPERTY       => 'filterProperty',
            static::PROPERTY_READ  => 'filterPropertyRead',
            static::PROPERTY_WRITE => 'filterPropertyWrite',

            static::CATEGORY       => 'filterCategory',
            static::SUBPACKAGE     => 'filterSubpackage',
            static::LINK           => 'filterLink'
        ];

        foreach ($filters as $filter) {
            if (!isset($filterMethodMap[$filter])) {
                throw new \UnexpectedValueException('Unknown filter passed!');
            }

            $result = array_merge(
                $result,
                $this->{$filterMethodMap[$filter]}($docblock, $itemName, $tags)
            );
        }

        return $result;
    }

    /**
     * Returns an array of $partCount values, the first value will go up until the first space, the second value will
     * go up until the second space, and so on. The last value will contain the rest of the string. Convenience method
     * for tags that consist of multiple parameters. This method returns an array with guaranteed $partCount elements.
     *
     * @param string $value
     * @param int    $partCount
     *
     * @return string[]
     */
    protected function filterParameterTag($value, $partCount)
    {
        $segments = [];
        $parts = explode(' ', $value);

        while ($partCount--) {
            if (!empty($parts)) {
                $segments[] = $this->sanitizeText(array_shift($parts));
            } else {
                $segments[] = null;
            }
        }

        // Append the remaining text to the last element.
        if (!empty($parts)) {
            $segments[count($segments) - 1] .= ' ' . implode(' ', $parts);
        }

        return $segments;
    }

    /**
     * Filters out information about the return value of the function or method.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterReturn($docblock, $itemName, array $tags)
    {
        if (isset($tags[static::RETURN_VALUE])) {
            list($type, $description) = $this->filterParameterTag($tags[static::RETURN_VALUE][0], 2);
        } else {
            $type = null;
            $description = null;

            // According to http://www.phpdoc.org/docs/latest/guides/docblocks.html, a method that does have a docblock,
            // but no explicit return type returns void. Constructors, however, must return self. If there is no
            // docblock at all, we can't assume either of these types.
            if ($docblock !== null) {
                $type = ($itemName === '__construct') ? 'self' : 'void';
            }
        }

        return [
            'return' => [
                'type'        => $type,
                'description' => $description
            ]
        ];
    }

    /**
     * Filters out information about the parameters of the function or method.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterParams($docblock, $itemName, array $tags)
    {
        $params = [];

        if (isset($tags[static::PARAM_TYPE])) {
            foreach ($tags[static::PARAM_TYPE] as $tag) {
                list($type, $variableName, $description) = $this->filterParameterTag($tag, 3);

                $isVariadic = false;
                $isReference = false;

                if (mb_strpos($variableName, '...') === 0) {
                    $isVariadic = true;
                    $variableName = mb_substr($variableName, mb_strlen('...'));
                }

                if (mb_strpos($variableName, '&amp;') === 0) {
                    $isReference = true;
                    $variableName = mb_substr($variableName, mb_strlen('&amp;'));
                }

                $params[$variableName] = [
                    'type'        => $type,
                    'description' => $description,
                    'isVariadic'  => $isVariadic,
                    'isReference' => $isReference
                ];
            }
        }

        return [
            'params' => $params
        ];
    }

    /**
     * Filters out information about the variable.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterVar($docblock, $itemName, array $tags)
    {
        $type = null;
        $description = null;

        if (isset($tags[static::VAR_TYPE])) {
            list($varType, $varName, $varDescription) = $this->filterParameterTag($tags[static::VAR_TYPE][0], 3);

            if ($varName) {
                if (mb_substr($varName, 0, 1) === '$') {
                    // Example: "@var DateTime $foo My description". The tag includes the name of the property it
                    // documents, it must match the property we're fetching documentation about.
                    if (mb_substr($varName, 1) === $itemName) {
                        $type = $varType;
                        $description = $varDescription;
                    }
                } else {
                    // Example: "@var DateTime My description".
                    $type = $varType;
                    $description = trim($varName . ' ' . $varDescription);
                }
            } else if (!$varName && !$varDescription) {
                // Example: "@var DateTime".
                $type = $varType;
            }
        }

        return [
            'var' => [
                'type'        => $type,
                'description' => $description
            ]
        ];
    }

    /**
     * Filters out deprecation information.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterDeprecated($docblock, $itemName, array $tags)
    {
        return [
            'deprecated' => isset($tags[static::DEPRECATED])
        ];
    }

    /**
     * Filters out information about what exceptions the method can throw.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterThrows($docblock, $itemName, array $tags)
    {
        $throws = [];

        if (isset($tags[static::THROWS])) {
            foreach ($tags[static::THROWS] as $tag) {
                list($type, $description) = $this->filterParameterTag($tag, 2);

                if ($type && $description) {
                    $throws[$type] = $description;
                }
            }
        }

        return [
            'throws' => $throws
        ];
    }

    /**
     * Filters out information about the magic methods of a class.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterMethod($docblock, $itemName, array $tags)
    {
        $methods = [];

        if (isset($tags[static::METHOD])) {
            foreach ($tags[static::METHOD] as $tag) {
                // The method signature can contain spaces, so we can't use a simple filterParameterTag.
                if (preg_match('/^(static\s+)?(?:(\S+)\s+)?([A-Za-z0-9_]+\(.*\))(?:\s+(.+))?$/', $tag, $match) !== false) {
                    $partCount = count($match);

                    if ($partCount == 5) {
                        $type = $match[2];
                        $methodSignature = $match[3];
                        $description = $match[4];
                    } else if ($partCount == 4) {
                        if (empty($match[2])) {
                            $type = 'void';
                            $methodSignature = $match[3];
                            $description = null;
                        } elseif (mb_strpos($match[2], '(') != false) {
                            // The return type was omitted, e.g. '@method foo() My description.', in which case the
                            // method returns 'void'.
                            $type = 'void';
                            $methodSignature = $match[2];
                            $description = $match[3];
                        } else {
                            // The description was omitted.
                            $type = $match[2];
                            $methodSignature = $match[3];
                            $description = null;
                        }
                    } else {
                        continue; // Empty @method tag, skip it.
                    }

                    $isStatic = (trim($match[1]) === 'static');

                    $requiredParameters = [];
                    $optionalParameters = [];

                    if (preg_match('/^([A-Za-z0-9_]+)\((.*)\)$/', $methodSignature, $match) !== false) {
                        $methodName = $match[1];
                        $methodParameterList = $match[2];

                        // NOTE: Example string: "$param1, int $param2, $param3 = array(), SOME\\TYPE_1 $param4 = null".
                        preg_match_all('/(?:(\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)\s+)?(\$[A-Za-z0-9_]+)(?:\s*=\s*([^,]+))?(?:,|$)/', $methodParameterList, $matches, PREG_SET_ORDER);

                        foreach ($matches as $match) {
                            $partCount = count($match);

                            if ($partCount == 4) {
                                $parameterType = $match[1];
                                $parameterName = $match[2];
                                $defaultValue = $match[3];
                            } elseif ($partCount == 3) {
                                if (!empty($match[1]) && $match[1][0] == '$') {
                                    $parameterType = null;
                                    $parameterName = $match[1];
                                    $defaultValue = $match[2];
                                } else {
                                    $parameterType = $match[1] ?: null;
                                    $parameterName = $match[2];
                                    $defaultValue = null;
                                }
                            } /*elseif ($partCount == 2) {
                                // NOTE: Caught by $partCount == 3 (the type will be an empty string).
                                $parameterType = null;
                                $parameterName = $match[1];
                                $defaultValue = null;
                            } */

                            $data = [
                                'type'         => $parameterType,
                                'defaultValue' => $defaultValue
                            ];

                            if (!$defaultValue) {
                                $requiredParameters[$parameterName] = $data;
                            } else {
                                $optionalParameters[$parameterName] = $data;
                            }

                        }
                    } else {
                        continue; // Invalid method signature.
                    }

                    $methods[$methodName] = [
                        'type'                => $type,
                        'isStatic'            => $isStatic,
                        'requiredParameters'  => $requiredParameters,
                        'optionalParameters'  => $optionalParameters,
                        'description'         => $description
                    ];
                }
            }
        }

        return [
            'methods' => $methods
        ];
    }

    /**
     * Filters out information about the magic properties of a class.
     *
     * @param string $tagName
     * @param string $keyName
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterPropertyTag($tagName, $keyName, $docblock, $itemName, array $tags)
    {
        $properties = [];

        if (isset($tags[$tagName])) {
            foreach ($tags[$tagName] as $tag) {
                list($staticKeyword, $type, $variableName, $description) = $this->filterParameterTag($tag, 4);

                // Normally, this tag consists of three parts. However, PHPStorm uses an extended syntax that allows
                // putting the keyword 'static' as first part of the tag to indicate that the property is indeed static.
                if ($staticKeyword !== 'static') {
                    list($type, $variableName, $description) = $this->filterParameterTag($tag, 3);
                }

                $properties[$variableName] = [
                    'type'        => $type,
                    'isStatic'    => ($staticKeyword === 'static'),
                    'description' => $description
                ];
            }
        }

        return [
            $keyName => $properties
        ];
    }

    /**
     * Filters out information about the magic properties of a class.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterProperty($docblock, $itemName, array $tags)
    {
        return $this->filterPropertyTag(static::PROPERTY, 'properties', $docblock, $itemName, $tags);
    }

    /**
     * Filters out information about the magic properties of a class.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterPropertyRead($docblock, $itemName, array $tags)
    {
        return $this->filterPropertyTag(static::PROPERTY_READ, 'propertiesReadOnly', $docblock, $itemName, $tags);
    }

    /**
     * Filters out information about the magic properties of a class.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterPropertyWrite($docblock, $itemName, array $tags)
    {
        return $this->filterPropertyTag(static::PROPERTY_WRITE, 'propertiesWriteOnly', $docblock, $itemName, $tags);
    }

    /**
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterCategory($docblock, $itemName, array $tags)
    {
        $description = null;

        if (isset($tags[static::CATEGORY])) {
            list($description) = $this->filterParameterTag($tags[static::CATEGORY][0], 1);
        }

        return [
            'category' => $description
        ];
    }

    /**
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterSubpackage($docblock, $itemName, array $tags)
    {
        $name = null;

        if (isset($tags[static::SUBPACKAGE])) {
            list($name) = $this->filterParameterTag($tags[static::SUBPACKAGE][0], 1);
        }

        return [
            'subpackage' => $name
        ];
    }

    /**
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterLink($docblock, $itemName, array $tags)
    {
        $links = [];

        if (isset($tags[static::LINK])) {
            list($uri, $description) = $this->filterParameterTag($tags[static::LINK][0], 2);

            $links[] = [
                'uri'         => $uri,
                'description' => $description
            ];
        }

        return [
            'link' => $links
        ];
    }

    /**
     * Filters out information about the description.
     *
     * @param string $docblock
     * @param string $itemName
     * @param array  $tags
     *
     * @return array
     */
    protected function filterDescription($docblock, $itemName, array $tags)
    {
        $summary = '';
        $description = '';

        $lines = explode("\n", $docblock);

        $isReadingSummary = true;

        foreach ($lines as $i => $line) {
            $matches = null;

            if (preg_match(self::TAG_START_REGEX, $line, $matches) === 1 && !$this->getDocblockAnalyzer()->isFullInheritDocSyntax(trim($matches[1]))) {
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

        return [
            'descriptions' => [
                'short' => $this->sanitizeText($summary),
                'long'  => $this->sanitizeText($description)
            ]
        ];
    }

    /**
     * Sanitizes text, trimming it and encoding HTML entities.
     *
     * @param string $text
     *
     * @return string
     */
    protected function sanitizeText($text)
    {
        return trim(htmlentities($text));
    }

    /**
     * Retrieves the specified string with its line separators replaced with the specifed separator.
     *
     * @param string $string
     * @param string $replacement
     *
     * @return string
     */
    protected function replaceNewlines($string, $replacement)
    {
        return str_replace(["\n", "\r\n", PHP_EOL], $replacement, $string);
    }

    /**
     * Normalizes all types of newlines to the "\n" separator.
     *
     * @param string $string
     *
     * @return string
     */
    protected function normalizeNewlines($string)
    {
        return $this->replaceNewlines($string, "\n");
    }

    /**
     * Retrieves an instance of DocblockAnalyzer. The object will only be created once if needed.
     *
     * @return DocblockAnalyzer
     */
    protected function getDocblockAnalyzer()
    {
        if (!$this->docblockAnalyzer instanceof DocblockAnalyzer) {
            $this->docblockAnalyzer = new DocblockAnalyzer();
        }

        return $this->docblockAnalyzer;
    }
}
