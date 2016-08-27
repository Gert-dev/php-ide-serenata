<?php

namespace PhpIntegrator\Indexing;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionFunctionAbstract;

use PhpIntegrator\TypeAnalyzer;

/**
 * Handles indexation of built-in classes, global constants and global functions.
 */
class BuiltinIndexer
{
    /**
     * The storage to use for index data.
     *
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var array
     */
    protected $accessModifierMap;

    /**
     * @var array
     */
    protected $structureTypeMap;

    /**
     * @var resource|null
     */
    protected $loggingStream;

    /**
     * @var array
     */
    protected $documentationData;

    /**
     * @param StorageInterface $storage
     * @param TypeAnalyzer     $typeAnalyzer
     */
    public function __construct(StorageInterface $storage, TypeAnalyzer $typeAnalyzer)
    {
        $this->storage = $storage;
        $this->typeAnalyzer = $typeAnalyzer;
    }

    /**
     * @return resource|null
     */
    public function getLoggingStream()
    {
        return $this->loggingStream;
    }

    /**
     * @param resource|null $loggingStream
     *
     * @return static
     */
    public function setLoggingStream($loggingStream)
    {
        $this->loggingStream = $loggingStream;
        return $this;
    }

    /**
     * Logs a single message for debugging purposes.
     *
     * @param string $message
     */
    protected function logMessage($message)
    {
        if (!$this->loggingStream) {
            return;
        }

        fwrite($this->loggingStream, $message . PHP_EOL);
    }

    /**
     * Indexes built-in classes, global functions and global constants.
     */
    public function index()
    {
        $this->storage->beginTransaction();

        try {
            $this->logMessage('Indexing built-in constants...');
            $this->indexConstants();

            $this->logMessage('Indexing built-in functions...');
            $this->indexFunctions();

            $this->logMessage('Indexing built-in classes...');
            $this->indexStructures();

            $this->storage->commitTransaction();
        } catch (Exception $e) {
            $this->storage->rollbackTransaction();

            throw $e;
        }
    }

    /**
     * Indexes built-in PHP constants.
     */
    protected function indexConstants()
    {
        foreach (get_defined_constants(true) as $namespace => $constantList) {
            if ($namespace === 'user') {
                continue; // User constants are indexed in the outline.
            }

            // NOTE: Be very careful if you want to pass back the value, there are also escaped paths, newlines
            // (PHP_EOL), etc. in there.
            foreach ($constantList as $name => $value) {
                $this->indexConstant($name, $value);
            }
        }
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return int
     */
    protected function indexConstant($name, $value)
    {
        $encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        // Requires PHP >= 5.6.
        if (defined('JSON_PRESERVE_ZERO_FRACTION')) {
            $encodingOptions |= JSON_PRESERVE_ZERO_FRACTION;
        }

        return $this->storage->insert(IndexStorageItemEnum::CONSTANTS, [
            'name'               => $name,
            'fqcn'               => $this->typeAnalyzer->getNormalizedFqcn($name),
            'file_id'            => null,
            'start_line'         => null,
            'end_line'           => null,
            'default_value'      => json_encode($value, $encodingOptions),
            'is_builtin'         => 1,
            'is_deprecated'      => 0,
            'has_docblock'       => 0,
            'short_description'  => null,
            'long_description'   => null,
            'type_description'   => null,
            'types_serialized'   => serialize([])
        ]);
    }

    /**
     * Indexes built-in PHP functions.
     */
    protected function indexFunctions()
    {
        foreach (get_defined_functions() as $group => $functions) {
            foreach ($functions as $functionName) {
                $function = null;

                try {
                    $function = new ReflectionFunction($functionName);
                } catch (\Exception $e) {
                    $this->logMessage(
                        '  - WARNING: Could not examine built-in function ' . $functionName . ' with Reflection'
                    );

                    continue;
                }

                $this->indexFunction($function);
            }
        }
    }

    /**
     * @param ReflectionFunctionAbstract $function
     *
     * @return int
     */
    protected function indexFunctionLike(ReflectionFunctionAbstract $function)
    {
        $returnTypes = [];

        // Requires PHP >= 7.
        if (method_exists($function, 'getReturnType')) {
            $returnType = $function->getReturnType();

            if ($returnType) {
                $returnTypes[] = [
                    'type' => (string) $returnType,
                    'fqcn' => $this->typeAnalyzer->getNormalizedFqcn((string) $returnType)
                ];
            }
        }

        if (!mb_check_encoding($function->getName(), 'UTF-8')) {
            // See also https://github.com/Gert-dev/php-integrator-base/issues/147 .
            $this->logMessage(
                '  - WARNING: Ignoring function with non-UTF-8 name ' . $function->getName()
            );

            return;
        }

        $functionIndexData = [
            'name'                    => $function->getName(),
            'fqcn'                    => null,
            'file_id'                 => null,
            'start_line'              => null,
            'end_line'                => null,
            'is_builtin'              => 1,
            'is_deprecated'           => $function->isDeprecated() ? 1 : 0,
            'short_description'       => null,
            'long_description'        => null,
            'return_description'      => null,
            'return_type_hint'        => null,
            'structure_id'            => null,
            'access_modifier_id'      => null,
            'is_magic'                => 0,
            'is_static'               => 0,
            'has_docblock'            => 0,
            'throws_serialized'       => serialize([]),
            'parameters_serialized'   => serialize([]),
            'return_types_serialized' => serialize($returnTypes)
        ];

        $functionIndexData = array_merge(
            $functionIndexData,
            $this->getFunctionLikeDataFromDocumentation($function)
        );

        $functionId = $this->storage->insert(IndexStorageItemEnum::FUNCTIONS, $functionIndexData);

        $parameters = [];

        /** @var ReflectionParameter $parameter */
        foreach ($function->getParameters() as $parameter) {
            $isVariadic = false;

            // Requires PHP >= 5.6.
            if (method_exists($parameter, 'isVariadic')) {
                $isVariadic = $parameter->isVariadic();
            }

            $type = null;
            $types = [];

            // Requires PHP >= 7, good thing this only affects built-in functions, which don't have any type
            // hinting yet anyways (at least in PHP < 7).
            if (method_exists($parameter, 'getType')) {
                $type = $parameter->getType();

                if ($type) {
                    $types[] = [
                        'type' => (string) $type,
                        'fqcn' => $this->typeAnalyzer->getNormalizedFqcn((string) $type)
                    ];
                }
            }

            $parameterData = [
                'function_id'      => $functionId,
                'name'             => $parameter->getName(),
                'type_hint'        => null,
                'types_serialized' => serialize($types),
                'description'      => null,
                'default_value'    => null, // Fetching this is not possible due to "implementation details" (PHP docs).
                'is_nullable'      => $type && $type->allowsNull() ? 1 : 0,
                'is_reference'     => $parameter->isPassedByReference() ? 1 : 0,
                'is_optional'      => $parameter->isOptional() ? 1 : 0,
                'is_variadic'      => $isVariadic ? 1 : 0
            ];

            if (!isset($parameterData['name'])) {
                $this->logMessage(
                    '  - WARNING: Ignoring malformed function parameters for ' . $function->getName()
                );

                // Some PHP extensions somehow contain parameters that have no name. An example of this is
                // ssh2_poll (from the ssh2 extension). Strangely enough this mystery function also can't be
                // found in the documentation. (Perhaps a bug in the extension?) Ignore these.
                continue;
            }

            $parameterData = array_merge(
                $parameterData,
                $this->getFunctionLikeParameterDataFromDocumentation($parameter)
            );

            $this->storage->insert(IndexStorageItemEnum::FUNCTIONS_PARAMETERS, $parameterData);

            $parameters[] = $parameterData;
        }

        $this->storage->update(IndexStorageItemEnum::FUNCTIONS, $functionId, [
            'throws_serialized'     => serialize([]),
            'parameters_serialized' => serialize($parameters)
        ]);

        return $functionId;
    }

    /**
     * Reflection only provides limited information about functions and methods as PHP does not internally use PHP's
     * type hinting and its docblocks. The actual documentation on php.net, however, much better reflects the types
     * and descriptions. Complete the data we receive from reflection with data from the documentation.
     *
     * @param ReflectionFunctionAbstract $function
     *
     * @return array
     */
    protected function getFunctionLikeDataFromDocumentation(ReflectionFunctionAbstract $function)
    {
        $documentationName = $function->getName();

        if ($function instanceof ReflectionMethod) {
            $documentationName = $function->getDeclaringClass()->getName() . '::' . $documentationName;
        }

        $documentation = $this->getDocumentationEntry($documentationName);

        $data = [
            'short_description'  => isset($documentation['desc'])      ? $this->getNormalizedDocumentation($documentation['desc']) : null,
            'long_description'   => isset($documentation['long_desc']) ? $this->getNormalizedDocumentation($documentation['long_desc']) : null,
            'return_description' => isset($documentation['ret_desc'])  ? $this->getNormalizedDocumentation($documentation['ret_desc']) : null
        ];

        if (isset($documentation['params'][0])) {
            $extendedInfo = $documentation['params'][0];

            if (isset($extendedInfo['ret_type'])) {
                $fqcn = $extendedInfo['ret_type'];

                if (!$this->typeAnalyzer->isSpecialType($fqcn)) {
                    $fqcn = $this->typeAnalyzer->getNormalizedFqcn($fqcn);
                }

                $returnTypes = [
                    [
                        'type' => $extendedInfo['ret_type'],
                        'fqcn' => $this->typeAnalyzer->getNormalizedFqcn($fqcn)
                    ]
                ];

                $data['return_types_serialized'] = serialize($returnTypes);
            }
        }

        return $data;
    }

    /**
     * @param ReflectionParameter $parameter
     */
    protected function getFunctionLikeParameterDataFromDocumentation(ReflectionParameter $parameter)
    {
        $function = $parameter->getDeclaringFunction();

        $documentationName = $function->getName();

        if ($function instanceof ReflectionMethod) {
            $documentationName = $function->getDeclaringClass()->getName() . '::' . $documentationName;
        }

        $documentation = $this->getDocumentationEntry($documentationName);

        if (!isset($documentation['params'][0]['list'])) {
            return [];
        }

        $extendedInfo = $documentation['params'][0]['list'];

        $documentationParameterName = '$' . $parameter->name;

        // Requires PHP >= 5.6.
        if (method_exists($parameter, 'isVariadic')) {
            if ($parameter->isVariadic()) {
                $documentationParameterName = '$...';
            }
        }

        foreach ($extendedInfo as $parameterInfo) {
            if ($parameterInfo['var'] === $documentationParameterName) {
                $fqcn = $parameterInfo['type'];

                if (!$this->typeAnalyzer->isSpecialType($fqcn)) {
                    $fqcn = $this->typeAnalyzer->getNormalizedFqcn($fqcn);
                }

                $types = [
                    [
                        'type' => $parameterInfo['type'],
                        'fqcn' => $this->typeAnalyzer->getNormalizedFqcn($fqcn)
                    ]
                ];

                $data = [
                    'types_serialized' => serialize($types),
                    'description'      => $this->getNormalizedDocumentation($parameterInfo['desc'])
                ];

                return $data;
            }
        }

        return [];
    }

    /**
     * @param string $documentation
     *
     * @return string
     */
    protected function getNormalizedDocumentation($documentation)
    {
        return str_replace('\\n', "\n", $documentation);
    }

    /**
     * Retrieves the documentation data for the specified entry.
     *
     * @param string $entryName
     *
     * @return array
     */
    protected function getDocumentationEntry($entryName)
    {
        $documentationData = $this->getDocumentationData();
        $entryName = mb_strtolower($entryName);

        // Some items are simply references to other keys in the array, follow them.
        $passedList = [];

        while (true) {
            if (!isset($documentationData[$entryName])) {
                return [];
            }

            $documentation = $documentationData[$entryName];

            if (!is_string($documentation)) {
                break;
            }

            $entryName = $documentation;

            // Avoid circular references that would result in an infinite loop (i.e. session_set_save_handler).
            if (isset($passedList[$entryName])) {
                return [];
            }

            $passedList[$entryName] = true;
        }

        return $documentation;
    }

    /**
     * Indexes built-in PHP classes, interfaces and traits.
     */
    protected function indexStructures()
    {
        foreach (get_declared_traits() as $trait) {
            $element = new ReflectionClass($trait);

            if ($element->isInternal()) {
                $this->indexStructure($element);
            }
        }

        foreach (get_declared_interfaces() as $interface) {
            $element = new ReflectionClass($interface);

            if ($element->isInternal()) {
                $this->indexStructure($element);
            }
        }

        foreach (get_declared_classes() as $class) {
            $element = new ReflectionClass($class);

            if ($element->isInternal()) {
                $this->indexStructure($element);
            }
        }
    }

    /**
     * Indexes the specified built-in class, interface or trait.
     *
     * @param ReflectionClass $element
     */
    protected function indexStructure(ReflectionClass $element)
    {
        $type = null;
        $parents = [];
        $interfaces = [];
        $traits = $element->getTraitNames();

        if ($element->isTrait()) {
            $type = 'trait';
            $interfaces = [];
            $parents = [];
        } elseif ($element->isInterface()) {
            $type = 'interface';
            $interfaces = [];

            // 'getParentClass' only returns one extended interface. If an interface extends multiple interfaces, the
            // other ones instead show up in 'getInterfaceNames'.
            $parents = $element->getInterfaceNames();
        } else {
            $type = 'class';
            $interfaces = $element->getInterfaceNames();
            $parents = $element->getParentClass() ? [$element->getParentClass()->getName()] : [];
        }

        $structureTypeMap = $this->getStructureTypeMap();

        $structureId = $this->storage->insertStructure([
            'name'              => $this->getStructureShortName($element),
            'fqcn'              => $this->getStructureFqcn($element),
            'file_id'           => null,
            'start_line'        => null,
            'end_line'          => null,
            'structure_type_id' => $structureTypeMap[$type],
            'short_description' => null,
            'long_description'  => null,
            'is_builtin'        => 1,
            'is_final'          => $element->isFinal() ? 1 : 0,
            'is_abstract'       => $element->isAbstract() ? 1 : 0,
            'is_annotation'     => 0,
            'is_deprecated'     => 0,
            'has_docblock'      => 0
        ]);

        foreach ($parents as $parent) {
            $this->storage->insert(IndexStorageItemEnum::STRUCTURES_PARENTS_LINKED, [
                'structure_id'          => $structureId,
                'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($parent)
            ]);
        }

        foreach ($interfaces as $interface) {
            $this->storage->insert(IndexStorageItemEnum::STRUCTURES_INTERFACES_LINKED, [
                'structure_id'          => $structureId,
                'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($interface)
            ]);
        }

        foreach ($traits as $trait) {
            $this->storage->insert(IndexStorageItemEnum::STRUCTURES_TRAITS_LINKED, [
                'structure_id'          => $structureId,
                'linked_structure_fqcn' => $this->typeAnalyzer->getNormalizedFqcn($trait)
            ]);
        }

        foreach ($element->getMethods() as $method) {
            $this->indexMethod($method, $structureId);
        }

        foreach ($element->getProperties() as $property) {
            $this->indexProperty($property, $structureId);
        }

        foreach ($element->getConstants() as $constantName => $constantValue) {
            $this->indexClassConstant($constantName, $constantValue, $structureId);
        }
    }

    /**
     * @param ReflectionFunction $function
     */
    protected function indexFunction(ReflectionFunction $function)
    {
        $functionId = $this->indexFunctionLike($function);

        $this->storage->update(IndexStorageItemEnum::FUNCTIONS, $functionId, [
            'fqcn' => $this->typeAnalyzer->getNormalizedFqcn($function->getName())
        ]);
    }

    /**
     * @param ReflectionMethod $method
     * @param int              $structureId
     */
    protected function indexMethod(ReflectionMethod $method, $structureId)
    {
        $functionId = $this->indexFunctionLike($method);

        $accessModifierName = null;

        if ($method->isPublic()) {
            $accessModifierName = 'public';
        } elseif ($method->isProtected()) {
            $accessModifierName = 'protected';
        } else/*if ($method->isPrivate())*/ {
            $accessModifierName = 'private';
        }

        $accessModifierMap = $this->getAccessModifierMap();

        $this->storage->update(IndexStorageItemEnum::FUNCTIONS, $functionId, [
            'structure_id'       => $structureId,
            'access_modifier_id' => $accessModifierMap[$accessModifierName],
            'is_magic'           => 0,
            'is_static'          => $method->isStatic(),
            'is_abstract'        => $method->isAbstract() ? 1 : 0,
            'is_final'           => $method->isFinal() ? 1 : 0
        ]);
    }

    /**
     * @param ReflectionProperty $property
     * @param int                $structureId
     */
    protected function indexProperty(ReflectionProperty $property, $structureId)
    {
        $accessModifierName = null;

        if ($property->isPublic()) {
            $accessModifierName = 'public';
        } elseif ($property->isProtected()) {
            $accessModifierName = 'protected';
        } else/*if ($property->isPrivate())*/ {
            $accessModifierName = 'private';
        }

        $accessModifierMap = $this->getAccessModifierMap();

        $defaultProperties = $property->getDeclaringClass()->getDefaultProperties();

        $name = $property->getName();

        $defaultValue = isset($defaultProperties[$name]) ? $defaultProperties[$name] : null;

        if ($defaultValue === '') {
            $defaultValue = "''";
        }

        $this->storage->insert(IndexStorageItemEnum::PROPERTIES, [
            'name'               => $name,
            'file_id'            => null,
            'start_line'         => null,
            'end_line'           => null,
            'default_value'      => $defaultValue,
            'is_deprecated'      => 0,
            'is_magic'           => 0,
            'is_static'          => $property->isStatic(),
            'has_docblock'       => 0,
            'short_description'  => null,
            'long_description'   => null,
            'type_description'   => null,
            'structure_id'       => $structureId,
            'access_modifier_id' => $accessModifierMap[$accessModifierName],
            'types_serialized'   => serialize([])
        ]);
    }

    /**
     * @param string $name
     * @param mixed  $value
     * @param int    $structureId
     */
    protected function indexClassConstant($name, $value, $structureId)
    {
        $constantId = $this->indexConstant($name, $value);

        $this->storage->update(IndexStorageItemEnum::CONSTANTS, $constantId, [
            'structure_id' => $structureId
        ]);
    }

    /**
     * Retrieves the short name of the specified classlike.
     *
     * Some of PHP's built-in classes have inconsistent naming. An example is the COM class, which is called 'COM'
     * according to the documentation but is actually called 'com' when fetching a list of built-in classes. PHP in
     * itself is mostly case insenstive, but as we aren't, we must at least ensure that the users get the expected
     * results when consulting the documentation.
     *
     * @param ReflectionClass $element
     *
     * @return string
     */
    protected function getStructureShortName(ReflectionClass $element)
    {
        $correctionMap = [
            'com'     => 'COM',
            'dotnet'  => 'DOTNET',
            'variant' => 'VARIANT'
        ];

        $shortName = $element->getShortName();

        return isset($correctionMap[$shortName]) ? $correctionMap[$shortName] : $shortName;
    }

    /**
     * Retrieves the FQCN of the specified classlike.
     *
     * See {@see getStructureShortName} for more information on why this is necessary.
     *
     * @param ReflectionClass $element
     *
     * @return string
     */
    protected function getStructureFqcn(ReflectionClass $element)
    {
        $correctionMap = [
            'com'     => 'COM',
            'dotnet'  => 'DOTNET',
            'variant' => 'VARIANT'
        ];

        $shortName = $element->getShortName();

        $correctedName = isset($correctionMap[$shortName]) ? $correctionMap[$shortName] : $shortName;

        return $this->typeAnalyzer->getNormalizedFqcn($correctedName);
    }

    /**
     * @return array
     */
    protected function getDocumentationData()
    {
        if (!$this->documentationData) {
            $this->documentationData = json_decode(file_get_contents(__DIR__ . '/Resource/documentation-data.json'), true);
        }

        return $this->documentationData;
    }

    /**
     * @return array
     */
    protected function getAccessModifierMap()
    {
        if (!$this->accessModifierMap) {
            $this->accessModifierMap = $this->storage->getAccessModifierMap();
        }

        return $this->accessModifierMap;
    }

    /**
     * @return array
     */
    protected function getStructureTypeMap()
    {
        if (!$this->structureTypeMap) {
            $this->structureTypeMap = $this->storage->getStructureTypeMap();
        }

        return $this->structureTypeMap;
    }
}
