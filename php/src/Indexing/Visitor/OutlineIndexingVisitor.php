<?php

namespace PhpIntegrator\Indexing\Visitor;

use PhpIntegrator\NodeHelpers;
use PhpIntegrator\TypeNormalizerInterface;

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
     * @var array
     */
    protected $globalDefines = [];

    /**
     * @var Node\Stmt\Class_|null
     */
    protected $currentStructure;

    /**
     * @var TypeNormalizerInterface
     */
    protected $typeNormalizer;

    /**
     * @var string
     */
    protected $code;

    /**
     * @param TypeNormalizerInterface $typeNormalizer
     * @param string                  $code
     */
    public function __construct(TypeNormalizerInterface $typeNormalizer, $code)
    {
        $this->typeNormalizer = $typeNormalizer;
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
        } elseif (
            $node instanceof Node\Expr\FuncCall &&
            $node->name instanceof Node\Name &&
            $node->name->toString() === 'define'
        ) {
            $this->parseDefineNode($node);
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
            $interfaces[] = NodeHelpers::fetchClassName($implementedName);
        }

        $fqcn = $this->typeNormalizer->getNormalizedFqcn($node->namespacedName->toString());

        $this->structures[$fqcn] = [
            'name'           => $node->name,
            'fqcn'           => $fqcn,
            'type'           => 'class',
            'startLine'      => $node->getLine(),
            'endLine'        => $node->getAttribute('endLine'),
            'startPosName'   => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos') : null,
            'endPosName'     => $node->getAttribute('startFilePos') ? ($node->getAttribute('startFilePos') + 1) : null,
            'isAbstract'     => $node->isAbstract(),
            'isFinal'        => $node->isFinal(),
            'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null,
            'parents'        => $node->extends ? [NodeHelpers::fetchClassName($node->extends)] : [],
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
            $extendedInterfaces[] = NodeHelpers::fetchClassName($extends);
        }

        $fqcn = $this->typeNormalizer->getNormalizedFqcn($node->namespacedName->toString());

        $this->structures[$fqcn] = [
            'name'           => $node->name,
            'fqcn'           => $fqcn,
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

        $fqcn = $this->typeNormalizer->getNormalizedFqcn($node->namespacedName->toString());

        $this->structures[$fqcn] = [
            'name'           => $node->name,
            'fqcn'           => $fqcn,
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

        $fqcn = $this->typeNormalizer->getNormalizedFqcn($this->currentStructure->namespacedName->toString());

        foreach ($node->traits as $traitName) {
            $this->structures[$fqcn]['traits'][] =
                NodeHelpers::fetchClassName($traitName);
        }

        foreach ($node->adaptations as $adaptation) {
            if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Alias) {
                $this->structures[$fqcn]['traitAliases'][] = [
                    'name'                       => $adaptation->method,
                    'alias'                      => $adaptation->newName,
                    'trait'                      => $adaptation->trait ? NodeHelpers::fetchClassName($adaptation->trait) : null,
                    'isPublic'                   => ($adaptation->newModifier === 1),
                    'isPrivate'                  => ($adaptation->newModifier === 4),
                    'isProtected'                => ($adaptation->newModifier === 2),
                    'isInheritingAccessModifier' => ($adaptation->newModifier === null)
                ];
            } elseif ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Precedence) {
                $this->structures[$fqcn]['traitPrecedences'][] = [
                    'name'  => $adaptation->method,
                    'trait' => NodeHelpers::fetchClassName($adaptation->trait)
                ];
            }
        }
    }

    /**
     * @param Node\Stmt\Property $node
     */
    protected function parseClassPropertyNode(Node\Stmt\Property $node)
    {
        $fqcn = $this->typeNormalizer->getNormalizedFqcn($this->currentStructure->namespacedName->toString());

        foreach ($node->props as $property) {
            $this->structures[$fqcn]['properties'][$property->name] = [
                'name'            => $property->name,
                'startLine'       => $property->getLine(),
                'endLine'         => $property->getAttribute('endLine'),
                'startPosName'    => $property->getAttribute('startFilePos') ? $property->getAttribute('startFilePos') : null,
                'endPosName'      => $property->getAttribute('startFilePos') ? ($property->getAttribute('startFilePos') + mb_strlen($property->name) + 1) : null,
                'isPublic'        => $node->isPublic(),
                'isPrivate'       => $node->isPrivate(),
                'isStatic'        => $node->isStatic(),
                'isProtected'     => $node->isProtected(),
                'docComment'      => $node->getDocComment() ? $node->getDocComment()->getText() : null,

                'defaultValue' => $property->default ?
                    substr(
                        $this->code,
                        $property->default->getAttribute('startFilePos'),
                        $property->default->getAttribute('endFilePos') - $property->default->getAttribute('startFilePos') + 1
                    ) :
                    null
            ];
        }
    }

    /**
     * @param Node\Stmt\Function_ $node
     */
    protected function parseFunctionNode(Node\Stmt\Function_ $node)
    {
        parent::enterNode($node);

        $fqcn = $this->typeNormalizer->getNormalizedFqcn(
            isset($node->namespacedName) ? $node->namespacedName->toString() : $node->name
        );

        $this->globalFunctions[$fqcn] = $this->extractFunctionLikeNodeData($node) + [
            'fqcn' => $fqcn
        ];
    }

    /**
     * @param Node\Stmt\ClassMethod $node
     */
    protected function parseClassMethodNode(Node\Stmt\ClassMethod $node)
    {
        $fqcn = $this->typeNormalizer->getNormalizedFqcn($this->currentStructure->namespacedName->toString());

        $this->structures[$fqcn]['methods'][$node->name] = $this->extractFunctionLikeNodeData($node) + [
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
                $localType = NodeHelpers::fetchClassName($param->type);
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
                    substr(
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
            $localType = NodeHelpers::fetchClassName($nodeType);
        } elseif ($nodeType) {
            $localType = (string) $nodeType;
        }

        parent::enterNode($node);

        foreach ($node->getParams() as $i => $param) {
            $resolvedType = null;

            if ($param->type instanceof Node\Name) {
                $resolvedType = NodeHelpers::fetchClassName($param->type);
            } elseif ($param->type) {
                $resolvedType = (string) $param->type;
            }

            $parameters[$i]['fullType'] = $resolvedType;
        }

        $resolvedType = null;
        $nodeType = $node->getReturnType();

        if ($nodeType instanceof Node\Name) {
            $resolvedType = NodeHelpers::fetchClassName($nodeType);
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
        $fqcn = $this->typeNormalizer->getNormalizedFqcn($this->currentStructure->namespacedName->toString());

        foreach ($node->consts as $const) {
            $this->structures[$fqcn]['constants'][$const->name] = [
                'name'           => $const->name,
                'startLine'      => $const->getLine(),
                'endLine'        => $const->getAttribute('endLine'),
                'startPosName'   => $const->getAttribute('startFilePos') ? $const->getAttribute('startFilePos') : null,
                'endPosName'     => $const->getAttribute('startFilePos') ? ($const->getAttribute('startFilePos') + mb_strlen($const->name)) : null,
                'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null,

                'defaultValue' => substr(
                    $this->code,
                    $const->value->getAttribute('startFilePos'),
                    $const->value->getAttribute('endFilePos') - $const->value->getAttribute('startFilePos') + 1
                )
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
            $fqcn = $this->typeNormalizer->getNormalizedFqcn(
                isset($const->namespacedName) ? $const->namespacedName->toString() : $const->name
            );

            $this->globalConstants[$fqcn] = [
                'name'           => $const->name,
                'fqcn'           => $fqcn,
                'startLine'      => $const->getLine(),
                'endLine'        => $const->getAttribute('endLine'),
                'startPosName'   => $const->getAttribute('startFilePos') ? $const->getAttribute('startFilePos') : null,
                'endPosName'     => $const->getAttribute('endFilePos') ? $const->getAttribute('endFilePos') : null,
                'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null,

                'defaultValue' => substr(
                    $this->code,
                    $const->value->getAttribute('startFilePos'),
                    $const->value->getAttribute('endFilePos') - $const->value->getAttribute('startFilePos') + 1
                )
            ];
        }
    }

    /**
     * @param Node\Expr\FuncCall $node
     */
    protected function parseDefineNode(Node\Expr\FuncCall $node)
    {
        parent::enterNode($node);

        if (count($node->args) < 2) {
            return;
        }

        $nameValue = $node->args[0]->value;

        if (!$nameValue instanceof Node\Scalar\String_) {
            return;
        }

        // Defines can be namespaced if their name contains slashes, see also
        // https://php.net/manual/en/function.define.php#90282
        $name = new Node\Name((string) $nameValue->value);

        $fqcn = $this->typeNormalizer->getNormalizedFqcn($name->toString());

        $this->globalDefines[$fqcn] = [
            'name'           => $name->getLast(),
            'fqcn'           => $fqcn,
            'startLine'      => $node->getLine(),
            'endLine'        => $node->getAttribute('endLine'),
            'startPosName'   => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos') : null,
            'endPosName'     => $node->getAttribute('endFilePos') ? $node->getAttribute('endFilePos') : null,
            'docComment'     => $node->getDocComment() ? $node->getDocComment()->getText() : null,

            'defaultValue' => substr(
                $this->code,
                $node->args[1]->getAttribute('startFilePos'),
                $node->args[1]->getAttribute('endFilePos') - $node->args[1]->value->getAttribute('startFilePos') + 1
            )
        ];
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

    /**
     * Retrieves the list of (global) defines.
     *
     * @return array
     */
    public function getGlobalDefines()
    {
        return $this->globalDefines;
    }
}
