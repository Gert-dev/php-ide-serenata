<?php

namespace PhpIntegrator;

// TODO: This can probably be obsoleted and replaced with php-parser based parsing.
class FileParser
{
    const USE_PATTERN = '/^\s*(?:use)(?:[^\w\\\\])([\w\\\\]+)(?![\w\\\\])(?:(?:[ ]+as[ ]+)(\w+))?(?:;)/';
    const NAMESPACE_PATTERN = '/^\s*(?:namespace)(?:[^\w\\\\])([\w\\\\]+)(?![\w\\\\])(?:;)/';
    const DEFINITION_PATTERN = '/^\s*(?:abstract class|class|trait|interface)\s+(\w+)/';

    /**
     * @var string
     */
    protected $filename;

    /**
     * Constructor.
     *
     * @param string $filename
     */
    public function __construct($filename)
    {
        if (!file_exists($filename)) {
            throw new \Exception(sprintf('File %s not found', $filename));
        }

        $this->filename = $filename;
    }

    /**
     * Retrieves the line the specified regular expression first matches at. The first line has a value of 1.
     *
     * @return int|null
     */
    public function getLineForRegex($regex)
    {
        $lineNum = 1;
        $found = false;
        $matches = null;
        $file = fopen($this->filename, 'r');

        while (!feof($file)) {
            $line = fgets($file);

            if (preg_match($regex, $line, $matches) === 1) {
                $found = true;
                break;
            }

            ++$lineNum;
        }

        fclose($file);

        return $found ? $lineNum : null;
    }

    /**
     * Retrieves the full class name of the given class, based on the namespace and use statements in the current file.
     *
     * @param string|null $className The class to search for. If null, the full class name of the first
     *                               class/trait/interface definition will be returned.
     * @param bool        $found     Set to true if an explicit use statement was found. If false, the full class name
     *                               could, for example, have been built using the namespace of the current file.
     *
     * @return string
     */
    public function getFullClassName($className, &$found)
    {
        if (!empty($className) && $className[0] == "\\") {
            return substr($className, 1); // FQCN, not subject to any further context.
        }

        $line = '';
        $matches = [];
        $found = false;
        $fullClass = $className;

        $file = fopen($this->filename, 'r');

        while (!feof($file)) {
            $line = fgets($file);

            if (preg_match(self::NAMESPACE_PATTERN, $line, $matches) === 1) {
                // The class name is relative to the namespace of the class it is contained in, unless a use statement
                // says otherwise.
                $fullClass = $matches[1] . '\\' . $className;
            } elseif ($className && preg_match(self::USE_PATTERN, $line, $matches) === 1) {
                $classNameParts = explode('\\', $className);
                $importNameParts = explode('\\', $matches[1]);

                $isAliasedImport = isset($matches[2]);

                if (($isAliasedImport && $matches[2] === $classNameParts[0]) ||
                    (!$isAliasedImport && $importNameParts[count($importNameParts) - 1] === $classNameParts[0])) {
                    $found = true;

                    $fullClass = $matches[1];

                    array_shift($classNameParts);

                    if (!empty($classNameParts)) {
                        $fullClass .= '\\' . implode('\\', $classNameParts);
                    }

                    break;
                }
            }

            if (preg_match(self::DEFINITION_PATTERN, $line, $matches) === 1) {
                if (!$className) {
                    $found = true;
                    $fullClass .= $matches[1];
                }

                break;
            }
        }

        if ($fullClass && $fullClass[0] == '\\') {
            $fullClass = substr($fullClass, 1);
        }

        fclose($file);

        return $fullClass;
    }
}

?>
