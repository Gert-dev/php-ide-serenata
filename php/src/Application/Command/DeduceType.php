<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\TypeAnalyzer;
use PhpIntegrator\IndexDatabase;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Allows deducing the type of an expression (e.g. a call chain, a simple string, ...).
 */
class DeduceType extends BaseCommand
{
    /**
     * @var VariableType
     */
    protected $variableTypeCommand;

    /**
     * @var ClassList
     */
    protected $classListCommand;

    /**
     * @var ClassInfo
     */
    protected $classInfoCommand;

    /**
     * @var ResolveType
     */
    protected $resolveTypeCommand;

    /**
     * @var GlobalFunctions
     */
    protected $globalFunctionsCommand;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        // $optionCollection->add('file?', 'The file to examine.')->isa('string');
        // $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        // $optionCollection->add('name:', 'The name of the variable to examine.')->isa('string');
        // $optionCollection->add('offset:', 'The character byte offset into the code to use for the determination.')->isa('number');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
    //     if (!isset($arguments['file']) && (!isset($arguments['stdin']) || !$arguments['stdin']->value)) {
    //         throw new UnexpectedValueException('Either a --file file must be supplied or --stdin must be passed!');
    //     } elseif (!isset($arguments['offset'])) {
    //         throw new UnexpectedValueException('An --offset must be supplied into the source code!');
    //     } elseif (!isset($arguments['name'])) {
    //         throw new UnexpectedValueException('The name of the variable must be set using --name!');
    //     }
       //
    //     $result = $this->getVariableType(
    //        isset($arguments['file']) ? $arguments['file']->value : null,
    //        $arguments['name']->value,
    //        $arguments['offset']->value,
    //        isset($arguments['stdin']) && $arguments['stdin']->value
    //    );
       //
    //    return $this->outputJson(true, $result);
    }


    /**
     * @param string|null $file
     * @param string[]    $expressionParts
     * @param int         $offset
     * @param bool        $isStdin
     */
    public function deduceType($file, array $expressionParts, $offset, $isStdin)
    {
        $code = null;

        if ($isStdin) {
            // NOTE: This call is blocking if there is no input!
            $code = file_get_contents('php://stdin');
        } else {
            if (!$file) {
                throw new UnexpectedValueException('The specified file does not exist!');
            }

            $code = @file_get_contents($file);
        }

        // TODO: Must be able to feed source code to other commands, need to split off source code fetching there.

        // TODO: Using regular expressions here is kind of silly. We should refactor this to actually analyze php-parser
        // nodes at a later stage. At the moment this is just a one-to-one translation of the original CoffeeScript
        // method.

        $i = 0;
        $className = null;

        if (empty($expressionParts)) {
            return null;
        }

        $propertyAccessNeedsDollarSign = false;
        $firstElement = array_shift($expressionParts);

        $classRegexPart = "?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*";

        if ($firstElement[0] === '$') {
            // TODO: Feed source code.
            $className = $this->getVariableTypeCommand()->getVariableType($file, $firstElement, $offset, false);

            $className = $className['resolvedType'];
        } elseif ($firstElement === 'static' or $firstElement === 'self') {
            $propertyAccessNeedsDollarSign = true;

            $className = $this->getCurrentClassAt($file, $code, $offset);
        } elseif ($firstElement === 'parent') {
            $propertyAccessNeedsDollarSign = true;

            $className = $this->getCurrentClassAt($file, $code, $offset);

            if ($className) {
                $classInfo = $this->getClassInfoCommand()->getClassInfo($className);

                if ($classInfo && !empty($classInfo['parents'])) {
                    $className = $classInfo['parents'][0];
                }
            }
        } elseif ($firstElement[0] === '[') {
            $className = 'array';
        } elseif (preg_match('/^(0x)?\d+$/', $firstElement) === 1) {
            $className = 'int';
        } elseif (preg_match('/^\d+.\d+$/', $firstElement) === 1) {
            $className = 'float';
        } elseif (preg_match('/^(true|false)$/', $firstElement) === 1) {
            $className = 'bool';
        } elseif (preg_match('/^"(.|\n)*"$/', $firstElement) === 1) {
            $className = 'string';
        } elseif (preg_match('/^\'(.|\n)*\'$/', $firstElement) === 1) {
            $className = 'string';
        } elseif (preg_match('/^array\s*\(/', $firstElement) === 1) {
            $className = 'array';
        } elseif (preg_match('/^function\s*\(/', $firstElement) === 1) {
            $className = '\\Closure';
        } elseif (preg_match("/^new\s+((${classRegexPart}))(?:\(\))?/", $firstElement, $matches) === 1) {
            // TODO: Feed source code.
            $className = $this->deduceType($file, [$matches[1]], $offset, false);
        } elseif (preg_match('/^clone\s+(\$[a-zA-Z0-9_]+)/', $firstElement, $matches) === 1) {
            // TODO: Feed source code.
            $className = $this->deduceType($file, [$matches[1]], $offset, false);
        } elseif (preg_match('/^(.*?)\(\)$/', $firstElement, $matches) === 1) {
            // Global PHP function.

            // TODO: No need to fetch all global functions here.
            $globalFunctions = $this->getGlobalFunctionsCommand()->getGlobalFunctions();

            if (isset($globalFunctions[$matches[1]])) {
                $className = $globalFunctions[$matches[1]]['return']['type'];
            }
        } elseif (preg_match("/((${classRegexPart}))/", $firstElement, $matches) === 1) {
            // Static class name.
            $propertyAccessNeedsDollarSign = true;

            $line = $this->calculateLineByOffset($code, $offset);

            $className = $this->getResolveTypeCommand()->resolveType($matches[1], $file, $line);
        } else {
            $className = null; // No idea what this is.
        }

        if (!$className) {
            return null;
        }

        // We now know what class we need to start from, now it's just a matter of fetching the return types of members
        // in the call stack.
        foreach ($expressionParts as $element) {
            if (!$this->getTypeAnalyzer()->isSpecialType($className)) {
                $info = $this->getClassInfoCommand()->getClassInfo($className);

                $className = null;

                if (mb_strpos($element, '()') !== false) {
                    $element = str_replace('()', '', $element);

                    if (isset($info['methods'][$element])) {
                        $className = $info['methods'][$element]['return']['resolvedType'];
                    }
                } elseif (isset($info['constants'][$element])) {
                    $className = $info['constants'][$element]['return']['resolvedType'];
                } else {
                    $isValidPropertyAccess = false;

                    if (!$propertyAccessNeedsDollarSign) {
                        $isValidPropertyAccess = true;
                    } elseif (!empty($element) && $element[0] === '$') {
                        $element = mb_substr($element, 1);
                        $isValidPropertyAccess = true;
                    }

                    if ($isValidPropertyAccess && isset($info['properties'][$element])) {
                        $className = $info['properties'][$element]['return']['resolvedType'];
                    }
                }
            } else {
                $className = null;
                break;
            }

            $propertyAccessNeedsDollarSign = false;
        }

        return $className;
    }

    protected function getCurrentClassAt($file, $source, $offset)
    {
        $line = $this->calculateLineByOffset($source, $offset);

        $classes = $this->getClassListCommand()->getClassList($file);

        // TODO: check if returned value is also 1-indexed.

        foreach ($classes as $fqsen => $class) {
            if ($line >= $class['startLine'] && $line <= $class['endLine']) {
                return $fqsen;
            }
        }

        return null;
    }

    // TODO: Move somewhere we can reuse this. Calculates the 1-indexed line. Also already exists in VariableType.
    protected function calculateLineByOffset($source, $offset)
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * @inheritDoc
     */
    public function setIndexDatabase(IndexDatabase $indexDatabase)
    {
        if ($this->variableTypeCommand) {
            $this->getVariableTypeCommand()->setIndexDatabase($indexDatabase);
        }

        if ($this->classListCommand) {
            $this->getClassListCommand()->setIndexDatabase($indexDatabase);
        }

        if ($this->classInfoCommand) {
            $this->getClassInfoCommand()->setIndexDatabase($indexDatabase);
        }

        if ($this->resolveTypeCommand) {
            $this->getResolveTypeCommand()->setIndexDatabase($indexDatabase);
        }

        if ($this->globalFunctionsCommand) {
            $this->getGlobalFunctionsCommand()->setIndexDatabase($indexDatabase);
        }

        parent::setIndexDatabase($indexDatabase);
    }

    /**
     * @return VariableType
     */
    protected function getVariableTypeCommand()
    {
        if (!$this->variableTypeCommand) {
            $this->variableTypeCommand = new VariableType();
            $this->variableTypeCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->variableTypeCommand;
    }

    /**
     * @return ClassList
     */
    protected function getClassListCommand()
    {
        if (!$this->classListCommand) {
            $this->classListCommand = new ClassList();
            $this->classListCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->classListCommand;
    }

    /**
     * @return ClassInfo
     */
    protected function getClassInfoCommand()
    {
        if (!$this->classInfoCommand) {
            $this->classInfoCommand = new ClassInfo();
            $this->classInfoCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->classInfoCommand;
    }

    /**
     * @return GlobalFunctions
     */
    protected function getGlobalFunctionsCommand()
    {
        if (!$this->globalFunctionsCommand) {
            $this->globalFunctionsCommand = new GlobalFunctions();
            $this->globalFunctionsCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->globalFunctionsCommand;
    }

    /**
     * @return ResolveType
     */
    protected function getResolveTypeCommand()
    {
        if (!$this->resolveTypeCommand) {
            $this->resolveTypeCommand = new ResolveType();
            $this->resolveTypeCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->resolveTypeCommand;
    }

    /**
     * @return TypeAnalyzer
     */
    protected function getTypeAnalyzer()
    {
        if (!$this->typeAnalyzer) {
            $this->typeAnalyzer = new TypeAnalyzer();
        }

        return $this->typeAnalyzer;
    }
}
