<?php

namespace PhpIntegrator\Indexing\Visitor;

use PhpParser\Node;
use PhpParser\NodeTraverser;

use PhpParser\NodeVisitor\NameResolver;

/**
 * Node visitor that indexes the outline of a file, creating a list of structural elements (classes, interfaces, ...)
 * with their direct methods, properties, constants, and so on.
 */
class OutlineIndexingVisitor extends NameResolver
{
    /**
     * @var array
     */
    protected $structures = [];

    /**
     * @var array
     */
    protected $globalFunctions = [];

    /**
     * @var array
     */
    protected $globalConstants = [];

    /**
     * @var Node\Stmt\Class_|null
     */
    protected $currentStructure;

    /**
     * @var string
     */
    protected $code;

    /**
     * @param string $code
     */
    public function __construct($code)
    {
        $this->code = $code;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Property) {
            $this->parseClassPropertyNode($node);
        } elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->parseClassMethodNode($node);
        } elseif ($node instanceof Node\Stmt\ClassConst) {
            $this->parseClassConstantNode($node);
        } elseif ($node instanceof Node\Stmt\Function_) {
            $this->parseFunctionNode($node);
        } elseif ($node instanceof Node\Stmt\Const_) {
            $this->parseConstantNode($node);
        } elseif ($node instanceof Node\Stmt\Class_) {
            if ($node->isAnonymous()) {
                // Ticket #45 - Skip PHP 7 anonymous classes.
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            $this->parseClassNode($node);
        } elseif ($node instanceof Node\Stmt\Interface_) {
            $this->parseInterfaceNode($node);
        } elseif ($node instanceof Node\Stmt\Trait_) {
            $this->parseTraitNode($node);
        } elseif ($node instanceof Node\Stmt\TraitUse) {
            $this->parseTraitUseNode($node);
        } else {
            // Resolve the names for other nodes as the nodes we need depend on them being resolved.
            parent::enterNode($node);
        }
    }

    /**
     * @param Node\Stmt\Class_ $node
     */
    protected function parseClassNode(Node\Stmt\Class_ $node)
    {
        parent::enterNode($node);

        $this->currentStructure = $node;

        $interfaces = [];

        foreach ($node->implements as $implementedName) {
            $interfaces[] = $this->fetchClassName($implementedName);
        }

        $this->structures[$node->namespacedName->toString()] = [
            'name'           => $node->name,
            'fqcn'           => $node->namespacedName->toString(),
            'type'           => 'class',
            'startLine'      => $node->getLine(),
            'endLine'        => $node->getAttribute('endLine'),
            'startPosName'   => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos') : null,
            'endPosName'     => $node->getAttribute('startFilePos') ? ($node->getAttribute('startFilePos') + 1) : null,
            'isAbstract'     => $node->isAbstract(),
            'isFinal'        => $node->isFinal(),
            'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null,
            'parents'        => $node->extends ? [$this->fetchClassName($node->extends)] : [],
            'interfaces'     => $interfaces,
            'traits'         => [],
            'methods'        => [],
            'properties'     => [],
            'constants'      => []
        ];
    }

    /**
     * @param Node\Stmt\Interface_ $node
     */
    protected function parseInterfaceNode(Node\Stmt\Interface_ $node)
    {
        parent::enterNode($node);

        if (!isset($node->namespacedName)) {
            return;
        }

        $this->currentStructure = $node;

        $extendedInterfaces = [];

        foreach ($node->extends as $extends) {
            $extendedInterfaces[] = $this->fetchClassName($extends);
        }

        $this->structures[$node->namespacedName->toString()] = [
            'name'           => $node->name,
            'fqcn'           => $node->namespacedName->toString(),
            'type'           => 'interface',
            'startLine'      => $node->getLine(),
            'endLine'        => $node->getAttribute('endLine'),
            'startPosName'   => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos') : null,
            'endPosName'     => $node->getAttribute('startFilePos') ? ($node->getAttribute('startFilePos') + 1) : null,
            'parents'        => $extendedInterfaces,
            'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null,
            'traits'         => [],
            'methods'        => [],
            'properties'     => [],
            'constants'      => []
        ];
    }

    /**
     * @param Node\Stmt\Trait_ $node
     */
    protected function parseTraitNode(Node\Stmt\Trait_ $node)
    {
        parent::enterNode($node);

        if (!isset($node->namespacedName)) {
            return;
        }

        $this->currentStructure = $node;

        $this->structures[$node->namespacedName->toString()] = [
            'name'           => $node->name,
            'fqcn'           => $node->namespacedName->toString(),
            'type'           => 'trait',
            'startLine'      => $node->getLine(),
            'endLine'        => $node->getAttribute('endLine'),
            'startPosName'   => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos') : null,
            'endPosName'     => $node->getAttribute('startFilePos') ? ($node->getAttribute('startFilePos') + 1) : null,
            'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null,
            'methods'        => [],
            'properties'     => [],
            'constants'      => []
        ];
    }

    /**
     * @param Node\Stmt\TraitUse $node
     */
    protected function parseTraitUseNode(Node\Stmt\TraitUse $node)
    {
        parent::enterNode($node);

        foreach ($node->traits as $traitName) {
            $this->structures[$this->currentStructure->namespacedName->toString()]['traits'][] =
                $this->fetchClassName($traitName);
        }

        foreach ($node->adaptations as $adaptation) {
            if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Alias) {
                $this->structures[$this->currentStructure->namespacedName->toString()]['traitAliases'][] = [
                    'name'                       => $adaptation->method,
                    'alias'                      => $adaptation->newName,
                    'trait'                      => $adaptation->trait ? $this->fetchClassName($adaptation->trait) : null,
                    'isPublic'                   => ($adaptation->newModifier === 1),
                    'isPrivate'                  => ($adaptation->newModifier === 4),
                    'isProtected'                => ($adaptation->newModifier === 2),
                    'isInheritingAccessModifier' => ($adaptation->newModifier === null)
                ];
            } elseif ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Precedence) {
                $this->structures[$this->currentStructure->namespacedName->toString()]['traitPrecedences'][] = [
                    'name'  => $adaptation->method,
                    'trait' => $this->fetchClassName($adaptation->trait)
                ];
            }
        }
    }

    /**
     * @param Node\Stmt\Property $node
     */
    protected function parseClassPropertyNode(Node\Stmt\Property $node)
    {
        foreach ($node->props as $property) {
            $this->structures[$this->currentStructure->namespacedName->toString()]['properties'][$property->name] = [
                'name'            => $property->name,
                'startLine'       => $property->getLine(),
                'endLine'         => $property->getAttribute('endLine'),
                'startPosName'    => $property->getAttribute('startFilePos') ? $property->getAttribute('startFilePos') : null,
                'endPosName'      => $property->getAttribute('startFilePos') ? ($property->getAttribute('startFilePos') + mb_strlen($property->name) + 1) : null,
                'isPublic'        => $node->isPublic(),
                'isPrivate'       => $node->isPrivate(),
                'isStatic'        => $node->isStatic(),
                'isProtected'     => $node->isProtected(),
                'docComment'      => $node->getDocComment() ? $node->getDocComment()->getText() : null
            ];
        }
    }

    /**
     * @param Node\Stmt\Function_ $node
     */
    protected function parseFunctionNode(Node\Stmt\Function_ $node)
    {
        parent::enterNode($node);

        $this->globalFunctions[$node->name] = $this->extractFunctionLikeNodeData($node) + [
            'fqcn' => isset($node->namespacedName) ? $node->namespacedName->toString() : $node->name
        ];
    }

    /**
     * @param Node\Stmt\ClassMethod $node
     */
    protected function parseClassMethodNode(Node\Stmt\ClassMethod $node)
    {
        $this->structures[$this->currentStructure->namespacedName->toString()]['methods'][$node->name] = $this->extractFunctionLikeNodeData($node) + [
            'isPublic'       => $node->isPublic(),
            'isPrivate'      => $node->isPrivate(),
            'isProtected'    => $node->isProtected(),
            'isAbstract'     => $node->isAbstract(),
            'isFinal'        => $node->isFinal(),
            'isStatic'       => $node->isStatic()
        ];
    }

    /**
     * @param Node\FunctionLike $node
     */
    protected function extractFunctionLikeNodeData(Node\FunctionLike $node)
    {
        $parameters = [];

        foreach ($node->getParams() as $i => $param) {
            $localType = null;

            if ($param->type instanceof Node\Name) {
                $localType = $this->fetchClassName($param->type);
            } elseif ($param->type) {
                $localType = (string) $param->type;
            }

            $parameters[$i] = [
                'name'         => $param->name,
                'type'         => $localType,
                'fullType'     => null, // Filled in below.
                'isReference'  => $param->byRef,
                'isVariadic'   => $param->variadic,
                'isOptional'   => $param->default ? true : false,

                'isNullable'   => (
                    $param->default instanceof Node\Expr\ConstFetch && $param->default->name->toString() === 'null'
                ),

                'defaultValue' => $param->default ?
                    mb_substr(
                        $this->code,
                        $param->default->getAttribute('startFilePos'),
                        $param->default->getAttribute('endFilePos') - $param->default->getAttribute('startFilePos') + 1
                    ) :
                    null
            ];
        }

        $localType = null;
        $nodeType = $node->getReturnType();

        if ($nodeType instanceof Node\Name) {
            $localType = $this->fetchClassName($nodeType);
        } elseif ($nodeType) {
            $localType = (string) $nodeType;
        }

        parent::enterNode($node);

        foreach ($node->getParams() as $i => $param) {
            $resolvedType = null;

            if ($param->type instanceof Node\Name) {
                $resolvedType = $this->fetchClassName($param->type);
            } elseif ($param->type) {
                $resolvedType = (string) $param->type;
            }

            $parameters[$i]['fullType'] = $resolvedType;
        }

        $resolvedType = null;
        $nodeType = $node->getReturnType();

        if ($nodeType instanceof Node\Name) {
            $resolvedType = $this->fetchClassName($nodeType);
        } elseif ($nodeType) {
            $resolvedType = (string) $nodeType;
        }

        return [
            'name'           => $node->name,
            'startLine'      => $node->getLine(),
            'endLine'        => $node->getAttribute('endLine'),
            'startPosName'   => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos') : null,
            'endPosName'     => $node->getAttribute('startFilePos') ? ($node->getAttribute('startFilePos') + 1) : null,
            'returnType'     => $localType,
            'fullReturnType' => $resolvedType,
            'parameters'     => $parameters,
            'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null
        ];
    }

    /**
     * @param Node\Stmt\ClassConst $node
     */
    protected function parseClassConstantNode(Node\Stmt\ClassConst $node)
    {
        foreach ($node->consts as $const) {
            $this->structures[$this->currentStructure->namespacedName->toString()]['constants'][$const->name] = [
                'name'           => $const->name,
                'startLine'      => $const->getLine(),
                'endLine'        => $const->getAttribute('endLine'),
                'startPosName'   => $const->getAttribute('startFilePos') ? $const->getAttribute('startFilePos') : null,
                'endPosName'     => $const->getAttribute('startFilePos') ? ($const->getAttribute('startFilePos') + mb_strlen($const->name)) : null,
                'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null
            ];
        }
    }

    /**
     * @param Node\Stmt\Const_ $node
     */
    protected function parseConstantNode(Node\Stmt\Const_ $node)
    {
        parent::enterNode($node);

        foreach ($node->consts as $const) {
            $this->globalConstants[$const->name] = [
                'name'           => $const->name,
                'fqcn'          => isset($const->namespacedName) ? $const->namespacedName->toString() : $const->name,
                'startLine'      => $const->getLine(),
                'endLine'        => $const->getAttribute('endLine'),
                'startPosName'   => $const->getAttribute('startFilePos') ? $const->getAttribute('startFilePos') : null,
                'endPosName'     => $const->getAttribute('endFilePos') ? $const->getAttribute('endFilePos') : null,
                'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
        if ($this->currentStructure === $node) {
            $this->currentStructure = null;
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
     * Retrieves the list of structural elements.
     *
     * @return array
     */
    public function getStructures()
    {
        return $this->structures;
    }

    /**
     * Retrieves the list of (global) functions.
     *
     * @return array
     */
    public function getGlobalFunctions()
    {
        return $this->globalFunctions;
    }

    /**
     * Retrieves the list of (global) constants.
     *
     * @return array
     */
    public function getGlobalConstants()
    {
        return $this->globalConstants;
    }
}
