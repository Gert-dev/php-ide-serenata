<?php

namespace PhpIntegrator\Application\Command\VariableType;

use PhpIntegrator\DocParser;

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
     * @var ResolveType
     */
    protected $resolveTypeCommand;

    /**
     * Constructor.
     *
     * @param int         $position
     * @param int         $line
     * @param string      $name
     * @param ResolveType $resolveTypeCommand
     */
    public function __construct($position, $line, $name, ResolveType $resolveTypeCommand)
    {
        $this->name = $name;
        $this->line = $line;
        $this->position = $position;
        $this->resolveTypeCommand = $resolveTypeCommand;
    }



    protected $currentClassName = null;
    protected $bestMatch = null;
    protected $bestTypeOverrideMatch = null;
    protected $bestTypeOverrideMatchLine = null;

    /**
     * @var Node\FunctionLike|null
     */
    protected $lastFunctionLikeNode = null;


    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node->getAttribute('startFilePos') >= $this->position) {
            // We've gone beyond the requested position, there is nothing here that can still be relevant anymore.
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        // TODO: Don't actually fetch any types or resolve call stacks yet, as we usually need the last interesting
        // assignment to the variable, and not the first. This will save us a lot of expensive determinations that will
        // be discarded anyway.

        $this->parseNodeDocblock($node);

        // TODO: If we meet the variable name being assigned to something else, we'll have to parse the expression
        // to see what it is.

        if ($node instanceof Node\Stmt\Catch_) {
            if ($node->var === $this->name) {
                $this->bestMatch = $this->fetchFqcn($node->type);
            }
        } elseif ($node instanceof Node\Stmt\If_ || $node instanceof Node\Stmt\ElseIf_) {
            if ($node->cond instanceof Node\Expr\Instanceof_) {
                if ($node->cond->expr instanceof Node\Expr\Variable && $node->cond->expr->name === $this->name) {

                    if ($node->cond->class instanceof Node\Name) {
                        $this->bestMatch = $this->fetchFqcn($node->cond->class);
                    } else {
                        // TODO: This is an expression, parse it to retrieve its return value.
                    }
                }
            }
        }

        // TODO: Parse foreach, examine the first argument, determine its type, if its an array (e.g. "Foo[]"), we know
        // the type of the foreach variable is a "Foo".

        // TODO: Tests!

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

    protected function fetchFqcn(Node\Name $name)
    {
        $newName = (string) $name;

        if ($name->isFullyQualified() && $newName[0] !== '\\') {
            $newName = '\\' . $newName;
        }

        return $newName;
    }

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
        } elseif ($this->lastFunctionLikeNode) {
            foreach ($this->lastFunctionLikeNode->getParams() as $param) {
                if ($param->name === $this->name) {
                    if ($param->type) {
                        // Found a type hint.
                        if ($param->type instanceof Node\Name) {
                            return $this->fetchFqcn($param->type);
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

        return $this->bestMatch;
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
