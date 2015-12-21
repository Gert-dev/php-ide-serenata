<?php

namespace PhpIntegrator;

use UnexpectedValueException;

/**
 * Main application class.
 */
class Application
{
    /**
     * The version of the database we're currently at. When there are large changes to the layout of the database, this
     * number is bumped and all databases with older versions will be dumped and replaced with a new index database.
     *
     * @var int
     */
    const DATABASE_VERSION = 1;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * Handles the application process.
     *
     * @param array $arguments The arguments to pass.
     *
     * @return mixed
     */
    public function handle(array $arguments)
    {
        try {
            $command = $this->parseRequiredArguments($arguments);

            $commands = [
                '--class-list' => 'getClassList',
                '--class-info' => 'getClassInfo',
                '--functions'  => 'getGlobalFunctions',
                '--constants'  => 'getGlobalConstants',
                '--reindex'    => 'reindex'
            ];

            if (isset($commands[$command])) {
                return $this->{$commands[$command]}($arguments);
            }

            $supportedCommands = implode(', ', array_keys($commands));

            throw new UnexpectedValueException("Unknown command {$command}, supported commands: {$supportedCommands}");
        } catch (UnexpectedValueException $e) {
            return $this->outputJson(false, $e->getMessage());
        }
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getClassList(array $arguments)
    {
        $structuralElements = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('se.*', 'fi.path', '(setype.name) AS type_name', 'sepl.linked_structural_element_id')
            ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENT_TYPES, 'setype', 'setype.id = se.structural_element_type_id')
            ->leftJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED, 'sepl', 'sepl.structural_element_id = se.id')
            ->leftJoin('se', IndexStorageItemEnum::FILES, 'fi', 'fi.id = se.file_id')
            ->execute();

        $result = [];

        foreach ($structuralElements as $element) {
            $info = $this->getStructuralElementInfo($element['id']);

            $constructor = null;

            if (isset($info['methods']['__construct'])) {
                $constructor = $info['methods']['__construct'];
            }

            unset($info['constants'], $info['properties'], $info['methods']);

            if ($constructor) {
                $info['methods']['__construct'] = $constructor;
            }

            $result[$element['fqsen']] = $info;
        }

        return $this->outputJson(true, $result);
    }

    /**
     * Retrieves a list of parent FQSEN's for the specified structural element.
     *
     * @param int $seId
     *
     * @return array An associative array mapping structural element ID's to their FQSEN.
     */
    protected function getParentFqsens($seId)
    {
        $parentFqsens = [];

        while ($seId) {
            $parentSe = $this->indexDatabase->getConnection()->createQueryBuilder()
                ->select('se.id', 'se.fqsen')
                ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, 'se')
                ->innerJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED, 'sepl', 'sepl.linked_structural_element_id = se.id')
                ->where('sepl.structural_element_id = ?')
                ->setParameter(0, $seId)
                ->execute()
                ->fetch();

            if (!$parentSe) {
                break;
            }

            $seId = $parentSe['id'];
            $parentFqsens[$parentSe['id']] = $parentSe['fqsen'];
        }

        return $parentFqsens;
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getClassInfo(array $arguments)
    {
        if (empty($arguments)) {
            throw new UnexpectedValueException(
                'The fully qualified name of the structural element is required for this command.'
            );
        }

        $fqsen = array_shift($arguments);

        if ($fqsen[0] === '\\') {
            $fqsen = mb_substr($fqsen, 1);
        }

        $element = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('id')
            ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS)
            ->where('fqsen = ?')
            ->setParameter(0, $fqsen)
            ->execute()
            ->fetch();

        if (!$element) {
            throw new UnexpectedValueException('The specified structural element was not found!');
        }

        $result = $this->getStructuralElementInfo($element['id']);

        return $this->outputJson(true, $result);
    }

    /**
     * Retrieves information about the specified structural element.
     *
     * @param int $id
     *
     * @return array
     */
    protected function getStructuralElementInfo($id)
    {
        $element = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('se.*', 'fi.path', '(setype.name) AS type_name', 'sepl.linked_structural_element_id')
            ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENT_TYPES, 'setype', 'setype.id = se.structural_element_type_id')
            ->leftJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED, 'sepl', 'sepl.structural_element_id = se.id')
            ->leftJoin('se', IndexStorageItemEnum::FILES, 'fi', 'fi.id = se.file_id')
            ->where('se.id = ?')
            ->setParameter(0, $id)
            ->execute()
            ->fetch();

        if (!$element) {
            throw new UnexpectedValueException('The specified structural element was not found!');
        }

        $parentFqsens = $this->getParentFqsens($element['id']);

        $result = [
            'class'        => $element['fqsen'],
            'wasFound'     => true,
            'startLine'    => $element['start_line'],
            'name'         => $element['fqsen'],
            'shortName'    => $element['name'],
            'filename'     => $element['path'],
            'isTrait'      => ($element['type_name'] === 'trait'),
            'isClass'      => ($element['type_name'] === 'class'),
            'isInterface'  => ($element['type_name'] === 'interface'),
            'isAbstract'   => !!$element['is_abstract'],
            'parents'      => array_values($parentFqsens),
            'deprecated'   => !!$element['is_deprecated'],
            'descriptions' => [
                'short' => $element['short_description'],
                'long'  => $element['long_description']
            ],
            'constants'    => [],
            'properties'   => [],
            'methods'      => []
        ];

        // Take all members from the base class as a starting point.
        $baseClassInfo = !empty($parentFqsens) ? $this->getStructuralElementInfo(array_keys($parentFqsens)[0]) : null;

        if ($baseClassInfo) {
            $result['constants']  = $baseClassInfo['constants'];
            $result['properties'] = $baseClassInfo['properties'];
            $result['methods']    = $baseClassInfo['methods'];
        }

        $interfaces = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('se.id')
            ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENTS_INTERFACES_LINKED, 'seil', 'seil.linked_structural_element_id = se.id')
            ->where('seil.structural_element_id = ?')
            ->setParameter(0, $element['id'])
            ->execute();

        // Append members from direct interfaces to the pool of members. These only supply additional members, but will
        // never overwrite any existing members as they have a lower priority than inherited members.
        foreach ($interfaces as $interface) {
            $interface = $this->getStructuralElementInfo($interface['id']);

            foreach ($interface['constants'] as $constant) {
                if (!isset($result['constants'][$constant['name']])) {
                    $result['constants'][$constant['name']] = $constant;
                }
            }

            foreach ($interface['properties'] as $property) {
                if (!isset($result['properties'][$property['name']])) {
                    $result['properties'][$property['name']] = $property;
                }
            }

            foreach ($interface['methods'] as $method) {
                if (!isset($result['methods'][$method['name']])) {
                    $result['methods'][$method['name']] = $method;
                }
            }
        }

        $traits = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('se.id')
            ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURAL_ELEMENTS_TRAITS_LINKED, 'setl', 'setl.linked_structural_element_id = se.id')
            ->where('setl.structural_element_id = ?')
            ->setParameter(0, $element['id'])
            ->execute();

        foreach ($traits as $trait) {
            $trait = $this->getStructuralElementInfo($trait['id']);

            foreach ($trait['constants'] as $constant) {
                if (isset($result['constants'][$constant['name']])) {
                    // TODO: Inherit description from existing member if not present.
                }

                $result['constants'][$constant['name']] = array_merge($constant, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => $element['start_line'],
                        'isTrait'         => ($element['type_name'] === 'trait'),
                        'isClass'         => ($element['type_name'] === 'class'),
                        'isInterface'     => ($element['type_name'] === 'interface')
                    ]
                ]);
            }

            foreach ($trait['properties'] as $property) {
                if (isset($result['properties'][$property['name']])) {
                    // TODO: Inherit description from existing member if not present.
                }

                $result['constants'][$property['name']] = array_merge($property, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => $element['start_line'],
                        'isTrait'         => ($element['type_name'] === 'trait'),
                        'isClass'         => ($element['type_name'] === 'class'),
                        'isInterface'     => ($element['type_name'] === 'interface')
                    ]
                ]);
            }

            foreach ($trait['methods'] as $method) {
                if (isset($result['methods'][$method['name']])) {
                    // TODO: Inherit description from existing member if not present.
                }

                $result['constants'][$method['name']] = array_merge($method, [
                    'declaringClass' => [
                        'name'            => $element['fqsen'],
                        'filename'        => $element['path'],
                        'startLine'       => $element['start_line'],
                        'isTrait'         => ($element['type_name'] === 'trait'),
                        'isClass'         => ($element['type_name'] === 'class'),
                        'isInterface'     => ($element['type_name'] === 'interface')
                    ]
                ]);
            }
        }

        $constants = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::CONSTANTS)
            ->where('structural_element_id = ?')
            ->setParameter(0, $element['id'])
            ->execute();

        foreach ($constants as $constant) {
            if (isset($result['constants'][$constant['name']])) {
                // TODO: Inherit description from existing member if not present.
            }

            $result['constants'][$constant['name']] = array_merge($this->getConstantInfo($constant), [
                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface')
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface'),
                    'startLineMember' => $constant['start_line']
                ]
            ]);
        }

        $properties = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('p.*', 'am.name AS access_modifier')
            ->from(IndexStorageItemEnum::PROPERTIES, 'p')
            ->innerJoin('p', IndexStorageItemEnum::ACCESS_MODIFIERS, 'am', 'am.id = p.access_modifier_id')
            ->where('structural_element_id = ?')
            ->setParameter(0, $element['id'])
            ->execute();

        foreach ($properties as $property) {
            $overriddenProperty = null;

            if (isset($result['properties'][$property['name']])) {
                // TODO: Inherit description from existing member if not present.

                $existingProperty = $result['properties'][$property['name']];

                $overriddenProperty = [
                    'declaringClass'     => $existingProperty['declaringClass'],
                    'declaringStructure' => $existingProperty['declaringStructure'],
                    'startLine'          => $existingProperty['startLine']
                ];
            }

            $propertyInfo = $this->getPropertyInfo($property);

            if ($propertyInfo['return']['type'] === 'self') {
                $propertyInfo['return']['resolvedType'] = $element['fqsen'];
            }

            $result['properties'][$property['name']] = array_merge($propertyInfo, [
                'override'       => $overriddenProperty,
                'implementation' => null,

                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface')
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface'),
                    'startLineMember' => $property['start_line']
                ]
            ]);
        }

        $methods = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('fu.*', 'fi.path', 'am.name AS access_modifier')
            ->from(IndexStorageItemEnum::FUNCTIONS, 'fu')
            ->leftJoin('fu', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fu.file_id')
            ->innerJoin('fu', IndexStorageItemEnum::ACCESS_MODIFIERS, 'am', 'am.id = fu.access_modifier_id')
            ->where('structural_element_id = ?')
            ->setParameter(0, $element['id'])
            ->execute();

        foreach ($methods as $method) {
            $overriddenMethod = null;
            $implementedMethod = null;

            if (isset($result['methods'][$method['name']])) {
                // TODO: Inherit description from existing member if not present.

                $existingMethod = $result['methods'][$method['name']];

                if ($existingMethod['declaringStructure']['isInterface']) {
                    $implementedMethod = [
                        'declaringClass'     => $existingMethod['declaringClass'],
                        'declaringStructure' => $existingMethod['declaringStructure'],
                        'startLine'          => $existingMethod['startLine']
                    ];
                } else {
                    $overriddenMethod = [
                        'declaringClass'     => $existingMethod['declaringClass'],
                        'declaringStructure' => $existingMethod['declaringStructure'],
                        'startLine'          => $existingMethod['startLine']
                    ];
                }
            }

            $methodInfo = $this->getMethodInfo($method);

            if ($methodInfo['return']['type'] === 'self') {
                $methodInfo['return']['resolvedType'] = $element['fqsen'];
            }

            $result['methods'][$method['name']] = array_merge($methodInfo, [
                'override'       => $overriddenMethod,
                'implementation' => $implementedMethod,

                'declaringClass' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface')
                ],

                'declaringStructure' => [
                    'name'            => $element['fqsen'],
                    'filename'        => $element['path'],
                    'startLine'       => $element['start_line'],
                    'isTrait'         => ($element['type_name'] === 'trait'),
                    'isClass'         => ($element['type_name'] === 'class'),
                    'isInterface'     => ($element['type_name'] === 'interface'),
                    'startLineMember' => $method['start_line']
                ]
            ]);
        }

        // Resolve return types.
        foreach ($result['methods'] as $name => &$method) {
            if ($method['return']['type'] === '$this' || $method['return']['type'] === 'static') {
                $method['return']['resolvedType'] = $element['fqsen'];
            } elseif (!isset($method['return']['resolvedType'])) {
                $method['return']['resolvedType'] = $method['return']['type'];
            }
        }

        foreach ($result['properties'] as $name => &$property) {
            if ($property['return']['type'] === '$this' || $property['return']['type'] === 'static') {
                $property['return']['resolvedType'] = $element['fqsen'];
            } elseif (!isset($property['return']['resolvedType'])) {
                $property['return']['resolvedType'] = $property['return']['type'];
            }
        }

        return $result;
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getGlobalFunctions(array $arguments)
    {
        $indexFunctions = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('fu.*', 'fi.path')
            ->from(IndexStorageItemEnum::FUNCTIONS, 'fu')
            ->leftJoin('fu', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fu.file_id')
            ->where('structural_element_id IS NULL')
            ->execute();

        $result = [];

        foreach ($indexFunctions as $function) {
            $result[$function['name']] = $this->getFunctionInfo($function);
        }

        return $this->outputJson(true, $result);
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    protected function getMethodInfo(array $rawInfo)
    {
        return array_merge($this->getFunctionInfo($rawInfo), [
            'isMethod'           => true,
            'isMagic'            => !!$rawInfo['is_magic'],
            'isPublic'           => ($rawInfo['access_modifier'] === 'public'),
            'isProtected'        => ($rawInfo['access_modifier'] === 'protected'),
            'isPrivate'          => ($rawInfo['access_modifier'] === 'private'),
            'isStatic'           => !!$rawInfo['is_static'],

            'override'           => null,
            'implementation'     => null,

            'declaringClass'     => null,
            'declaringStructure' => null
        ]);
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    protected function getFunctionInfo(array $rawInfo)
    {
        $rawParameters = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::FUNCTIONS_PARAMETERS)
            ->where('function_id = ?')
            ->setParameter(0, $rawInfo['id'])
            ->execute();

        $optionals = [];
        $parameters = [];

        foreach ($rawParameters as $rawParameter) {
            $name = '';

            if ($rawParameter['is_reference']) {
                $name .= '&';
            }

            $name .= '$' . $rawParameter['name'];

            if ($rawParameter['is_variadic']) {
                $name .= '...';
            }

            if ($rawParameter['is_optional']) {
                $optionals[] = $name;
            } else {
                $parameters[] = $name;
            }
        }

        $throws = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::FUNCTIONS_THROWS)
            ->where('function_id = ?')
            ->setParameter(0, $rawInfo['id'])
            ->execute();

        $throwsAssoc = [];

        foreach ($throws as $throws) {
            $throwsAssoc[$throws['type']] = $throws['description'];
        }

        return [
            'name'          => $rawInfo['name'],
            'isBuiltin'     => false,
            'startLine'     => $rawInfo['start_line'],
            'filename'      => $rawInfo['path'],

            'parameters'    => $parameters,
            'optionals'     => $optionals,
            'throws'        => $throwsAssoc,
            'deprecated'    => !!$rawInfo['is_deprecated'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'         => $rawInfo['return_type'],
                'description'  => $rawInfo['return_description']
            ]
        ];
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    protected function getGlobalConstants(array $arguments)
    {
        $indexConstants = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::CONSTANTS)
            ->where('structural_element_id IS NULL')
            ->execute();

        $constants = [];

        foreach ($indexConstants as $constant) {
            $constants[$constant['name']] = $this->getConstantInfo($constant);
        }

        return $this->outputJson(true, $constants);
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    protected function getPropertyInfo(array $rawInfo)
    {
        return [
            'name'               => $rawInfo['name'],
            'isProperty'         => true,
            'startLine'          => $rawInfo['start_line'],
            'isMagic'            => !!$rawInfo['is_magic'],
            'isPublic'           => ($rawInfo['access_modifier'] === 'public'),
            'isProtected'        => ($rawInfo['access_modifier'] === 'protected'),
            'isPrivate'          => ($rawInfo['access_modifier'] === 'private'),
            'isStatic'           => !!$rawInfo['is_static'],
            'deprecated'         => !!$rawInfo['is_deprecated'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'         => $rawInfo['return_type'],
                'description'  => $rawInfo['return_description']
            ],

            'override'           => null,
            'declaringClass'     => null,
            'declaringStructure' => null
        ];
    }

    /**
     * @param array $rawInfo
     *
     * @return array
     */
    protected function getConstantInfo(array $rawInfo)
    {
        return [
            'name'        => $rawInfo['name'],
            'isBuiltin'   => !!$rawInfo['is_builtin'],
            'isPublic'    => true,
            'isProtected' => false,
            'isPrivate'   => false,
            'isStatic'    => true,
            'deprecated'  => !!$rawInfo['is_deprecated'],

            'descriptions'  => [
                'short' => $rawInfo['short_description'],
                'long'  => $rawInfo['long_description']
            ],

            'return'        => [
                'type'         => $rawInfo['return_type'],
                'description'  => $rawInfo['return_description']
            ],
        ];
    }

    /**
     * Parses required arguments for the application and fetches the command to execute.
     *
     * @param array $arguments
     *
     * @return string
     *
     * @throws UnexpectedValueException
     */
    protected function parseRequiredArguments(array &$arguments)
    {
        if (count($arguments) < 2) {
            throw new UnexpectedValueException(
                'Not enough arguments passed. Usage: Main.php <database path> <command> [<additional options>]'
            );
        }

        $databasePath = array_shift($arguments);

        $this->indexDatabase = new IndexDatabase($databasePath, static::DATABASE_VERSION);

        $command = array_shift($arguments);

        return $command;
    }

    /**
     * Reindexes the project using the specified arguments.
     *
     * @param array $arguments
     *
     * @throws UnexpectedValueException
     */
    protected function reindex(array $arguments)
    {
        if (empty($arguments)) {
            throw new UnexpectedValueException('The path to index is required for this command.');
        }

        $showOutput = false;
        $path = array_shift($arguments);

        if (!empty($arguments)) {
            if (array_shift($arguments) === '--show-output') {
                $showOutput = true;
            }
        }

        $indexer = new Indexer($this->indexDatabase, $showOutput);

        $hasIndexedBuiltin = $this->indexDatabase->getConnection()->createQueryBuilder()
            ->select('id', 'value')
            ->from(IndexStorageItemEnum::SETTINGS)
            ->where('name = ?')
            ->setParameter(0, 'has_indexed_builtin')
            ->execute()
            ->fetch();

        if (!$hasIndexedBuiltin || !$hasIndexedBuiltin['value']) {
            $indexer->indexBuiltinItems();

            if ($hasIndexedBuiltin) {
                $this->indexDatabase->update(IndexStorageItemEnum::SETTINGS, $hasIndexedBuiltin['id'], [
                    'value' => 1
                ]);
            } else {
                $this->indexDatabase->insert(IndexStorageItemEnum::SETTINGS, [
                    'name'  => 'has_indexed_builtin',
                    'value' => 1
                ]);
            }
        }

        if (is_dir($path)) {
            $indexer->indexDirectory($path);
        } else {
            $indexer->indexFile($path);
        }

        return $this->outputJson(true, null);
    }

    /**
     * Outputs JSON.
     *
     * @param bool  $success
     * @param mixed $data
     *
     * @return string
     */
    protected function outputJson($success, $data)
    {
        return json_encode([
            'success' => $success,
            'result'  => $data
        ]);
    }
}
