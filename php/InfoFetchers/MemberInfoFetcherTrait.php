<?php

namespace PhpIntegrator;

use Reflector;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Trait that contains useful functionality for fetching information about class members.
 */
trait MemberInfoFetcherTrait
{
    /**
     * Retrieves information about what the specified member is overriding, if anything.
     *
     * @param ReflectionMethod|ReflectionProperty $reflectionMember
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
            'declaringClass'     => $this->getDeclaringClass($overriddenMember),
            'declaringStructure' => $this->getDeclaringStructure($overriddenMember),
            'startLine'          => $startLine
        ];
    }

    /**
     * Retrieves information about the class that contains the specified reflection member.
     *
     * @param ReflectionMethod|ReflectionProperty $reflectionMember
     *
     * @return array
     */
    protected function getDeclaringClass(Reflector $reflectionMember)
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
     * Retrieves information about the structure (class, trait, interface, ...) that contains the specified reflection
     * member.
     *
     * @param ReflectionMethod|ReflectionProperty $reflectionMember
     *
     * @return array
     */
    protected function getDeclaringStructure(Reflector $reflectionMember)
    {
        // This will point to the class that contains the member, which will resolve to the parent class if it's
        // inherited (and not overridden).
        $declaringStructure = $reflectionMember->getDeclaringClass();
        $isMethod = ($reflectionMember instanceof ReflectionMethod);

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
}
