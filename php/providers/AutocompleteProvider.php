<?php

namespace PhpIntegrator;

class AutocompleteProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute($args = [])
    {
        $class = $args[0];
        $name  = $args[1];

        if (mb_strpos($class, '\\') === 0) {
            $class = substr($class, 1);
        }

        $isMethod = false;

        if (mb_strpos($name, '()') !== false) {
            $isMethod = true;
            $name = str_replace('()', '', $name);
        }

        $memberInfo = null;
        $relevantClass = null;
        $classInfo = $this->getClassInfo($class);

        if ($isMethod && isset($classInfo['methods'][$name])) {
            $memberInfo = $classInfo['methods'][$name];
        } elseif (!$isMethod && isset($classInfo['properties'][$name])) {
            $memberInfo = $classInfo['properties'][$name];
        }

        if ($memberInfo) {
            $returnValue = $memberInfo['args']['return']['type'];

            if ($returnValue == '$this' || $returnValue == 'static') {
                $relevantClass = $class;
            } elseif ($returnValue === 'self') {
                $relevantClass = $memberInfo['declaringClass']['name'];
            } else {
                $soleClassName = $this->getSoleClassName($returnValue);

                if ($soleClassName) {
                    // At this point, this could either be a class name relative to the current namespace or a full
                    // class name without a leading slash. For example, Foo\Bar could also be relative (e.g.
                    // My\Foo\Bar), in which case its absolute path is determined by the namespace and use statements
                    // of the file containing it.
                    $relevantClass = $soleClassName;

                    if (!empty($soleClassName) && $soleClassName[0] !== "\\") {
                        $parser = new FileParser($memberInfo['declaringStructure']['filename']);

                        $useStatementFound = false;
                        $completedClassName = $parser->getFullClassName($soleClassName, $useStatementFound);

                        if ($useStatementFound) {
                            $relevantClass = $completedClassName;
                        } else {
                            $isRelativeClass = true;

                            // Try instantiating the class, e.g. My\Foo\Bar.
                            try {
                                $reflection = new \ReflectionClass($completedClassName);

                                $relevantClass = $completedClassName;
                            } catch (\Exception $e) {
                                // The class, e.g. My\Foo\Bar, didn't exist. We can only assume its an absolute path,
                                // using a namespace set up in composer.json, without a leading slash.
                            }
                        }
                    }
                }
            }
        }

        // Minor optimization to avoid fetching the same data twice.
        return ($relevantClass === $class) ? $classInfo : $this->getClassInfo($relevantClass);
    }

    /**
     * Retrieves the sole class name from the specified return value statement.
     *
     * @example "null" returns null.
     * @example "FooClass" returns "FooClass".
     * @example "FooClass|null" returns "FooClass".
     * @example "FooClass|BarClass|null" returns null (there is no single type).
     *
     * @param string $returnValueStatement
     *
     * @return string|null
     */
    protected function getSoleClassName($returnValueStatement)
    {
        if ($returnValueStatement) {
            $types = explode(DocParser::TYPE_SPLITTER, $returnValueStatement);

            $classTypes = [];

            foreach ($types as $type) {
                if ($this->isClassType($type)) {
                    $classTypes[] = $type;
                }
            }

            if (count($classTypes) === 1) {
                return $classTypes[0];
            }
        }

        return null;
    }

    /**
     * Returns a boolean indicating if the specified value is a class type or not.
     *
     * @param string $type
     *
     * @return bool
     */
    protected function isClassType($type)
    {
        return ucfirst($type) === $type;
    }
}
