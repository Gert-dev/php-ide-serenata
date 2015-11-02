<?php

namespace PhpIntegrator;

class FileParser
{
    const USE_PATTERN = '/^\s*``(?:use)(?:[^\w\\\\])([\w\\\\]+)(?![\w\\\\])(?:(?:[ ]+as[ ]+)(\w+))?(?:;)/';
    const NAMESPACE_PATTERN = '/^\s*(?:namespace)(?:[^\w\\\\])([\w\\\\]+)(?![\w\\\\])(?:;)/';
    const DEFINITION_PATTERN = '/^\s*(?:abstract class|class|trait|interface)\s+(\w+)/';

    /**
     * @var string Handler to the file
     */
    protected $file;

    /**
     * Open the given file
     * @param string $filePath Path to the PHP file
     */
    public function __construct($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception(sprintf('File %s not found', $filePath));
        }

        $this->file = fopen($filePath, 'r');
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        fclose($this->file);
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

        while (!feof($this->file)) {
            $line = fgets($this->file);

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

        return $fullClass;
    }
}

?>
