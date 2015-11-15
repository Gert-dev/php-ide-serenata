<?php

namespace PhpIntegrator;

use ReflectionFunctionAbstract;

/**
 * Fetches information about (global) functions.
 */
class FunctionInfoFetcher implements InfoFetcherInterface
{
    use FetcherInfoTrait;

    /**
     * Fetches documentation about the specified method or function, such as its parameters, a description from the
     * docblock (if available), the return type, ...
     *
     * @param ReflectionFunctionAbstract $function The function or method to analyze.
     *
     * @return array
     */
    protected function getDocumentation(ReflectionFunctionAbstract $function)
    {
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
                            return $this->getDocumentation($traitMethod);
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
                            return $this->getDocumentation($interfaceMethod);
                        }
                    }
                }
            }

            // Walk up base classes to see if any of them have additional info about this method.
            while ($classIterator) {
                if ($classIterator->hasMethod($function->getName())) {
                    $baseClassMethod = $classIterator->getMethod($function->getName());

                    if ($baseClassMethod->getDocComment()) {
                        $baseClassMethodArgs = $this->getDocumentation($baseClassMethod);

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

        return $docParseResult;
    }

    /**
     * {@inheritDoc}
     */
    public function createDefaultInfo(array $options)
    {
        throw new \LogicException("Not implemented yet!");
    }

    /**
     * Retrieves a data structure containing information about the specified function (or method).
     *
     * @param ReflectionFunctionAbstract $function
     *
     * @return array
     */
    public function getInfo($function)
    {
        if (!$function instanceof ReflectionFunctionAbstract) {
            throw new \InvalidArgumentException("The passed argument is not of the correct type!");
        }

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

        $documentation = $this->getDocumentation($function);

        $data = [
            'name'          => $function->getName(),
            'isMethod'      => true,
            'isProperty'    => false,
            'isBuiltin'     => ($function->getFileName() === false),
            'startLine'     => $function->getStartLine(),
            'filename'      => $function->getFileName(),
            'parameters'    => $parameters,
            'optionals'     => $optionals,
            'docParameters' => $documentation['params'],
            'throws'        => $documentation['throws'],
            'descriptions'  => $documentation['descriptions'],
            'deprecated'    => $function->isDeprecated() || $documentation['deprecated'],
            'return'        => $documentation['return']
        ];

        $data['return']['resolvedType'] = $this->determineFullReturnType($data);

        return $data;
    }
}
