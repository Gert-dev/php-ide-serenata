<?php

namespace PhpIntegrator;

use Reflection;
use ReflectionClass;

/**
 * Fetches information about classes, interfaces, traits, ...
 */
class ClassInfoFetcher implements InfoFetcherInterface
{
    use FetcherInfoTrait;

    /**
     * @var InfoFetcherInterface
     */
    protected $methodFetcher;

    /**
     * @var InfoFetcherInterface
     */
    protected $propertyFetcher;

    /**
     * @var InfoFetcherInterface
     */
    protected $constantFetcher;

    /**
     * Constructor.
     *
     * @param InfoFetcherInterface $propertyFetcher
     * @param InfoFetcherInterface $methodFetcher
     * @param InfoFetcherInterface $constantFetcher
     */
    public function __construct(
        InfoFetcherInterface $propertyFetcher,
        InfoFetcherInterface $methodFetcher,
        InfoFetcherInterface $constantFetcher
    ) {
        $this->methodFetcher = $methodFetcher;
        $this->propertyFetcher = $propertyFetcher;
        $this->constantFetcher = $constantFetcher;
    }

    /**
     * Retrieves a list of parent classes of the specified class, ordered from the closest to the furthest ancestor.
     *
     * @param ReflectionClass $class
     *
     * @return string[]
     */
    protected function getParentClasses(ReflectionClass $class)
    {
        $parents = [];

        $parentClass = $class;

        while ($parentClass = $parentClass->getParentClass()) {
            $parents[] = $parentClass->getName();
        }

        return $parents;
    }

    /**
     * Fetches documentation about the specified class, trait, interface, ...
     *
     * @param ReflectionClass $class The class to analyze.
     *
     * @return array
     */
    protected function getDocumentation(ReflectionClass $class)
    {
        $parser = new DocParser();
        $docComment = $class->getDocComment() ?: '';

        return $parser->parse($docComment, [
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION,
            DocParser::METHOD,
            DocParser::PROPERTY,
            DocParser::PROPERTY_READ,
            DocParser::PROPERTY_WRITE
        ], $class->getShortName());
    }

    /**
     * {@inheritDoc}
     */
    public function createDefaultInfo(array $options)
    {
        throw new \LogicException("Not implemented yet!");
    }

    /**
     * Retrieves information about magic members (properties and methods).
     *
     * @param ReflectionClass $class
     *
     * @return array
     */
    protected function getMagicMembers(ReflectionClass $class)
    {
        $data = [];

        $parseProperty = function (ReflectionClass $class, $key, array $list) use (&$data) {
            if (!isset($data[$key])) {
                $data[$key] = [];
            }

            $declaringClass = [
                'name'            => $class->getName(),
                'filename'        => $class->getFileName(),
                'startLine'       => $class->getStartLine(),
                'startLineMember' => $class->getStartLine()
            ];

            foreach ($list as $magicPropertyName => $magicPropertyData) {
                if ($magicPropertyName[0] == '$') {
                    $actualName = mb_substr($magicPropertyName, 1);
                } else {
                    $actualName = $magicPropertyName;
                }

                $data[$key][$actualName] = $this->propertyFetcher->createDefaultInfo([
                    'isMagic'            => true,
                    'isStatic'           => $magicPropertyData['isStatic'],
                    'name'               => $actualName,
                    'descriptions'       => ['short' => $magicPropertyData['description']],
                    'return'             => ['type'  => $magicPropertyData['type']],
                    'declaringClass'     => $declaringClass,
                    'declaringStructure' => $declaringClass
                ]);
            }
        };

        $parseMethods = function (ReflectionClass $class, $key, array $list) use (&$data) {
            if (!isset($data[$key])) {
                $data[$key] = [];
            }

            $declaringClass = [
                'name'            => $class->getName(),
                'filename'        => $class->getFileName(),
                'startLine'       => $class->getStartLine(),
                'startLineMember' => $class->getStartLine()
            ];

            foreach ($list as $magicMethodName => $magicMethodData) {
                $data[$key][$magicMethodName] = $this->methodFetcher->createDefaultInfo([
                    'isMagic'            => true,
                    'isStatic'           => $magicMethodData['isStatic'],
                    'name'               => $magicMethodName,
                    'descriptions'       => ['short' => $magicMethodData['description']],
                    'return'             => ['type'  => $magicMethodData['type']],
                    'declaringClass'     => $declaringClass,
                    'declaringStructure' => $declaringClass,
                    'parameters'         => array_keys($magicMethodData['requiredParameters']),
                    'optionals'          => array_keys($magicMethodData['optionalParameters'])
                ]);
            }
        };

        foreach ($class->getInterfaces() as $interface) {
            $documentation = $this->getDocumentation($interface);

            $parseMethods($interface, 'methods', $documentation['methods']);
            $parseProperty($interface, 'properties', $documentation['properties']);
            $parseProperty($interface, 'propertiesReadOnly', $documentation['propertiesReadOnly']);
            $parseProperty($interface, 'propertiesWriteOnly', $documentation['propertiesWriteOnly']);
        }

        do {
            $documentation = $this->getDocumentation($class);

            $parseMethods($class, 'methods', $documentation['methods']);
            $parseProperty($class, 'properties', $documentation['properties']);
            $parseProperty($class, 'propertiesReadOnly', $documentation['propertiesReadOnly']);
            $parseProperty($class, 'propertiesWriteOnly', $documentation['propertiesWriteOnly']);

            $class = $class->getParentClass();
        } while ($class);

        return $data;
    }

    /**
     * Returns information about the specified class.
     *
     * @param ReflectionClass $class
     *
     * @return array
     */
    public function getInfo($class)
    {
        if (!$class instanceof ReflectionClass) {
            throw new \InvalidArgumentException("The passed argument is not of the correct type!");
        }

        $magicMembers = $this->getMagicMembers($class);
        $documentation = $this->getDocumentation($class);

        $data = [
            'class'        => $class->getName(),
            'wasFound'     => true,
            'startLine'    => $class->getStartLine(),
            'name'         => $class->getName(),
            'shortName'    => $class->getShortName(),
            'filename'     => $class->getFileName(),
            'isTrait'      => $class->isTrait(),
            'isClass'      => !($class->isTrait() || $class->isInterface()),
            'isAbstract'   => $class->isAbstract(),
            'isInterface'  => $class->isInterface(),
            'parents'      => $this->getParentClasses($class),
            'deprecated'   => $documentation['deprecated'],
            'descriptions' => $documentation['descriptions'],
            'properties'   => array_merge(
                $magicMembers['properties'],
                $magicMembers['propertiesReadOnly'],
                $magicMembers['propertiesWriteOnly']
            ),
            'methods'      => $magicMembers['methods'],
            'constants'    => []
        ];

        foreach ($class->getMethods() as $method) {
            $data['methods'][$method->getName()] = $this->methodFetcher->getInfo($method);
        }

        foreach ($class->getProperties() as $property) {
            $data['properties'][$property->getName()] = $this->propertyFetcher->getInfo($property);
        }

        foreach ($class->getConstants() as $constant => $value) {
            $data['constants'][$constant] = $this->constantFetcher->getInfo($constant, $class);
        }

        return $data;
    }
}
