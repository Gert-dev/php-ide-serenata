<?php

namespace PhpIntegrator;

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
            DocParser::DESCRIPTION
        ], $class->getShortName());
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
            'descriptions' => $documentation['descriptions']
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
