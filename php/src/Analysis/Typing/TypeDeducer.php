<?php

namespace PhpIntegrator\Analysis\Typing;

use UnexpectedValueException;

use PhpIntegrator\Analysis\ClasslikeInfoBuilder;

use PhpIntegrator\Analysis\Conversion\FunctionConverter;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;
use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolverFactory;

use PhpIntegrator\Analysis\Visiting\TypeQueryingVisitor;
use PhpIntegrator\Analysis\Visiting\ScopeLimitingVisitor;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpIntegrator\Parsing\PartialParser;
use PhpIntegrator\Parsing\DocblockParser;

use PhpIntegrator\UserInterface\Command\ClassListCommand;

use PhpIntegrator\Utility\NodeHelpers;
use PhpIntegrator\Utility\SourceCodeHelpers;

use PhpParser\Node;
use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\NodeTraverser;

/**
 * Deduces the type(s) of an expression.
 */
class TypeDeducer
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var ClassListCommand
     */
    protected $classListCommand;

    /**
     * @var DocblockParser
     */
    protected $docblockParser;

    /**
     * @var PartialParser
     */
    protected $partialParser;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var TypeResolver
     */
    protected $typeResolver;

    /**
     * @var FileTypeResolverFactory
     */
    protected $fileTypeResolverFactory;

    /**
     * @var TypeQueryingVisitor
     */
    protected $typeQueryingVisitor;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var ClasslikeInfoBuilder
     */
    protected $classlikeInfoBuilder;

    /**
     * @var FunctionConverter
     */
    protected $functionConverter;

    /**
     * Serves as cache to avoid refetching class lists for the same file multiple times.
     *
     * @var array
     */
    protected $fileClassListMap = [];

    /**
     * Serves as cache to avoid rebuilding file type resolvers for the same file multiple times.
     *
     * @var array
     */
    protected $fileTypeResolverMap = [];

    /**
     * @param Parser                  $parser
     * @param ClassListCommand        $classListCommand
     * @param DocblockParser          $docblockParser
     * @param PartialParser           $partialParser
     * @param TypeAnalyzer            $typeAnalyzer
     * @param TypeResolver            $typeResolver
     * @param FileTypeResolverFactory $fileTypeResolverFactory
     * @param IndexDatabase           $indexDatabase
     * @param ClasslikeInfoBuilder    $classlikeInfoBuilder
     * @param FunctionConverter       $functionConverter
     */
    public function __construct(
        Parser $parser,
        ClassListCommand $classListCommand,
        DocblockParser $docblockParser,
        PartialParser $partialParser,
        TypeAnalyzer $typeAnalyzer,
        TypeResolver $typeResolver,
        FileTypeResolverFactory $fileTypeResolverFactory,
        IndexDatabase $indexDatabase,
        ClasslikeInfoBuilder $classlikeInfoBuilder,
        FunctionConverter $functionConverter
    ) {
        $this->parser = $parser;
        $this->classListCommand = $classListCommand;
        $this->docblockParser = $docblockParser;
        $this->partialParser = $partialParser;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->typeResolver = $typeResolver;
        $this->fileTypeResolverFactory = $fileTypeResolverFactory;
        $this->indexDatabase = $indexDatabase;
        $this->classlikeInfoBuilder = $classlikeInfoBuilder;
        $this->functionConverter = $functionConverter;
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

        if (!$firstElement) {
            return [];
        }

        $classRegexPart = "?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*";

        if ($firstElement[0] === '$') {
            $types = $this->getVariableTypes($file, $code, $firstElement, $offset);
        } elseif ($firstElement === 'static' or $firstElement === 'self') {
            $propertyAccessNeedsDollarSign = true;

            $currentClass = $this->getCurrentClassAt($file, $code, $offset);

            $types = [$this->typeAnalyzer->getNormalizedFqcn($currentClass)];
        } elseif ($firstElement === 'parent') {
            $propertyAccessNeedsDollarSign = true;

            $currentClassName = $this->getCurrentClassAt($file, $code, $offset);

            if ($currentClassName) {
                $classInfo = $this->classlikeInfoBuilder->getClasslikeInfo($currentClassName);

                if ($classInfo && !empty($classInfo['parents'])) {
                    $type = $classInfo['parents'][0];

                    $types = [$this->typeAnalyzer->getNormalizedFqcn($type)];
                }
            }
        } elseif ($firstElement[0] === '[') {
            $types = ['array'];
        } elseif ($firstElement === 'null') {
            $types = ['null'];
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
        } elseif (preg_match("/^new\s+(({$classRegexPart}))(?:\(\))?/", $firstElement, $matches) === 1) {
            $types = $this->deduceTypes($file, $code, [$matches[1]], $offset);
        } elseif (preg_match('/^clone\s+(\$[a-zA-Z0-9_]+)/', $firstElement, $matches) === 1) {
            $types = $this->deduceTypes($file, $code, [$matches[1]], $offset);
        } elseif (preg_match('/^(.*?)\(\)$/', $firstElement, $matches) === 1) {
            $globalFunction = $this->indexDatabase->getGlobalFunctionByFqcn($matches[1]);

            if ($globalFunction) {
                $convertedGlobalFunction = $this->functionConverter->convert($globalFunction);

                $types = $this->fetchResolvedTypesFromTypeArrays($convertedGlobalFunction['returnTypes']);
            }
        } elseif (preg_match("/(({$classRegexPart}))/", $firstElement, $matches) === 1) {
            // Static class name.
            $propertyAccessNeedsDollarSign = true;

            $line = SourceCodeHelpers::calculateLineByOffset($code, $offset);

            $types = [$this->getTypeResolverForFile($file)->resolve($matches[1], $line)];
        }

        // We now know what types we need to start from, now it's just a matter of fetching the return types of members
        // in the call stack.
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
                if (!$this->typeAnalyzer->isClassType($type)) {
                    continue; // Can't fetch members of non-class type.
                }

                try {
                    $info = $this->classlikeInfoBuilder->getClasslikeInfo($type);
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
     * @param string $code
     * @param int    $offset
     *
     * @throws UnexpectedValueException
     */
    protected function walkTypeQueryingVisitorTo($code, $offset)
    {
        $nodes = null;

        try {
            $nodes = $this->parser->parse($code);
        } catch (Error $e) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        // In php-parser 2.x, this happens when you enter $this-> before an if-statement, because of a syntax error that
        // it can not recover from.
        if ($nodes === null) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        $scopeLimitingVisitor = new ScopeLimitingVisitor($offset);
        $this->typeQueryingVisitor = new TypeQueryingVisitor($this->docblockParser, $offset);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($scopeLimitingVisitor);
        $traverser->addVisitor($this->typeQueryingVisitor);
        $traverser->traverse($nodes);
    }

    /**
     * @param string     $file
     * @param string     $code
     * @param string     $name
     * @param int        $offset
     *
     * @return string[]
     */
    protected function getVariableTypes($file, $code, $name, $offset)
    {
        $this->walkTypeQueryingVisitorTo($code, $offset);

        $variableName = mb_substr($name, 1);

        $variableTypeInfoMap = $this->typeQueryingVisitor->getVariableTypeInfoMap();
        $offsetLine = SourceCodeHelpers::calculateLineByOffset($code, $offset);

        return $this->getResolvedTypes($variableTypeInfoMap, $variableName, $file, $offsetLine, $code);
    }

    /**
     * @param string $variable
     * @param Node   $node
     * @param string $file
     * @param string $code
     *
     * @return string[]
     */
    protected function getTypesForNode($variable, Node $node, $file, $code)
    {
        if ($node instanceof Node\Expr\Assign) {
            if ($node->expr instanceof Node\Expr\Ternary) {
                $firstOperandType = $this->deduceTypesFromNode(
                    $file,
                    $code,
                    $node->expr->if ?: $node->expr->cond,
                    $node->getAttribute('startFilePos')
                );

                $secondOperandType = $this->deduceTypesFromNode(
                    $file,
                    $code,
                    $node->expr->else,
                    $node->getAttribute('startFilePos')
                );

                return array_unique(array_merge($firstOperandType, $secondOperandType));
            } else {
                return $this->deduceTypesFromNode(
                    $file,
                    $code,
                    $node->expr,
                    $node->getAttribute('startFilePos')
                );
            }
        } elseif ($node instanceof Node\Stmt\Foreach_) {
            $types = $this->deduceTypesFromNode(
                $file,
                $code,
                $node->expr,
                $node->getAttribute('startFilePos')
            );

            foreach ($types as $type) {
                if ($type && mb_strpos($type, '[]') !== false) {
                    $type = mb_substr($type, 0, -2);

                    return $type ? [$type] : [];
                }
            }
        } elseif ($node instanceof Node\FunctionLike) {
            foreach ($node->getParams() as $param) {
                if ($param->name === $variable) {
                    if ($docBlock = $node->getDocComment()) {
                        // Analyze the docblock's @param tags.
                        $name = null;

                        if ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod) {
                            $name = $node->name;
                        }

                        $result = $this->docblockParser->parse((string) $docBlock, [
                            DocblockParser::PARAM_TYPE
                        ], $name, true);

                        if (isset($result['params']['$' . $variable])) {
                            return $this->typeAnalyzer->getTypesForTypeSpecification(
                                $result['params']['$' . $variable]['type']
                            );
                        }
                    }

                    if ($param->type instanceof Node\Name) {
                        return [NodeHelpers::fetchClassName($param->type)];
                    }

                    return $param->type ? [$param->type] : [];
                }
            }
        } elseif ($node instanceof Node\Stmt\ClassLike) {
            return [(string) $node->name];
        } elseif ($node instanceof Node\Name) {
            return [NodeHelpers::fetchClassName($node)];
        }

        return [];
    }

    /**
     * @param array  $variableTypeInfo
     * @param string $variable
     * @param string $file
     * @param string $code
     *
     * @return string[]
     */
    protected function getTypes($variableTypeInfo, $variable, $file, $code)
    {
        if (isset($variableTypeInfo['bestTypeOverrideMatch'])) {
            return $this->typeAnalyzer->getTypesForTypeSpecification($variableTypeInfo['bestTypeOverrideMatch']);
        }

        $guaranteedTypes = [];
        $possibleTypeMap = [];

        $conditionalTypes = isset($variableTypeInfo['conditionalTypes']) ?
            $variableTypeInfo['conditionalTypes'] :
            [];

        foreach ($conditionalTypes as $type => $possibility) {
            if ($possibility === TypeQueryingVisitor::TYPE_CONDITIONALLY_GUARANTEED) {
                $guaranteedTypes[] = $type;
            } elseif ($possibility === TypeQueryingVisitor::TYPE_CONDITIONALLY_POSSIBLE) {
                $possibleTypeMap[$type] = true;
            }
        }

        $types = [];

        // Types guaranteed by a conditional statement take precedence (if they didn't apply, the if statement could
        // never have executed in the first place).
        if (!empty($guaranteedTypes)) {
            $types = $guaranteedTypes;
        } elseif (isset($variableTypeInfo['bestMatch'])) {
            $types = $this->getTypesForNode($variable, $variableTypeInfo['bestMatch'], $file, $code);
        }

        $filteredTypes = [];

        foreach ($types as $type) {
            if (isset($variableTypeInfo['conditionalTypes'][$type])) {
                $possibility = $variableTypeInfo['conditionalTypes'][$type];

                if ($possibility === TypeQueryingVisitor::TYPE_CONDITIONALLY_IMPOSSIBLE) {
                    continue;
                } elseif (isset($possibleTypeMap[$type])) {
                    $filteredTypes[] = $type;
                } elseif ($possibility === TypeQueryingVisitor::TYPE_CONDITIONALLY_GUARANTEED) {
                    $filteredTypes[] = $type;
                }
            } elseif (empty($possibleTypeMap)) {
                // If the possibleTypeMap wasn't empty, the types the variable can have are limited to those present
                // in it (it acts as a whitelist).
                $filteredTypes[] = $type;
            }
        }

        return $filteredTypes;
    }

    /**
     * Retrieves a list of types for the variable, with any referencing types (self, static, $this, ...)
     * resolved to their actual types.
     *
     * @param array  $variableTypeInfoMap
     * @param string $variable
     * @param string $file
     * @param string $code
     *
     * @return string[]
     */
    protected function getUnreferencedTypes($variableTypeInfoMap, $variable, $file, $code)
    {
        $variableTypeInfo = isset($variableTypeInfoMap[$variable]) ? $variableTypeInfoMap[$variable] : [];

        $types = $this->getTypes($variableTypeInfo, $variable, $file, $code);

        $unreferencedTypes = [];

        foreach ($types as $type) {
            if (in_array($type, ['self', 'static', '$this'], true)) {
                $unreferencedTypes = array_merge(
                    $unreferencedTypes,
                    $this->getUnreferencedTypes($variableTypeInfoMap, 'this', $file, $code)
                );
            } else {
                $unreferencedTypes[] = $type;
            }
        }

        return $unreferencedTypes;
    }

    /**
     * Retrieves a list of fully resolved types for the variable.
     *
     * @param array  $variableTypeInfoMap
     * @param string $variable
     * @param string $file
     * @param int    $line
     * @param string $code
     *
     * @return string[]
     */
    protected function getResolvedTypes($variableTypeInfoMap, $variable, $file, $line, $code)
    {
        $types = $this->getUnreferencedTypes($variableTypeInfoMap, $variable, $file, $code);

        $variableTypeInfo = isset($variableTypeInfoMap[$variable]) ? $variableTypeInfoMap[$variable] : [];

        $resolvedTypes = [];

        foreach ($types as $type) {
            if ($this->typeAnalyzer->isClassType($type)) {
                $typeLine = isset($variableTypeInfo['bestTypeOverrideMatchLine']) ?
                    $variableTypeInfo['bestTypeOverrideMatchLine'] :
                    $line;

                $type = $this->getTypeResolverForFile($file)->resolve($type, $typeLine);
            }

            $resolvedTypes[] = $type;
        }

        return $resolvedTypes;
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
                return [NodeHelpers::fetchClassName($node->class), $node->name . '()'];
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
            return [NodeHelpers::fetchClassName($node)];
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
        $line = SourceCodeHelpers::calculateLineByOffset($source, $offset);

        return $this->getCurrentClassAtLine($file, $source, $line);
    }

    /**
     * @param string $file
     * @param string $source
     * @param int    $line
     *
     * @return string|null
     */
    protected function getCurrentClassAtLine($file, $source, $line)
    {
        $classes = $this->getClassListForFile($file);

        foreach ($classes as $fqcn => $class) {
            if ($line >= $class['startLine'] && $line <= $class['endLine']) {
                return $fqcn;
            }
        }

        return null;
    }

    /**
     * @param string $file
     *
     * @return array
     */
    protected function getClassListForFile($file)
    {
        if (!isset($this->fileClassListMap[$file])) {
            $this->fileClassListMap[$file] = $this->classListCommand->getClassList($file);
        }

        return $this->fileClassListMap[$file];
    }

    /**
     * @param string $file
     *
     * @return FileTypeResolver
     */
    protected function getTypeResolverForFile($file)
    {
        if (!isset($this->fileTypeResolverMap[$file])) {
            $this->fileTypeResolverMap[$file] = $this->fileTypeResolverFactory->create($file);
        }

        return $this->fileTypeResolverMap[$file];
    }
}
