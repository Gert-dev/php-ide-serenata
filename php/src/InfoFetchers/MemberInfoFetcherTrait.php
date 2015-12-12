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
        $isMethod = ($reflectionMember instanceof ReflectionMethod);

        $data = [
            'name'      => $declaringClass->name,
            'filename'  => $declaringClass->getFileName(),
            'startLine' => $declaringClass->getStartLine()
        ];

        if ($isMethod) {
            $data['startLineMember'] = $declaringClass->getMethod($reflectionMember->name)->getStartLine();
        } else {
            $data['startLineMember'] = $this->getStartLineForPropertyIn($declaringClass->getFileName(), $reflectionMember->name);
        }

        return $data;
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

        $data = [
            'name'      => $declaringStructure->name,
            'filename'  => $declaringStructure->getFileName(),
            'startLine' => $declaringStructure->getStartLine()
        ];

        if ($isMethod) {
            $data['startLineMember'] = $declaringStructure->getMethod($reflectionMember->name)->getStartLine();
        } else {
            $data['startLineMember'] = $this->getStartLineForPropertyIn($declaringStructure->getFileName(), $reflectionMember->name);
        }

        return $data;
    }

    /**
     * Retrieves the line the specified property starts at.
     *
     * @param string $filename
     * @param string $name
     *
     * @return int|null
     */
    protected function getStartLineForPropertyIn($filename, $name)
    {
        if (!$filename) {
            return null;
        }

        $parser = new FileParser($filename);

        return $parser->getLineForRegex("/(?:protected|public|private|static)\\s+\\$$name/");
    }
}
