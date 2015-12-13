<?php

namespace PhpIntegrator;

use PhpParser\Node;

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
    protected $structuralElements = [];

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
    protected $currentStructuralElement;

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Node\Stmt\Class_) {
            $this->parseClassNode($node);
        } elseif ($node instanceof Node\Stmt\Interface_) {
            $this->parseInterfaceNode($node);
        } elseif ($node instanceof Node\Stmt\Trait_) {
            $this->parseTraitNode($node);
        } elseif ($node instanceof Node\Stmt\TraitUse) {
            $this->parseTraitUseNode($node);
        } elseif ($node instanceof Node\Stmt\Property) {
            $this->parseClassPropertyNode($node);
        } elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->parseClassMethodNode($node);
        } elseif ($node instanceof Node\Stmt\ClassConst) {
            $this->parseClassConstantNode($node);
        } elseif ($node instanceof Node\Stmt\Function_) {
            $this->parseFunctionNode($node);
        } elseif ($node instanceof Node\Stmt\Const_) {
            $this->parseConstantNode($node);
        }
    }

    /**
     * @param Node\Stmt\Class_ $node
     */
    protected function parseClassNode(Node\Stmt\Class_ $node)
    {
        $this->currentStructuralElement = $node;

        $interfaces = [];

        /** @var Node\Name $implementedName */
        foreach ($node->implements as $implementedName) {
            $interfaces[] = $implementedName->toString();
        }

        $this->structuralElements[$node->namespacedName->toString()] = [
            'name'       => $node->name,
            'type'       => 'class',
            'startLine'  => $node->getLine(),
            'isAbstract' => $node->isAbstract(),
            'docComment' => $node->getDocComment() ? $node->getDocComment()->getText() : null,
            'parent'     => $node->extends ? $node->extends->toString() : null,
            'interfaces' => $interfaces,
            'traits'     => [],
            'docComment' => $node->getDocComment() ? $node->getDocComment()->getReformattedText() : null,
            'methods'    => [],
            'properties' => [],
            'constants'  => []
        ];
    }

    /**
     * @param Node\Stmt\Interface_ $node
     */
    protected function parseInterfaceNode(Node\Stmt\Interface_ $node)
    {
        $this->currentStructuralElement = $node;

        $this->structuralElements[$node->namespacedName->toString()] = [
            'name'       => $node->name,
            'type'       => 'interface',
            'startLine'  => $node->getLine(),
            'parent'     => $node->extends ? $node->extends->toString() : null,
            'docComment' => $node->getDocComment() ? $node->getDocComment()->getText() : null,
            'traits'     => [],
            'methods'    => [],
            'properties' => [],
            'constants'  => []
        ];
    }

    /**
     * @param Node\Stmt\Trait_ $node
     */
    protected function parseTraitNode(Node\Stmt\Trait_ $node)
    {
        $this->currentStructuralElement = $node;

        $this->structuralElements[$node->namespacedName->toString()] = [
            'name'       => $node->name,
            'type'       => 'trait',
            'startLine'  => $node->getLine(),
            'docComment' => $node->getDocComment() ? $node->getDocComment()->getText() : null,
            'methods'    => [],
            'properties' => [],
            'constants'  => []
        ];
    }

    /**
     * @param Node\Stmt\TraitUse $node
     */
    protected function parseTraitUseNode(Node\Stmt\TraitUse $node)
    {
        /** @var Node\Name $traitName */
        foreach ($node->traits as $traitName) {
            $this->structuralElements[$this->currentStructuralElement->namespacedName->toString()]['traits'][] =
                $traitName->toString();
        }
    }

    /**
     * @param Node\Stmt\Property $node
     */
    protected function parseClassPropertyNode(Node\Stmt\Property $node)
    {
        foreach ($node->props as $property) {
            $this->structuralElements[$this->currentStructuralElement->namespacedName->toString()]['properties'][] = [
                'name'        => $property->name,
                'startLine'   => $node->getLine(),
                'isPublic'    => $node->isPublic(),
                'isPrivate'   => $node->isPrivate(),
                'isStatic'    => $node->isStatic(),
                'isProtected' => $node->isProtected(),
                'docComment'  => $node->getDocComment() ? $node->getDocComment()->getText() : null
            ];
        }
    }

    /**
     * @param Node\Stmt\Function_ $node
     */
    protected function parseFunctionNode(Node\Stmt\Function_ $node)
    {
        $parameters = [];

        /** @var \PhpParser\Node\Param $param */
        foreach ($node->params as $param) {
            $parameters[] = [
                'name'       => $param->name,
                'type'       => $param->type,
                'isVariadic' => $param->variadic,
                'isOptional' => $param->default ? true : false
            ];
        }

        $this->globalFunctions[] = [
            'name'        => $node->name,
            'startLine'   => $node->getLine(),
            'returnType'  => $node->getReturnType(),
            'parameters'  => $parameters,
            'docComment'  => $node->getDocComment() ? $node->getDocComment()->getText() : null
        ];
    }

    /**
     * @param Node\Stmt\ClassMethod $node
     */
    protected function parseClassMethodNode(Node\Stmt\ClassMethod $node)
    {
        $parameters = [];

        /** @var \PhpParser\Node\Param $param */
        foreach ($node->params as $param) {
            $parameters[] = [
                'name'       => $param->name,
                'type'       => $param->type,
                'isVariadic' => $param->variadic,
                'isOptional' => $param->default ? true : false
            ];
        }

        $this->structuralElements[$this->currentStructuralElement->namespacedName->toString()]['methods'][] = [
            'name'        => $node->name,
            'startLine'   => $node->getLine(),
            'isPublic'    => $node->isPublic(),
            'isPrivate'   => $node->isPrivate(),
            'isProtected' => $node->isProtected(),
            'isStatic'    => $node->isStatic(),
            'returnType'  => $node->getReturnType(),
            'parameters'  => $parameters,
            'docComment'  => $node->getDocComment() ? $node->getDocComment()->getText() : null
        ];
    }

    /**
     * @param Node\Stmt\ClassConst $node
     */
    protected function parseClassConstantNode(Node\Stmt\ClassConst $node)
    {
        foreach ($node->consts as $const) {
            $this->structuralElements[$this->currentStructuralElement->namespacedName->toString()]['constants'][] = [
                'name'       => $const->name,
                'startLine'  => $node->getLine(),
                'docComment' => $node->getDocComment() ? $node->getDocComment()->getText() : null
            ];
        }
    }

    /**
     * @param Node\Stmt\Const_ $node
     */
    protected function parseConstantNode(Node\Stmt\Const_ $node)
    {
        foreach ($node->consts as $const) {
            $this->globalConstants[] = [
                'name'       => $const->name,
                'startLine'  => $node->getLine(),
                'docComment' => $node->getDocComment() ? $node->getDocComment()->getText() : null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function leaveNode(Node $node)
    {
        if ($this->currentStructuralElement === $node) {
            $this->currentStructuralElement = null;
        }
    }

    /**
     * Retrieves the list of structural elements.
     *
     * @return array
     */
    public function getStructuralElements()
    {
        return $this->structuralElements;
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
