<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\TypeAnalyzer;
use PhpIntegrator\IndexDatabase;
use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

use PhpParser\Node;

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
        $optionCollection->add('file:', 'The file to examine.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('part+', 'A part of the expression as string. Specify this as many times as you have parts.')->isa('string');
        $optionCollection->add('offset:', 'The character byte offset into the code to use for the determination.')->isa('number');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['file'])) {
            throw new UnexpectedValueException('Either a --file file must be supplied or --stdin must be passed!');
        } elseif (!isset($arguments['offset'])) {
            throw new UnexpectedValueException('An --offset must be supplied into the source code!');
        } elseif (!isset($arguments['part'])) {
            throw new UnexpectedValueException('You must specify at least one part using --part!');
        }

        $code = $this->getSourceCode(
            isset($arguments['file']) ? $arguments['file']->value : null,
            isset($arguments['stdin']) && $arguments['stdin']->value
        );

        $result = $this->deduceType(
           isset($arguments['file']) ? $arguments['file']->value : null,
           $code,
           $arguments['part']->value,
           $arguments['offset']->value
        );

        return $this->outputJson(true, $result);
    }

    /**
     * @param string   $file
     * @param string   $code
     * @param string[] $expressionParts
     * @param int      $offset
     *
     * @return string|null
     */
    public function deduceType($file, $code, array $expressionParts, $offset)
    {
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
            $className = $this->getVariableTypeCommand()->getVariableType($file, $code, $firstElement, $offset);
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
            $className = $this->deduceType($file, $code, [$matches[1]], $offset);
        } elseif (preg_match('/^clone\s+(\$[a-zA-Z0-9_]+)/', $firstElement, $matches) === 1) {
            $className = $this->deduceType($file, $code, [$matches[1]], $offset);
        } elseif (preg_match('/^(.*?)\(\)$/', $firstElement, $matches) === 1) {
            // Global PHP function.

            // TODO: No need to fetch all global functions here.
            $globalFunctions = $this->getGlobalFunctionsCommand()->getGlobalFunctions();

            if (isset($globalFunctions[$matches[1]])) {
                $returnTypes = $globalFunctions[$matches[1]]['returnTypes'];

                if (count($returnTypes) === 1) {
                    $className = $returnTypes[0]['resolvedType'];
                }
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
        $storageProxy = new DeduceType\IndexDataAdapterProvider($this->indexDatabase, null);
        $dataAdapter = new IndexDataAdapter($storageProxy);

        foreach ($expressionParts as $element) {
            if ($this->getTypeAnalyzer()->isSpecialType($className)) {
                $className = null;
                break;
            }

            $isMethod = false;
            $isValidPropertyAccess = false;

            if (mb_strpos($element, '()') !== false) {
                $isMethod = true;
                $element = str_replace('()', '', $element);
            } elseif (!$propertyAccessNeedsDollarSign) {
                $isValidPropertyAccess = true;
            } elseif (!empty($element) && $element[0] === '$') {
                $element = mb_substr($element, 1);
                $isValidPropertyAccess = true;
            }

            $classNameToSearch = ($className && $className[0] === '\\' ? mb_substr($className, 1) : $className);

            $id = $this->indexDatabase->getStructureId($classNameToSearch);

            $storageProxy->setMemberFilter($element);
            $info = $dataAdapter->getStructureInfo($id);

            $className = null;

            if ($isMethod) {
                if (isset($info['methods'][$element])) {
                    $returnTypes = $info['methods'][$element]['returnTypes'];

                    if (count($returnTypes) === 1) {
                        $className = $returnTypes[0]['resolvedType'];
                    }
                }
            } elseif (isset($info['constants'][$element])) {
                $types = $info['constants'][$element]['types'];

                if (count($types) === 1) {
                    $className = $types[0]['resolvedType'];
                }
            } elseif ($isValidPropertyAccess && isset($info['properties'][$element])) {
                $types = $info['properties'][$element]['types'];

                if (count($types) === 1) {
                    $className = $types[0]['resolvedType'];
                }
            }

            $propertyAccessNeedsDollarSign = false;
        }

        if ($className && !$this->getTypeAnalyzer()->isSpecialType($className) && $className[0] !== "\\") {
            $className = "\\" . $className;
        }

        return $className;
    }

    /**
     * @param string|null $file
     * @param string      $code
     * @param Node\Expr   $expression
     * @param int         $offset
     *
     * @return string|null
     */
    public function deduceTypeFromNode($file, $code, Node\Expr $expression, $offset)
    {
        $expressionParts = $this->convertExpressionToStringParts($expression);

        return $expressionParts ? $this->deduceType($file, $code, $expressionParts, $offset) : null;
    }

    /**
     * This function acts as an adapter for AST node data to an array of strings for the reimplementation of the
     * CoffeeScript DeduceType method. As such, this bridge will be removed over time, as soon as DeduceType  works with
     * an AST instead of regular expression parsing. At that point, input of string call stacks from the command line
     * can be converted to an intermediate AST so data from CoffeeScript (that has no notion of the AST) can be treated
     * the same way.
     *
     * @param Node\Expr $node
     *
     * @return string[]|null
     */
    protected function convertExpressionToStringParts(Node\Expr $node)
    {
        if ($node instanceof Node\Expr\Variable) {
            if (is_string($node->name)) {
                return ['$' . (string) $node->name];
            }
        } elseif ($node instanceof Node\Expr\New_) {
            if ($node->class instanceof Node\Name) {
                $newName = (string) $node->class;

                if ($node->class->isFullyQualified() && $newName[0] !== '\\') {
                    $newName = '\\' . $newName;
                }

                return ['new ' . $newName];
            }
        } elseif ($node instanceof Node\Expr\Clone_) {
            if ($node->expr instanceof Node\Expr\Variable) {
                return ['clone $' . $node->expr->name];
            }
        } elseif ($node instanceof Node\Expr\Closure) {
            return ['function ()'];
        } elseif ($node instanceof Node\Expr\Array_) {
            return ['['];
        } elseif ($node instanceof Node\Scalar\LNumber) {
            return ['1'];
        } elseif ($node instanceof Node\Scalar\DNumber) {
            return ['1.1'];
        } elseif ($node instanceof Node\Expr\ConstFetch) {
            if ($node->name->toString() === 'true' || $node->name->toString() === 'false') {
                return ['true'];
            }
        } elseif ($node instanceof Node\Scalar\String_) {
            return ['""'];
        } elseif ($node instanceof Node\Expr\MethodCall) {
            if (is_string($node->name)) {
                $parts = $this->convertExpressionToStringParts($node->var);
                $parts[] = $node->name . '()';

                return $parts;
            }
        } elseif ($node instanceof Node\Expr\StaticCall) {
            if (is_string($node->name) && $node->class instanceof Node\Name) {
                return [$node->class->toString(), $node->name . '()'];
            }
        } elseif ($node instanceof Node\Expr\PropertyFetch) {
            if (is_string($node->name)) {
                $parts = $this->convertExpressionToStringParts($node->var);
                $parts[] = $node->name;

                return $parts;
            }
        } elseif ($node instanceof Node\Expr\StaticPropertyFetch) {
            if (is_string($node->name) && $node->class instanceof Node\Name) {
                return [$node->name->toString(), $node->name];
            }
        } elseif ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Name) {
                return [$node->name->toString() . '()'];
            }
        }

        return null;
    }

    /**
     * @param string $file
     * @param string $source
     * @param int    $offset
     *
     * @return string|null
     */
    protected function getCurrentClassAt($file, $source, $offset)
    {
        $line = $this->calculateLineByOffset($source, $offset);

        $classes = $this->getClassListCommand()->getClassList($file);

        foreach ($classes as $fqsen => $class) {
            if ($line >= $class['startLine'] && $line <= $class['endLine']) {
                return $fqsen;
            }
        }

        return null;
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
