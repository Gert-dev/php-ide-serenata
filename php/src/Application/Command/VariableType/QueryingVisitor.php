<?php

namespace PhpIntegrator\Application\Command\VariableType;

use PhpIntegrator\DocParser;

use PhpIntegrator\Application\Command\DeduceType;
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
     * @var DeduceType
     */
    protected $deduceTypeCommand;

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
     * @param string      $file
     * @param string      $code
     * @param int         $position
     * @param int         $line
     * @param string      $name
     * @param ResolveType $resolveTypeCommand
     * @param DeduceType  $deduceTypeCommand
     */
    public function __construct($file, $code, $position, $line, $name, ResolveType $resolveTypeCommand, DeduceType $deduceTypeCommand)
    {
        $this->name = $name;
        $this->line = $line;
        $this->file = $file;
        $this->code = $code;
        $this->position = $position;
        $this->deduceTypeCommand = $deduceTypeCommand;
        $this->resolveTypeCommand = $resolveTypeCommand;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node->getAttribute('startFilePos') >= $this->position) {
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
                $this->resetStateForNewScope();

                $this->lastFunctionLikeNode = $node;
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
                return ['new ' . (string) $node->class];
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
                return [$node->name->toString(), $node->name . '()'];
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
        }

        return null;
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
     * @var string|null
     */
    public function getType()
    {
        if ($this->bestTypeOverrideMatch) {
            return $this->bestTypeOverrideMatch;
        } elseif ($this->name === 'this') {
            return $this->currentClassName;
        } elseif ($this->bestMatch) {
            if ($this->bestMatch instanceof Node\Expr\Assign) {
                $expressionParts = $this->convertExpressionToStringParts($this->bestMatch->expr);

                if ($expressionParts) {
                    // The position + 1 ensures that this node is also taken up in the scan for its type, causing
                    // its docblock (which could contain a type annotation override) to also be examined.
                    return $this->deduceTypeCommand->deduceType(
                        $this->file,
                        $this->code,
                        $expressionParts,
                        $this->bestMatch->getAttribute('startFilePos') + 1
                    );
                }
            } elseif ($this->bestMatch instanceof Node\Stmt\Foreach_) {
                $expressionParts = $this->convertExpressionToStringParts($this->bestMatch->expr);

                if ($expressionParts) {
                    $listType = $this->deduceTypeCommand->deduceType(
                        $this->file,
                        $this->code,
                        $expressionParts,
                        $this->bestMatch->getAttribute('startFilePos') + 1 // Same as above.
                    );

                    if ($listType && mb_strpos($listType, '[]') !== false) {
                        return mb_substr($listType, 0, -2);
                    }
                }
            } else {
                return $this->bestMatch;
            }
        } elseif ($this->lastFunctionLikeNode) {
            foreach ($this->lastFunctionLikeNode->getParams() as $param) {
                if ($param->name === $this->name) {
                    if ($param->type) {
                        // Found a type hint.
                        if ($param->type instanceof Node\Name) {
                            return $this->fetchClassName($param->type);
                        }

                        return $param->type;
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
                        return $result['params']['$' . $this->name]['type'];
                    }

                    break;
                }
            }
        }

        return null;
    }

    /**
     * @param string $file
     *
     * @return string|null
     */
    public function getResolvedType($file)
    {
        $type = $this->getType();

        if ($type && $type[0] !== '\\') {
            return $this->resolveTypeCommand->resolveType(
                $type,
                $file,
                $this->bestTypeOverrideMatchLine ?: $this->line
            );
        }

        return $type;
    }
}
