<?php

namespace PhpIntegrator\Application\Command\VariableType;

use PhpIntegrator\DocParser;
use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Application\Command\DeduceTypes;
use PhpIntegrator\Application\Command\ResolveType;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that queries the nodes for information about an invoked function or method.
 */
class QueryingVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $line;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var ResolveType
     */
    protected $resolveTypeCommand;

    /**
     * @var DeduceTypes
     */
    protected $deduceTypesCommand;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var Node\FunctionLike|null
     */
    protected $lastFunctionLikeNode;

    /**
     * @var string|null
     */
    protected $currentClassName;

    /**
     * @var Node|string||null
     */
    protected $bestMatch;

    /**
     * @var string|null
     */
    protected $bestTypeOverrideMatch;

    /**
     * @var int|null
     */
    protected $bestTypeOverrideMatchLine;

    /**
     * Constructor.
     *
     * @param string       $file
     * @param string       $code
     * @param int          $position
     * @param int          $line
     * @param string       $name
     * @param TypeAnalyzer $typeAnalyzer
     * @param ResolveType  $resolveTypeCommand
     * @param DeduceTypes   $deduceTypesCommand
     */
    public function __construct(
        $file,
        $code,
        $position,
        $line,
        $name,
        TypeAnalyzer $typeAnalyzer,
        ResolveType $resolveTypeCommand,
        DeduceTypes $deduceTypesCommand
    ) {
        $this->name = $name;
        $this->line = $line;
        $this->file = $file;
        $this->code = $code;
        $this->position = $position;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->deduceTypesCommand = $deduceTypesCommand;
        $this->resolveTypeCommand = $resolveTypeCommand;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        $startFilePos = $node->getAttribute('startFilePos');

        if ($startFilePos >= $this->position) {
            if ($startFilePos == $this->position) {
                // We won't analyze this node anymore (it falls outside the position and can cause infinite recursion
                // otherwise), but php-parser matches each docblock with the next node. That docblock might still
                // contain a type override annotation we need to parse.
                $this->parseNodeDocblock($node);
            }

            // We've gone beyond the requested position, there is nothing here that can still be relevant anymore.
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        $this->parseNodeDocblock($node);

        if ($node instanceof Node\Stmt\Catch_) {
            if ($node->var === $this->name) {
                $this->bestMatch = $this->fetchClassName($node->type);
            }
        } elseif ($node instanceof Node\Stmt\If_ || $node instanceof Node\Stmt\ElseIf_) {
            if ($node->cond instanceof Node\Expr\Instanceof_) {
                if ($node->cond->expr instanceof Node\Expr\Variable && $node->cond->expr->name === $this->name) {
                    if ($node->cond->class instanceof Node\Name) {
                        $this->bestMatch = $this->fetchClassName($node->cond->class);
                    } else {
                        // TODO: This is an expression, parse it to retrieve its return value.
                    }
                }
            }
        } elseif ($node instanceof Node\Expr\Assign) {
            if ($node->var instanceof Node\Expr\Variable) {
                $variableName = null;

                if ($node->var->name instanceof Node\Name) {
                    $variableName = (string) $node->var->name;
                } elseif (is_string($node->var->name)) {
                    $variableName = $node->var->name;
                }

                if ($variableName && $variableName === $this->name) {
                    $this->bestMatch = $node;
                }
            }
        } elseif ($node instanceof Node\Stmt\Foreach_) {
            if ($node->valueVar->name === $this->name) {
                $this->bestMatch = $node;
            }
        }

        if ($node->getAttribute('startFilePos') <= $this->position &&
            $node->getAttribute('endFilePos') >= $this->position
        ) {
            if ($node instanceof Node\Stmt\ClassLike) {
                $this->currentClassName = (string) $node->name;

                $this->resetStateForNewScope();
            } elseif ($node instanceof Node\FunctionLike) {
                $variableIsOutsideCurrentScope = false;

                // If the variable is in a use() statement of a closure, we can't reset the state as we still need to
                // examine the parent scope of the closure where the variable is defined.
                if ($node instanceof Node\Expr\Closure) {
                    foreach ($node->uses as $closureUse) {
                        if ($closureUse->var === $this->name) {
                            $variableIsOutsideCurrentScope = true;
                            break;
                        }
                    }
                }

                if (!$variableIsOutsideCurrentScope) {
                    $this->resetStateForNewScope();
                    $this->lastFunctionLikeNode = $node;
                }
            }
        }
    }

    /**
     * @param Node $node
     */
    protected function parseNodeDocblock(Node $node)
    {
        $docblock = $node->getDocComment();

        $classRegexPart = "?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*";

        // Check for a type annotation in the style of /** @var FooType $someVar */ or /** @var $someVar FooType */.
        $regexTypeAnnotation = "/\/\*\*\s*@var\s+(({$classRegexPart}(?:\[\])?))\s+\\\${$this->name}\s*(\s.*)?\*\//";
        $reversRegexTypeAnnotation = "/\/\*\*\s*@var\s+\\\${$this->name}\s+(({$classRegexPart}(?:\[\])?))\s*(\s.*)?\*\//";

        if (preg_match($regexTypeAnnotation, $docblock, $matches) === 1) {
            $this->bestTypeOverrideMatch = $matches[1];
            $this->bestTypeOverrideMatchLine = $node->getLine();
        } elseif (preg_match($reversRegexTypeAnnotation, $docblock, $matches) === 1) {
            $this->bestTypeOverrideMatch = $matches[1];
            $this->bestTypeOverrideMatchLine = $node->getLine();
        }
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
     * @return void
     */
    protected function resetStateForNewScope()
    {
        $this->bestMatch = null;
        $this->bestTypeOverrideMatch = null;
        $this->bestTypeOverrideMatchLine = null;
    }

    /**
     * @var string[]
     */
    protected function getTypes()
    {
        if ($this->bestTypeOverrideMatch) {
            return $this->bestTypeOverrideMatch ? [$this->bestTypeOverrideMatch] : [];
        } elseif ($this->name === 'this') {
            return $this->currentClassName ? [$this->currentClassName] : [];
        } elseif ($this->bestMatch) {
            if ($this->bestMatch instanceof Node\Expr\Assign) {
                return $this->deduceTypesCommand->deduceTypesFromNode(
                    $this->file,
                    $this->code,
                    $this->bestMatch->expr,
                    $this->bestMatch->getAttribute('startFilePos')
                );
            } elseif ($this->bestMatch instanceof Node\Stmt\Foreach_) {
                $types = $this->deduceTypesCommand->deduceTypesFromNode(
                    $this->file,
                    $this->code,
                    $this->bestMatch->expr,
                    $this->bestMatch->getAttribute('startFilePos')
                );

                foreach ($types as $type) {
                    if ($type && mb_strpos($type, '[]') !== false) {
                        $type = mb_substr($type, 0, -2);

                        return $type ? [$type] : [];
                    }
                }
            } else {
                return $this->bestMatch ? [$this->bestMatch] : [];
            }
        } elseif ($this->lastFunctionLikeNode) {
            foreach ($this->lastFunctionLikeNode->getParams() as $param) {
                if ($param->name === $this->name) {
                    if ($param->type) {
                        // Found a type hint.
                        if ($param->type instanceof Node\Name) {
                            $type = $this->fetchClassName($param->type);

                            return $type ? [$type] : [];
                        }

                        return $param->type ? [$param->type] : [];
                    }

                    $docBlock = $this->lastFunctionLikeNode->getDocComment();

                    if (!$docBlock) {
                        break;
                    }

                    // Analyze the docblock's @param tags.
                    $docParser = new DocParser();

                    $name = null;

                    if ($this->lastFunctionLikeNode instanceof Node\Stmt\Function_ ||
                        $this->lastFunctionLikeNode instanceof Node\Stmt\ClassMethod
                    ) {
                        $name = $this->lastFunctionLikeNode->name;
                    }

                    $result = $docParser->parse((string) $docBlock, [
                        DocParser::PARAM_TYPE
                    ], $name, true);

                    if (isset($result['params']['$' . $this->name])) {
                        return $this->typeAnalyzer->getTypesForTypeSpecification(
                            $result['params']['$' . $this->name]['type']
                        );
                    }

                    break;
                }
            }
        }

        return [];
    }

    /**
     * @param string $file
     *
     * @return string[]
     */
    public function getResolvedTypes($file)
    {
        $resolvedTypes = [];

        $types = $this->getTypes();

        foreach ($types as $type) {
            if ($this->typeAnalyzer->isClassType($type) && $type[0] !== "\\") {
                $type = $this->resolveTypeCommand->resolveType(
                    $type,
                    $file,
                    $this->bestTypeOverrideMatchLine ?: $this->line
                );
            }

            $resolvedTypes[] = $type;
        }

        return $resolvedTypes;
    }
}
