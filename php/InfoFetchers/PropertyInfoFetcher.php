<?php

namespace PhpIntegrator;

use ReflectionClass;
use ReflectionProperty;

/**
 * Fetches information about class properties.
 */
class PropertyInfoFetcher implements InfoFetcherInterface
{
    use FetcherInfoTrait,
        MemberInfoFetcherTrait;

    /**
     * Fetches documentation about the specified class property, such as its type, description, ...
     *
     * @param ReflectionProperty $property The property to analyze.
     *
     * @return array
     */
    protected function getDocumentation(ReflectionProperty $property)
    {
       $parser = new DocParser();
       $docComment = $property->getDocComment() ?: '';

       $docParseResult = $parser->parse($docComment, [
           DocParser::VAR_TYPE,
           DocParser::DEPRECATED,
           DocParser::DESCRIPTION
       ], $property->name);

       if (!$docComment) {
           $classIterator = new ReflectionClass($property->class);
           $classIterator = $classIterator->getParentClass();

           // Walk up base classes to see if any of them have additional info about this property.
           while ($classIterator) {
               if ($classIterator->hasProperty($property->getName())) {
                   $baseClassProperty = $classIterator->getProperty($property->getName());

                   if ($baseClassProperty->getDocComment()) {
                       $baseClassPropertyArgs = $this->getDocumentation($baseClassProperty);

                       return $baseClassPropertyArgs; // Fall back to parent docblock.
                   }
               }

               $classIterator = $classIterator->getParentClass();
           }
       }

       return $docParseResult;
    }

    /**
     * {@inheritDoc}
     */
    public function createDefaultInfo(array $options)
    {
       $data = array_merge([
           'name'               => null,
           'isMethod'           => false,
           'isProperty'         => true,
           'isMagic'            => false,
           'isPublic'           => true,
           'isProtected'        => false,
           'isPrivate'          => false,
           'isStatic'           => false,
           'override'           => false,
           'declaringClass'     => null,
           'declaringStructure' => null,

           'deprecated'         => false,
           'descriptions'       => [
               'short' => null,
               'long'  => null
           ],
           'return'             => [
               'type'        => null,
               'description' => null
           ]
       ], $options);

        $data['return']['resolvedType'] = $this->determineFullReturnType($data);

        return $data;
    }

    /**
     * Retrieves a data structure containing information about the specified property.
     *
     * @param ReflectionProperty $property
     *
     * @return array
     */
    public function getInfo($property)
    {
        if (!$property instanceof ReflectionProperty) {
            throw new \InvalidArgumentException("The passed argument is not of the correct type!");
        }

        $documentation = $this->getDocumentation($property);

        $data = [
            'name'               => $property->getName(),
            'isMethod'           => false,
            'isProperty'         => true,
            'isMagic'            => false,
            'isPublic'           => $property->isPublic(),
            'isProtected'        => $property->isProtected(),
            'isPrivate'          => $property->isPrivate(),
            'isStatic'           => $property->isStatic(),
            'override'           => $this->getOverrideInfo($property),
            'declaringClass'     => $this->getDeclaringClass($property),
            'declaringStructure' => $this->getDeclaringStructure($property),

            'deprecated'         => $documentation['deprecated'],
            'descriptions'       => $documentation['descriptions'],
            'return'             => $documentation['var']
        ];

        // You can place documentation after the @var tag as well as at the start of the docblock. Fall back from the
        // latter to the former.
        if (empty($data['descriptions']['short'])) {
            $data['descriptions']['short'] = $documentation['var']['description'];
        }

        $data['return']['resolvedType'] = $this->determineFullReturnType($data);

        return $data;
    }
}
