<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\TypeAnalyzer;
use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpParser\Node;

/**
 * Allows deducing the types of an expression (e.g. a call chain, a simple string, ...).
 */
class DeduceTypes extends AbstractCommand
{
    /**
     * @var VariableTypes
     */
    protected $variableTypesCommand;

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
        $optionCollection->add('charoffset?', 'If set, the input offset will be treated as a character offset instead of a byte offset.');
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

        $offset = $arguments['offset']->value;

        if (isset($arguments['charoffset']) && $arguments['charoffset']->value == true) {
            $offset = $this->getCharacterOffsetFromByteOffset($offset, $code);
        }

        $result = $this->deduceTypes(
           isset($arguments['file']) ? $arguments['file']->value : null,
           $code,
           $arguments['part']->value,
           $offset
        );

        return $this->outputJson(true, $result);
    }

    /**
     * @param string   $file
     * @param string   $code
     * @param string[] $expressionParts
     * @param int      $offset
     *
     * @return string[]
     */
    public function deduceTypes($file, $code, array $expressionParts, $offset)
    {
        // TODO: Using regular expressions here is kind of silly. We should refactor this to actually analyze php-parser
        // nodes at a later stage. At the moment this is just a one-to-one translation of the original CoffeeScript
        // method.

        $types = [];

        if (empty($expressionParts)) {
            return $types;
        }

        $propertyAccessNeedsDollarSign = false;
        $firstElement = array_shift($expressionParts);

        $classRegexPart = "?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*";

        if ($firstElement[0] === '$') {
            $types = $this->getVariableTypesCommand()->getVariableTypes($file, $code, $firstElement, $offset);
        } elseif ($firstElement === 'static' or $firstElement === 'self') {
            $propertyAccessNeedsDollarSign = true;

            $currentClass = $this->getCurrentClassAt($file, $code, $offset);

            $types = [$this->getTypeAnalyzer()->getNormalizedFqcn($currentClass, true)];
        } elseif ($firstElement === 'parent') {
            $propertyAccessNeedsDollarSign = true;

            $currentClassName = $this->getCurrentClassAt($file, $code, $offset);

            if ($currentClassName) {
                $classInfo = $this->getClassInfoCommand()->getClassInfo($currentClassName);

                if ($classInfo && !empty($classInfo['parents'])) {
                    $type = $classInfo['parents'][0];

                    $types = [$this->getTypeAnalyzer()->getNormalizedFqcn($type, true)];
                }
            }
        } elseif ($firstElement[0] === '[') {
            $types = ['array'];
        } elseif (preg_match('/^(0x)?\d+$/', $firstElement) === 1) {
            $types = ['int'];
        } elseif (preg_match('/^\d+.\d+$/', $firstElement) === 1) {
            $types = ['float'];
        } elseif (preg_match('/^(true|false)$/', $firstElement) === 1) {
            $types = ['bool'];
        } elseif (preg_match('/^"(.|\n)*"$/', $firstElement) === 1) {
            $types = ['string'];
        } elseif (preg_match('/^\'(.|\n)*\'$/', $firstElement) === 1) {
            $types = ['string'];
        } elseif (preg_match('/^array\s*\(/', $firstElement) === 1) {
            $types = ['array'];
        } elseif (preg_match('/^function\s*\(/', $firstElement) === 1) {
            $types = ['\Closure'];
        } elseif (preg_match("/^new\s+((${classRegexPart}))(?:\(\))?/", $firstElement, $matches) === 1) {
            $types = $this->deduceTypes($file, $code, [$matches[1]], $offset);
        } elseif (preg_match('/^clone\s+(\$[a-zA-Z0-9_]+)/', $firstElement, $matches) === 1) {
            $types = $this->deduceTypes($file, $code, [$matches[1]], $offset);
        } elseif (preg_match('/^(.*?)\(\)$/', $firstElement, $matches) === 1) {
            // Global PHP function.

            // TODO: No need to fetch all global functions here.
            $globalFunctions = $this->getGlobalFunctionsCommand()->getGlobalFunctions();

            if (isset($globalFunctions[$matches[1]])) {
                $returnTypes = $globalFunctions[$matches[1]]['returnTypes'];

                if (count($returnTypes) === 1) {
                    $types = $this->fetchResolvedTypesFromTypeArrays($returnTypes);
                }
            }
        } elseif (preg_match("/((${classRegexPart}))/", $firstElement, $matches) === 1) {
            // Static class name.
            $propertyAccessNeedsDollarSign = true;

            $line = $this->calculateLineByOffset($code, $offset);

            $types = [$this->getResolveTypeCommand()->resolveType($matches[1], $file, $line)];
        }

        // We now know what types we need to start from, now it's just a matter of fetching the return types of members
        // in the call stack.
        $dataAdapter = $this->getIndexDataAdapter();

        foreach ($expressionParts as $element) {
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

            $newTypes = [];

            foreach ($types as $type) {
                if (!$this->getTypeAnalyzer()->isClassType($type)) {
                    continue; // Can't fetch members of non-class type.
                }

                $classNameToSearch = ($type && $type[0] === '\\' ? mb_substr($type, 1) : $type);

                try {
                    $info = $dataAdapter->getStructureInfo($classNameToSearch);
                } catch (UnexpectedValueException $e) {
                    continue;
                }

                $fetchedTypes = [];

                if ($isMethod) {
                    if (isset($info['methods'][$element])) {
                        $fetchedTypes = $this->fetchResolvedTypesFromTypeArrays($info['methods'][$element]['returnTypes']);
                    }
                } elseif (isset($info['constants'][$element])) {
                    $fetchedTypes = $this->fetchResolvedTypesFromTypeArrays($info['constants'][$element]['types']);
                } elseif ($isValidPropertyAccess && isset($info['properties'][$element])) {
                    $fetchedTypes = $this->fetchResolvedTypesFromTypeArrays($info['properties'][$element]['types']);
                }

                if (!empty($fetchedTypes)) {
                    $newTypes += array_combine($fetchedTypes, array_fill(0, count($fetchedTypes), true));
                }
            }

            // We use an associative array so we automatically avoid duplicate types.
            $types = array_keys($newTypes);

            $propertyAccessNeedsDollarSign = false;
        }

        return $types;
    }

    /**
     * @param array $typeArray
     *
     * @return string
     */
    protected function fetchResolvedTypeFromTypeArray(array $typeArray)
    {
        return $typeArray['resolvedType'];
    }

    /**
     * @param array $typeArrays
     *
     * @return string[]
     */
    protected function fetchResolvedTypesFromTypeArrays(array $typeArrays)
    {
        return array_map([$this, 'fetchResolvedTypeFromTypeArray'], $typeArrays);
    }

    /**
     * @param string|null $file
     * @param string      $code
     * @param Node        $expression
     * @param int         $offset
     *
     * @return string[]
     */
    public function deduceTypesFromNode($file, $code, Node $expression, $offset)
    {
        $expressionParts = $this->convertNodeToStringParts($expression);

        return $expressionParts ? $this->deduceTypes($file, $code, $expressionParts, $offset) : [];
    }

    /**
     * This function acts as an adapter for AST node data to an array of strings for the reimplementation of the
     * CoffeeScript DeduceType method. As such, this bridge will be removed over time, as soon as DeduceType  works with
     * an AST instead of regular expression parsing. At that point, input of string call stacks from the command line
     * can be converted to an intermediate AST so data from CoffeeScript (that has no notion of the AST) can be treated
     * the same way.
     *
     * @param Node $node
     *
     * @return string[]|null
     */
    protected function convertNodeToStringParts(Node $node)
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
                $parts = $this->convertNodeToStringParts($node->var);
                $parts[] = $node->name . '()';

                return $parts;
            }
        } elseif ($node instanceof Node\Expr\StaticCall) {
            if (is_string($node->name) && $node->class instanceof Node\Name) {
                return [$this->fetchClassName($node->class), $node->name . '()'];
            }
        } elseif ($node instanceof Node\Expr\PropertyFetch) {
            if (is_string($node->name)) {
                $parts = $this->convertNodeToStringParts($node->var);
                $parts[] = $node->name;

                return $parts;
            }
        } elseif ($node instanceof Node\Expr\StaticPropertyFetch) {
            if (is_string($node->name) && $node->class instanceof Node\Name) {
                return [$node->class->toString(), $node->name];
            }
        } elseif ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Name) {
                return [$node->name->toString() . '()'];
            }
        } elseif ($node instanceof Node\Name) {
            return [$this->fetchClassName($node)];
        }

        return null;
    }

    /**
     * Takes a class name and turns it into a string.
     *
     * @param Node\Name $name
     *
     * @return string
     */
    protected function fetchClassName(Node\Name $name)
    {
        $newName = (string) $name;

        if ($name->isFullyQualified() && $newName[0] !== '\\') {
            $newName = '\\' . $newName;
        }

        return $newName;
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

        foreach ($classes as $fqcn => $class) {
            if ($line >= $class['startLine'] && $line <= $class['endLine']) {
                return $fqcn;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function setIndexDatabase(IndexDatabase $indexDatabase)
    {
        if ($this->variableTypesCommand) {
            $this->getVariableTypesCommand()->setIndexDatabase($indexDatabase);
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
     * @return VariableTypes
     */
    protected function getVariableTypesCommand()
    {
        if (!$this->variableTypesCommand) {
            $this->variableTypesCommand = new VariableTypes($this->cache);
            $this->variableTypesCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->variableTypesCommand;
    }

    /**
     * @return ClassList
     */
    protected function getClassListCommand()
    {
        if (!$this->classListCommand) {
            $this->classListCommand = new ClassList($this->cache);
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
            $this->classInfoCommand = new ClassInfo($this->cache);
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
            $this->globalFunctionsCommand = new GlobalFunctions($this->cache);
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
            $this->resolveTypeCommand = new ResolveType($this->cache);
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
