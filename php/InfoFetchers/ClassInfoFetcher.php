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

        $fillClosure = function (ReflectionClass $class, $key, array $list) use (&$data) {
            if (!isset($data[$key])) {
                $data[$key] = [];
            }

            $declaringClass = [
                'name'      => $class->getName(),
                'filename'  => $class->getFilename(),
                'startLine' => $class->getStartLine()
            ];

            foreach ($list as $magicPropertyName => $magicPropertyData) {
                if ($magicPropertyName[0] == '$') {
                    $actualName = mb_substr($magicPropertyName, 1);
                } else {
                    $actualName = $magicPropertyName;
                }

                $data[$key][$actualName] = $this->propertyFetcher->createDefaultInfo([
                    'isMagic'            => true,
                    'name'               => $actualName,
                    'descriptions'       => ['short' => $magicPropertyData['description']],
                    'return'             => ['type'  => $magicPropertyData['type']],
                    'declaringClass'     => $declaringClass,
                    'declaringStructure' => $declaringClass
                ]);
            }
        };

        foreach ($class->getInterfaces() as $interface) {
            $documentation = $this->getDocumentation($interface);

            $fillClosure($interface, 'properties', $documentation['properties']);
            $fillClosure($interface, 'propertiesReadOnly', $documentation['propertiesReadOnly']);
            $fillClosure($interface, 'propertiesWriteOnly', $documentation['propertiesWriteOnly']);
        }

        do {
            $documentation = $this->getDocumentation($class);

            $fillClosure($class, 'properties', $documentation['properties']);
            $fillClosure($class, 'propertiesReadOnly', $documentation['propertiesReadOnly']);
            $fillClosure($class, 'propertiesWriteOnly', $documentation['propertiesWriteOnly']);

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

        $documentation = $this->getDocumentation($class);

        $data = [
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
            'properties'   => [],
            'methods'      => [],
            'constants'    => []
        ];

        $magicMembers = $this->getMagicMembers($class);

        $data['properties'] = array_merge(
            $magicMembers['properties'],
            $magicMembers['propertiesReadOnly'],
            $magicMembers['propertiesWriteOnly']
        );

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
