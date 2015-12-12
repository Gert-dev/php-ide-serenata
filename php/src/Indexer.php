<?php

namespace PhpIntegrator;

use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;

use Doctrine\DBAL\Exception\TableNotFoundException;

use PhpParser\Lexer;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

use PhpParser\NodeVisitor\NameResolver;

/**
 * Handles indexation of PHP code.
 */
class Indexer
{
    /**
     * The storage to use for index data.
     *
     * @var IndexStorageInterface
     */
    protected $storage;

    /**
     * Constructor.
     *
     * @param IndexStorageInterface $storage
     */
    public function __construct(IndexStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Logs a banner for debugging purposes.
     *
     * @param string $message
     */
    protected function logBanner($message)
    {
        echo str_repeat('=', 80) . PHP_EOL;
        echo $message . PHP_EOL;
        echo str_repeat('=', 80) . PHP_EOL;
    }

    /**
     * Logs a single message for debugging purposes.
     *
     * @param string $message
     */
    protected function logMessage($message)
    {
        echo $message . PHP_EOL;
    }

    /**
     * Indexes the specified project using the specified database.
     *
     * @param string $projectPath
     */
    public function indexProject($projectPath)
    {
        $this->logMessage('Indexing project ' . $projectPath);

        $this->logBanner('Pass 1 - Scanning and sorting by dependencies...');

        $fileClassMap = $this->scan($projectPath);

        $fileClassMap = $this->sortScanResultByDependencies($fileClassMap);

        foreach ($fileClassMap as $filename => $fqsens) {
            echo '  - ' . $filename . PHP_EOL;
        }

        $this->logBanner('Pass 2 - Indexing outline...');

        $this->indexFileOutlines(array_keys($fileClassMap));
    }

    /**
     * Scans the specified directory, returning a mapping of file names to a list of FQSEN's contained in the file, each
     * of which are then mapped to a list of FQSEN's they depend on.
     *
     * @param string $directory
     *
     * @return array
     */
    protected function scan($directory)
    {
        $fileClassMap = [];

        $dirIterator = new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
        );

        /** @var \DirectoryIterator $fileInfo */
        foreach ((new RecursiveIteratorIterator($dirIterator)) as $filename => $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $fileClassMap[$filename] = $this->getFqsenDependenciesForFile($filename);
        }

        return $fileClassMap;
    }

    /**
     * Sorts the specified result set from the {@see scan} method to ensure that files containing structural elements
     * that depend on other structural elements end up after their dependencies in the list.
     *
     * @param array $scanResult
     *
     * @return array The input value, after sorting.
     */
    protected function sortScanResultByDependencies(array $scanResult)
    {
        uasort($scanResult, function (array $a, array $b) {
            foreach ($a as $fqsen => $dependencies) {
                foreach ($dependencies as $dependencyFqsen) {
                    if (isset($b[$dependencyFqsen])) {
                        return 1; // a is dependent on b, b must be indexed first.
                    }
                }
            }

            foreach ($b as $fqsen => $dependencies) {
                foreach ($dependencies as $dependencyFqsen) {
                    if (isset($a[$dependencyFqsen])) {
                        return -1; // b is dependent on a, a must be indexed first.
                    }
                }
            }

            return 0; // Neither are dependent on one another, order is irrelevant.
        });

        return $scanResult;
    }

    /**
     * Retrieves a list of FQSENs in the specified file along with their dependencies.
     *
     * @return array
     */
    protected function getFqsenDependenciesForFile($filename)
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $nodes = [];

        try {
            $nodes = $parser->parse(file_get_contents($filename));
        } catch (Error $e) {
            $this->logMessage('  - WARNING: ' . $filename . ' could not be indexed due to parsing errors!');
        }

        $dependencyFetchingVisitor = new DependencyFetchingVisitor();

        $traverser = new NodeTraverser();
        $traverser->addVisitor($dependencyFetchingVisitor);
        $traverser->traverse($nodes);

        return $dependencyFetchingVisitor->getFqsenDependencyMap();
    }

    /**
     * Indexes the outline of the specified files.
     *
     * @param array $filePaths
     */
    protected function indexFileOutlines(array $filePaths)
    {
        foreach ($filePaths as $filePath) {
            $this->indexFileOutline($filePath);
        }
    }

    /**
     * Indexes the outline of the specified file.
     *
     * The outline consists of functions, structural elements (classes, interfaces, traits, ...), ... contained within
     * the file. For structural elements, this also includes (direct) members, information about the parent class,
     * used traits, etc.
     *
     * @param string $filename
     */
    protected function indexFileOutline($filename)
    {
        // TODO: Initial version, has some low-hanging fruit regarding optimization.
        // TODO: Needs refactoring as well.

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $nodes = [];

        try {
            $nodes = $parser->parse(file_get_contents($filename));
        } catch (Error $e) {
            $this->logMessage('  - WARNING: ' . $filename . ' could not be indexed due to parsing errors!');
        }

        $outlineIndexingVisitor = new OutlineIndexingVisitor();

        $traverser = new NodeTraverser();
        $traverser->addVisitor($outlineIndexingVisitor);
        $traverser->traverse($nodes);

        $fileId = $this->storage->insert(IndexStorageItemEnum::FILES, [
            'path' => $filename
        ]);

        foreach ($outlineIndexingVisitor->getStructuralElements() as $fqsen => $structuralElement) {
            // TODO: Need the DocParser for the remaining bits.

            $seTypeId = $this->storage->getStructuralElementTypeId($structuralElement['type']);

            $seId = $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, [
                'name'                       => $structuralElement['name'],
                'fqsen'                      => $fqsen,
                'file_id'                    => $fileId,
                'start_line'                 => $structuralElement['startLine'],
                'structural_element_type_id' => $seTypeId,
                'is_abstract'                => (isset($structuralElement['is_abstract']) && $structuralElement['is_abstract']) ? 1 : 0,
                // 'is_deprecated'              => $structuralElement['is_deprecated'],
                // 'short_description'          => $structuralElement['short_description'],
                // 'long_description'           => $structuralElement['long_description']
            ]);

            if (isset($structuralElement['parent'])) {
                $parentSeId = $this->storage->getStructuralElementId($structuralElement['parent']);

                if ($parentSeId) {
                    $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED, [
                        'structural_element_id'        => $seId,
                        'linked_structural_element_id' => $parentSeId
                    ]);
                } else {
                    $this->logMessage(
                        '  - WARNING: Could not find a record for the parent class ' .
                        $structuralElement['parent']
                    );
                }
            }

            if (isset($structuralElement['interfaces'])) {
                foreach ($structuralElement['interfaces'] as $interface) {
                    $interfaceSeId = $this->storage->getStructuralElementId($interface);

                    if ($interfaceSeId) {
                        $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_INTERFACES_LINKED, [
                            'structural_element_id'        => $seId,
                            'linked_structural_element_id' => $interfaceSeId
                        ]);
                    } else {
                        $this->logMessage(
                            '  - WARNING: Could not find a record for the interface ' .
                            $interface
                        );
                    }
                }
            }

            if (isset($structuralElement['traits'])) {
                foreach ($structuralElement['traits'] as $trait) {
                    $traitSeId = $this->storage->getStructuralElementId($trait);

                    if ($traitSeId) {
                        $this->storage->insert(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_TRAITS_LINKED, [
                            'structural_element_id'        => $seId,
                            'linked_structural_element_id' => $traitSeId
                        ]);
                    } else {
                        $this->logMessage(
                            '  - WARNING: Could not find a record for the trait ' .
                            $trait
                        );
                    }
                }
            }

            foreach ($structuralElement['properties'] as $property) {
                $accessModifier = null;

                if ($property['isPublic']) {
                    $accessModifier = 'public';
                } elseif ($property['isProtected']) {
                    $accessModifier = 'protected';
                } elseif ($property['isPrivate']) {
                    $accessModifier = 'private';
                } else {
                    throw new \UnexpectedValueException('Unknown access modifier returned!');
                }

                $amId = $this->storage->getAccessModifierid($accessModifier);

                $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
                    'name'                  => $property['name'],
                    'file_id'               => $fileId,
                    'start_line'            => $property['startLine'],
                    // 'is_deprecated'         => '',
                    // 'short_description'     => '',
                    // 'long_description'      => '',
                    // 'return_type'           => '',
                    // 'return_description'    => '',
                    'structural_element_id' => $seId,
                    'access_modifier_id'    => $amId,

                    'is_magic'              => 0,
                    'is_static'             => $property['isStatic'] ? 1 : 0
                ]);
            }

            foreach ($structuralElement['methods'] as $method) {
                $accessModifier = null;

                if ($method['isPublic']) {
                    $accessModifier = 'public';
                } elseif ($method['isProtected']) {
                    $accessModifier = 'protected';
                } elseif ($method['isPrivate']) {
                    $accessModifier = 'private';
                } else {
                    throw new \UnexpectedValueException('Unknown access modifier returned!');
                }

                $amId = $this->storage->getAccessModifierid($accessModifier);

                // TODO: Try to determine return type from the docblock if it is null here.

                $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, [
                    'name'                  => $method['name'],
                    'file_id'               => $fileId,
                    'start_line'            => $method['startLine'],
                    'is_builtin'            => 0,
                    // 'is_deprecated'         => '',
                    // 'short_description'     => '',
                    // 'long_description'      => '',
                    'return_type'           => $method['returnType'],
                    // 'return_description'    => '',
                    'structural_element_id' => $seId,
                    'access_modifier_id'    => $amId,

                    'is_magic'              => 0,
                    'is_static'             => $method['isStatic'] ? 1 : 0
                ]);

                foreach ($method['parameters'] as $parameter) {
                    // TODO: Try to determine type from the docblock if it is null here.

                    $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, [
                        'function_id' => $functionId,
                        'name'        => $parameter['name'],
                        'type'        => $parameter['type'] ?: null,
                        'is_optional' => $parameter['isOptional'] ? 1 : 0,
                        'is_variadic' => $parameter['isVariadic'] ? 1 : 0
                    ]);
                }

                // TODO: functions_throws, which we can fetch from the docblock.
            }

            foreach ($structuralElement['constants'] as $constant) {
                $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
                    'name'                  => $constant['name'],
                    'file_id'               => $fileId,
                    'start_line'            => $constant['startLine'],
                    'is_builtin'            => 0,
                    // 'is_deprecated'         => '',
                    // 'short_description'     => '',
                    // 'long_description'      => '',
                    // 'return_type'           => '',
                    // 'return_description'    => '',
                    'structural_element_id' => $seId
                ]);
            }
        }

        foreach ($outlineIndexingVisitor->getGlobalFunctions() as $function) {
            // TODO: Try to determine return type from the docblock if it is null here.

            $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, [
                'name'                  => $function['name'],
                'file_id'               => $fileId,
                'start_line'            => $function['startLine'],
                'is_builtin'            => 0,
                // 'is_deprecated'         => '',
                // 'short_description'     => '',
                // 'long_description'      => '',
                'return_type'           => $function['returnType'],
                // 'return_description'    => ''
            ]);

            foreach ($function['parameters'] as $parameter) {
                // TODO: Try to determine type from the docblock if it is null here.

                $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, [
                    'function_id' => $functionId,
                    'name'        => $parameter['name'],
                    'type'        => $parameter['type'] ?: null,
                    'is_optional' => $parameter['isOptional'] ? 1 : 0,
                    'is_variadic' => $parameter['isVariadic'] ? 1 : 0
                ]);
            }

            // TODO: functions_throws, which we can fetch from the docblock.
        }

        foreach ($outlineIndexingVisitor->getGlobalConstants() as $constant) {
            $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
                'name'                  => $constant['name'],
                'file_id'               => $fileId,
                'start_line'            => $constant['startLine'],
                'is_builtin'            => 0,
                // 'is_deprecated'         => '',
                // 'short_description'     => '',
                // 'long_description'      => '',
                // 'return_type'           => '',
                // 'return_description'    => ''
            ]);
        }
    }
}