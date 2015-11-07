<?php

namespace PhpIntegrator;

use Reflector;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionFunctionAbstract;

abstract class Tools
{
    /**
     * Fetches information about the specified class, trait, interface, ...
     *
     * @param ReflectionClass $class The class to analyze.
     *
     * @return array
     */
   protected function getClassArguments(ReflectionClass $class)
   {
       $parser = new DocParser();
       $docComment = $class->getDocComment() ?: '';

       $docParseResult = $parser->parse($docComment, [
           DocParser::DEPRECATED,
           DocParser::DESCRIPTION
       ], $class->getShortName());

       return [
          'descriptions' => $docParseResult['descriptions'],
          'deprecated'   => $docParseResult['deprecated']
      ];
   }

    /**
     * Fetches information about the specified method or function, such as its parameters, a description from the
     * docblock (if available), the return type, ...
     *
     * @param ReflectionFunctionAbstract $function The function or method to analyze.
     *
     * @return array
     */
    protected function getFunctionArguments(ReflectionFunctionAbstract $function)
    {
        $args = $function->getParameters();

        $optionals = [];
        $parameters = [];

        foreach ($args as $argument) {
            $value = '$' . $argument->getName();

            if ($argument->isPassedByReference()) {
                $value = '&' . $value;
            }

            if ($argument->isOptional()) {
                $optionals[] = $value;
            } else {
                $parameters[] = $value;
            }
        }

        // For variadic methods, append three dots to the last argument (if any) to indicate this to the user. This
        // requires PHP >= 5.6.
        if (!empty($args) && method_exists($function, 'isVariadic') && $function->isVariadic()) {
            $lastArgument = $args[count($args) - 1];

            if ($lastArgument->isOptional()) {
                $optionals[count($optionals) - 1] .= '...';
            } else {
                $parameters[count($parameters) - 1] .= '...';
            }
        }

        $parser = new DocParser();
        $docComment = $function->getDocComment();

        $docParseResult = $parser->parse($docComment, [
            DocParser::THROWS,
            DocParser::PARAM_TYPE,
            DocParser::DEPRECATED,
            DocParser::DESCRIPTION,
            DocParser::RETURN_VALUE
        ], $function->name);

        $docblockInheritsLongDescription = false;

        // Ticket #86 - Add support for inheriting the entire docblock from the parent if the current docblock contains
        // nothing but these tags. Note that, according to draft PSR-5 and phpDocumentor's implementation, this is
        // incorrect. However, some large frameworks (such as Symfony) use this and it thus makes life easier for many
        // developers, hence this workaround.
        if (in_array($docParseResult['descriptions']['short'], ['{@inheritdoc}', '{@inheritDoc}'])) {
            $docComment = false; // Pretend there is no docblock.
        }

        if (strpos($docParseResult['descriptions']['long'], DocParser::INHERITDOC) !== false) {
            // The parent docblock is embedded, which we'll need to parse. Note that according to phpDocumentor this
            // only works for the long description (not the so-called 'summary' or short description).
            $docblockInheritsLongDescription = true;
        }

        // No immediate docblock available or we need to scan the parent docblock?
        if ((!$docComment || $docblockInheritsLongDescription) && $function instanceof ReflectionMethod) {
            $classIterator = new ReflectionClass($function->class);
            $classIterator = $classIterator->getParentClass();

            // Check if this method is implementing an abstract method from a trait, in which case that docblock should
            // be used.
            if (!$docComment) {
                foreach ($function->getDeclaringClass()->getTraits() as $trait) {
                    if ($trait->hasMethod($function->getName())) {
                        $traitMethod = $trait->getMethod($function->getName());

                        if ($traitMethod->isAbstract() && $traitMethod->getDocComment()) {
                            return $this->getFunctionArguments($traitMethod);
                        }
                    }
                }
            }

            // Check if this method is implementing an interface method, in which case that docblock should be used.
            // NOTE: If the parent class has an interface, getMethods() on the parent class will include the interface
            // methods, along with their docblocks, even if the parent doesn't actually implement the method. So we only
            // have to check the interfaces of the declaring class.
            if (!$docComment) {
                foreach ($function->getDeclaringClass()->getInterfaces() as $interface) {
                    if ($interface->hasMethod($function->getName())) {
                        $interfaceMethod = $interface->getMethod($function->getName());

                        if ($interfaceMethod->getDocComment()) {
                            return $this->getFunctionArguments($interfaceMethod);
                        }
                    }
                }
            }

            // Walk up base classes to see if any of them have additional info about this method.
            while ($classIterator) {
                if ($classIterator->hasMethod($function->getName())) {
                    $baseClassMethod = $classIterator->getMethod($function->getName());

                    if ($baseClassMethod->getDocComment()) {
                        $baseClassMethodArgs = $this->getFunctionArguments($baseClassMethod);

                        if (!$docComment) {
                            return $baseClassMethodArgs; // Fall back to parent docblock.
                        } elseif ($docblockInheritsLongDescription) {
                            $docParseResult['descriptions']['long'] = str_replace(
                                DocParser::INHERITDOC,
                                $baseClassMethodArgs['descriptions']['long'],
                                $docParseResult['descriptions']['long']
                            );
                        }

                        break;
                    }
                }

                $classIterator = $classIterator->getParentClass();
            }
        }

        return [
            'parameters'    => $parameters,
            'optionals'     => $optionals,
            'docParameters' => $docParseResult['params'],
            'throws'        => $docParseResult['throws'],
            'return'        => $docParseResult['return'],
            'descriptions'  => $docParseResult['descriptions'],
            'deprecated'    => $function->isDeprecated() || $docParseResult['deprecated']
        ];
    }

     /**
      * Fetches information about the specified class property, such as its type, description, ...
      *
      * @param ReflectionProperty $property The property to analyze.
      *
      * @return array
      */
    protected function getPropertyArguments(ReflectionProperty $property)
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
                        $baseClassPropertyArgs = $this->getPropertyArguments($baseClassProperty);

                        return $baseClassPropertyArgs; // Fall back to parent docblock.
                    }
                }

                $classIterator = $classIterator->getParentClass();
            }
        }

        return [
           'return'       => $docParseResult['var'],
           'descriptions' => $docParseResult['descriptions'],
           'deprecated'   => $docParseResult['deprecated']
       ];
    }

    /**
     * Retrieves the class that contains the specified reflection member.
     *
     * @param ReflectionFunctionAbstract|ReflectionProperty $reflectionMember
     *
     * @return array
     */
    protected function getDeclaringClassForMember(Reflector $reflectionMember)
    {
        // This will point to the class that contains the member, which will resolve to the parent class if it's
        // inherited (and not overridden).
        $declaringClass = $reflectionMember->getDeclaringClass();

        return [
            'name'      => $declaringClass->name,
            'filename'  => $declaringClass->getFilename(),
            'startLine' => $declaringClass->getStartLine()
        ];
    }

    /**
     * Retrieves the structure (class, trait, interface, ...) that contains the specified reflection member.
     *
     * @param ReflectionFunctionAbstract|ReflectionProperty $reflectionMember
     *
     * @return array
     */
    protected function getDeclaringStructureForMember(Reflector $reflectionMember)
    {
        // This will point to the class that contains the member, which will resolve to the parent class if it's
        // inherited (and not overridden).
        $declaringStructure = $reflectionMember->getDeclaringClass();
        $isMethod = ($reflectionMember instanceof ReflectionFunctionAbstract);

        // Members from traits are seen as part of the structure using the trait, but we still want the actual trait
        // name.
        foreach ($declaringStructure->getTraits() as $trait) {
            if (($isMethod && $trait->hasMethod($reflectionMember->name)) ||
                (!$isMethod && $trait->hasProperty($reflectionMember->name))) {
                $declaringStructure = $trait;
                break;
            }
        }

        return [
            'name'      => $declaringStructure->name,
            'filename'  => $declaringStructure->getFilename(),
            'startLine' => $declaringStructure->getStartLine()
        ];
    }

    /**
     * Retrieves information about what the specified member is overriding, if anything.
     *
     * @param ReflectionFunctionAbstract|ReflectionProperty $reflectionMember
     *
     * @return array|null
     */
    protected function getOverrideInfo(Reflector $reflectionMember)
    {
        $overriddenMember = null;
        $memberName = $reflectionMember->getName();

        $baseClass = $reflectionMember->getDeclaringClass();

        $type = ($reflectionMember instanceof ReflectionProperty) ? 'Property' : 'Method';

        while ($baseClass = $baseClass->getParentClass()) {
            if ($baseClass->{'has' . $type}($memberName)) {
                $overriddenMember = $baseClass->{'get' . $type}($memberName);
                break;
            }
        }

        if (!$overriddenMember) {
            // This method is not an override of a parent method, see if it is an 'override' of an abstract method from
            // a trait the class it is in is using.
            if ($reflectionMember instanceof ReflectionFunctionAbstract) {
                foreach ($reflectionMember->getDeclaringClass()->getTraits() as $trait) {
                    if ($trait->hasMethod($memberName)) {
                        $traitMethod = $trait->getMethod($memberName);

                        if ($traitMethod->isAbstract()) {
                            $overriddenMember = $traitMethod;
                        }
                    }
                }
            }

            if (!$overriddenMember) {
                return null;
            }
        }

        $startLine = null;

        if ($overriddenMember instanceof ReflectionFunctionAbstract) {
            $startLine = $overriddenMember->getStartLine();
        }

        return [
            'declaringClass'     => $this->getDeclaringClassForMember($overriddenMember),
            'declaringStructure' => $this->getDeclaringStructureForMember($overriddenMember),
            'startLine'          => $startLine
        ];
    }

    /**
     * Retrieves information about what interface the specified member method is implementind, if any.
     *
     * @param ReflectionFunctionAbstract $reflectionMember
     *
     * @return array|null
     */
    protected function getImplementationInfo(ReflectionFunctionAbstract $reflectionMember)
    {
        $implementedMember = null;
        $methodName = $reflectionMember->getName();

        foreach ($reflectionMember->getDeclaringClass()->getInterfaces() as $interface) {
            if ($interface->hasMethod($methodName)) {
                $implementedMember = $interface->getMethod($methodName);
                break;
            }
        }

        if (!$implementedMember) {
            return null;
        }

        return [
            'declaringClass'     => $this->getDeclaringClassForMember($implementedMember),
            'declaringStructure' => $this->getDeclaringStructureForMember($implementedMember),
            'startLine'          => $implementedMember->getStartLine()
        ];
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
     * Retrieves a data structure containing information about the specified function (or method).
     *
     * @param ReflectionFunctionAbstract $function
     *
     * @return array
     */
    protected function getFunctionInfo(ReflectionFunctionAbstract $function)
    {
        return [
            'name'               => $function->getName(),
            'isMethod'           => true,
            'isProperty'         => false,
            'isBuiltin'          => ($function->getFileName() === false),

            'args'               => $this->getFunctionArguments($function),
            'startLine'          => $function->getStartLine(),
            'filename'           => $function->getFileName()
        ];
    }

    /**
     * Retrieves a data structure containing information about the specified method, expanding upon
     * {@see getFunctionInfo} to provide additional information.
     *
     * @param ReflectionMethod $method
     *
     * @return array
     */
    protected function getMethodInfo(ReflectionMethod $method)
    {
        return array_merge($this->getFunctionInfo($method), [
            'override'           => $this->getOverrideInfo($method),
            'implementation'     => $this->getImplementationInfo($method),

            'isPublic'           => $method->isPublic(),
            'isProtected'        => $method->isProtected(),
            'isPrivate'          => $method->isPrivate(),
            'isStatic'           => $method->isStatic(),

            'declaringClass'     => $this->getDeclaringClassForMember($method),
            'declaringStructure' => $this->getDeclaringStructureForMember($method)
        ]);
    }

    /**
     * Retrieves a data structure containing information about the specified property.
     *
     * @param ReflectionProperty $property
     *
     * @return array
     */
    protected function getPropertyInfo(ReflectionProperty $property)
    {
        return [
            'name'               => $property->getName(),
            'isMethod'           => false,
            'isProperty'         => true,
            'isPublic'           => $property->isPublic(),
            'isProtected'        => $property->isProtected(),
            'isPrivate'          => $property->isPrivate(),
            'isStatic'           => $property->isStatic(),

            'override'           => $this->getOverrideInfo($property),

            'args'               => $this->getPropertyArguments($property),
            'declaringClass'     => $this->getDeclaringClassForMember($property),
            'declaringStructure' => $this->getDeclaringStructureForMember($property)
        ];
    }

    /**
     * Retrieves a data structure containing information about the specified constant.
     *
     * @param string               $name
     * @param ReflectionClass|null $class
     *
     * @return array
     */
    protected function getConstantInfo($name, ReflectionClass $class = null)
    {
        // TODO: There is no direct way to know where the constant originated from (the current class, a base class,
        // an interface of a base class, a trait, ...). This could be done by looping up the chain of base classes
        // to the last class that also has the same property and then checking if any of that class' traits or
        // interfaces define the constant.
        return [
            'name'           => $name,
            'isMethod'       => false,
            'isProperty'     => false,
            'isPublic'       => true,
            'isProtected'    => false,
            'isPrivate'      => false,
            'isStatic'       => true,
            'declaringClass' => [
                'name'     => $class ? $class->name : null,
                'filename' => $class ? $class->getFileName() : null
            ],
            'declaringStructure' => [
                'name'     => $class ? $class->name : null,
                'filename' => $class ? $class->getFileName() : null
            ],

            // TODO: It is not possible to directly fetch the docblock of the constant through reflection, manual
            // file parsing is required.
            'args'           => [
                'return'       => null,
                'descriptions' => [],
                'deprecated'   => false
            ]
        ];
    }

    /**
     * Returns information about the specified class.
     *
     * @param string $className Full namespace of the parsed class.
     *
     * @return array
     */
    protected function getClassInfo($className)
    {
        $data = [
            'class'       => $className,
            'wasFound'    => false,
            'name'        => null,
            'startLine'   => null,
            'shortName'   => null,
            'filename'    => null,
            'isTrait'     => null,
            'isClass'     => null,
            'isAbstract'  => null,
            'isInterface' => null,
            'parents'     => [],
            'properties'  => [],
            'methods'     => [],
            'constants'   => [],
            'args'        => []
        ];

        try {
            $reflection = new ReflectionClass($className);
        } catch (\Exception $e) {
            return $data;
        }

        $data = array_merge($data, [
            'wasFound'     => true,
            'startLine'    => $reflection->getStartLine(),
            'name'         => $reflection->getName(),
            'shortName'    => $reflection->getShortName(),
            'filename'     => $reflection->getFileName(),
            'isTrait'      => $reflection->isTrait(),
            'isClass'      => !($reflection->isTrait() || $reflection->isInterface()),
            'isAbstract'   => $reflection->isAbstract(),
            'isInterface'  => $reflection->isInterface(),
            'parents'      => $this->getParentClasses($reflection),
            'args'         => $this->getClassArguments($reflection)
        ]);

        foreach ($reflection->getMethods() as $method) {
            $data['methods'][$method->getName()] = $this->getMethodInfo($method);
        }

        foreach ($reflection->getProperties() as $property) {
            $data['properties'][$property->getName()] = $this->getPropertyInfo($property);
        }

        foreach ($reflection->getConstants() as $constant => $value) {
            $data['constants'][$constant] = $this->getConstantInfo($constant, $reflection);
        }

        return $data;
    }
}
