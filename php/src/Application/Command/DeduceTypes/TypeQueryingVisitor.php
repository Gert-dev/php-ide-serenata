<?php

namespace PhpIntegrator\Application\Command\DeduceTypes;

use PhpIntegrator\DocParser;
use PhpIntegrator\NodeHelpers;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that walks to a specific position, building a list of information about variables and their possible and
 * guaranteed types.
 */
class TypeQueryingVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    const TYPE_CONDITIONALLY_GUARANTEED = 1;

    /**
     * @var int
     */
    const TYPE_CONDITIONALLY_POSSIBLE   = 2;

    /**
     * @var int
     */
    const TYPE_CONDITIONALLY_IMPOSSIBLE = 4;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var DocParser
     */
    protected $docParser;

    /**
     * @var array
     */
    protected $variableTypeInfoMap = [];

    /**
     * Constructor.
     *
     * @param DocParser $docParser
     * @param int       $position
     */
    public function __construct(DocParser $docParser, $position)
    {
        $this->docParser = $docParser;
        $this->position = $position;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        $startFilePos = $node->getAttribute('startFilePos');
        $endFilePos = $node->getAttribute('endFilePos');

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
            $this->setBestMatch($node->var, $node->type);
        } elseif (
            $node instanceof Node\Stmt\If_ ||
            $node instanceof Node\Stmt\ElseIf_ ||
            $node instanceof Node\Expr\Ternary
        ) {
            // There can be conditional expressions inside the current scope (think variables assigned to a ternary
            // expression). In that case we don't want to actually look at the condition for type deduction unless
            // we're inside the scope of that conditional.
            if ($this->position >= $startFilePos && $this->position <= $endFilePos) {
                $typeData = $this->parseCondition($node->cond);

                foreach ($typeData as $variable => $newConditionalTypes) {
                    $conditionalTypes = isset($this->variableTypeInfoMap[$variable]['conditionalTypes']) ?
                        $this->variableTypeInfoMap[$variable]['conditionalTypes'] :
                        [];

                    $this->variableTypeInfoMap[$variable]['conditionalTypes'] = array_merge($conditionalTypes, $newConditionalTypes);
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

                if ($variableName && $endFilePos <= $this->position) {
                    $this->setBestMatch($variableName, $node);
                }
            }
        } elseif ($node instanceof Node\Stmt\Foreach_) {
            if (!$node->valueVar instanceof Node\Expr\List_) {
                $this->setBestMatch($node->valueVar->name, $node);
            }
        }

        if ($startFilePos <= $this->position && $endFilePos >= $this->position) {
            if ($node instanceof Node\Stmt\ClassLike) {
                $this->resetStateForNewScope();

                $this->variableTypeInfoMap['this']['bestMatch'] = $node;
            } elseif ($node instanceof Node\FunctionLike) {
                $variablesOutsideCurrentScope = ['this'];

                // If a variable is in a use() statement of a closure, we can't reset the state as we still need to
                // examine the parent scope of the closure where the variable is defined.
                if ($node instanceof Node\Expr\Closure) {
                    foreach ($node->uses as $closureUse) {
                        $variablesOutsideCurrentScope[] = $closureUse->var;
                    }
                }

                $this->resetStateForNewScopeForAllBut($variablesOutsideCurrentScope);

                foreach ($node->getParams() as $param) {
                    $this->variableTypeInfoMap[$param->name]['bestMatch'] = $node;
                }
            }
        }
    }

    /**
     * @param Node\Expr $node
     *
     * @return array
     */
    protected function parseCondition(Node\Expr $node)
    {
        $types = [];

        if (
            $node instanceof Node\Expr\BinaryOp\BitwiseAnd ||
            $node instanceof Node\Expr\BinaryOp\BitwiseOr ||
            $node instanceof Node\Expr\BinaryOp\BitwiseXor ||
            $node instanceof Node\Expr\BinaryOp\BooleanAnd ||
            $node instanceof Node\Expr\BinaryOp\BooleanOr ||
            $node instanceof Node\Expr\BinaryOp\LogicalAnd ||
            $node instanceof Node\Expr\BinaryOp\LogicalOr ||
            $node instanceof Node\Expr\BinaryOp\LogicalXor
        ) {
            $leftTypes = $this->parseCondition($node->left);
            $rightTypes = $this->parseCondition($node->right);

            $types = $leftTypes;

            foreach ($rightTypes as $variable => $conditionalTypes) {
                foreach ($conditionalTypes as $conditionalType => $possibility) {
                    $types[$variable][$conditionalType] = $possibility;
                }
            }
        } elseif (
            $node instanceof Node\Expr\BinaryOp\Equal ||
            $node instanceof Node\Expr\BinaryOp\Identical
        ) {
            if ($node->left instanceof Node\Expr\Variable) {
                if ($node->right instanceof Node\Expr\ConstFetch && $node->right->name->toString() === 'null') {
                    $types[$node->left->name]['null'] = self::TYPE_CONDITIONALLY_GUARANTEED;
                }
            } elseif ($node->right instanceof Node\Expr\Variable) {
                if ($node->left instanceof Node\Expr\ConstFetch && $node->left->name->toString() === 'null') {
                    $types[$node->right->name]['null'] = self::TYPE_CONDITIONALLY_GUARANTEED;
                }
            }
        } elseif (
            $node instanceof Node\Expr\BinaryOp\NotEqual ||
            $node instanceof Node\Expr\BinaryOp\NotIdentical
        ) {
            if ($node->left instanceof Node\Expr\Variable) {
                if ($node->right instanceof Node\Expr\ConstFetch && $node->right->name->toString() === 'null') {
                    $types[$node->left->name]['null'] = self::TYPE_CONDITIONALLY_IMPOSSIBLE;
                }
            } elseif ($node->right instanceof Node\Expr\Variable) {
                if ($node->left instanceof Node\Expr\ConstFetch && $node->left->name->toString() === 'null') {
                    $types[$node->right->name]['null'] = self::TYPE_CONDITIONALLY_IMPOSSIBLE;
                }
            }
        } elseif ($node instanceof Node\Expr\BooleanNot) {
            if ($node->expr instanceof Node\Expr\Variable) {
                $types[$node->expr->name]['int']    = self::TYPE_CONDITIONALLY_POSSIBLE; // 0
                $types[$node->expr->name]['string'] = self::TYPE_CONDITIONALLY_POSSIBLE; // ''
                $types[$node->expr->name]['float']  = self::TYPE_CONDITIONALLY_POSSIBLE; // 0.0
                $types[$node->expr->name]['array']  = self::TYPE_CONDITIONALLY_POSSIBLE; // []
                $types[$node->expr->name]['null']   = self::TYPE_CONDITIONALLY_POSSIBLE; // null
            } else {
                $subTypes = $this->parseCondition($node->expr);

                // Reverse the possiblity of the types.
                foreach ($subTypes as $variable => $typeData) {
                    foreach ($typeData as $subType => $possibility) {
                        if ($possibility === self::TYPE_CONDITIONALLY_GUARANTEED) {
                            $types[$variable][$subType] = self::TYPE_CONDITIONALLY_IMPOSSIBLE;
                        } elseif ($possibility === self::TYPE_CONDITIONALLY_IMPOSSIBLE) {
                            $types[$variable][$subType] = self::TYPE_CONDITIONALLY_GUARANTEED;
                        } elseif ($possibility === self::TYPE_CONDITIONALLY_POSSIBLE) {
                            // Possible types are effectively negated and disappear.
                        }
                    }
                }
            }
        } elseif ($node instanceof Node\Expr\Variable) {
            $types[$node->name]['null'] = self::TYPE_CONDITIONALLY_IMPOSSIBLE;
        } elseif ($node instanceof Node\Expr\Instanceof_) {
            if ($node->expr instanceof Node\Expr\Variable) {
                if ($node->class instanceof Node\Name) {
                    $types[$node->expr->name][NodeHelpers::fetchClassName($node->class)] = self::TYPE_CONDITIONALLY_GUARANTEED;
                } else {
                    // This is an expression, we could fetch its return type, but that still won't tell us what
                    // the actual class is, so it's useless at the moment.
                }
            }
        } elseif ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Name) {
                $variableHandlingFunctionTypeMap = [
                    'is_array'    => ['array'],
                    'is_bool'     => ['bool'],
                    'is_callable' => ['callable'],
                    'is_double'   => ['float'],
                    'is_float'    => ['float'],
                    'is_int'      => ['int'],
                    'is_integer'  => ['int'],
                    'is_long'     => ['int'],
                    'is_null'     => ['null'],
                    'is_numeric'  => ['int', 'float', 'string'],
                    'is_object'   => ['object'],
                    'is_real'     => ['float'],
                    'is_resource' => ['resource'],
                    'is_scalar'   => ['int', 'float', 'string', 'bool'],
                    'is_string'   => ['string']
                ];

                if (isset($variableHandlingFunctionTypeMap[$node->name->toString()])) {
                    if (
                        !empty($node->args) &&
                        !$node->args[0]->unpack &&
                        $node->args[0]->value instanceof Node\Expr\Variable
                    ) {
                        $guaranteedTypes = $variableHandlingFunctionTypeMap[$node->name->toString()];

                        foreach ($guaranteedTypes as $guaranteedType) {
                            $types[$node->args[0]->value->name][$guaranteedType] = self::TYPE_CONDITIONALLY_GUARANTEED;
                        }
                    }
                }
            }
        }

        return $types;
    }

    /**
     * @param Node $node
     */
    protected function parseNodeDocblock(Node $node)
    {
        $docblock = $node->getDocComment();

        if (!$docblock) {
            return;
        }

        // Check for a reverse type annotation /** @var $someVar FooType */. These aren't correct in the sense that
        // they aren't consistent with the standard syntax "@var <type> <name>", but they are still used by some IDE's.
        // For this reason we support them, but only their most elementary form.
        $classRegexPart = "?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*";
        $reverseRegexTypeAnnotation = "/\/\*\*\s*@var\s+\\\$([A-Za-z0-9_])\s+(({$classRegexPart}(?:\[\])?))\s*(\s.*)?\*\//";

        if (preg_match($reverseRegexTypeAnnotation, $docblock, $matches) === 1) {
            $variable = $matches[1];

            $this->variableTypeInfoMap[$variable]['bestTypeOverrideMatch'] = $matches[2];
            $this->variableTypeInfoMap[$variable]['bestTypeOverrideMatchLine'] = $node->getLine();
        } else {
            $docblockData = $this->docParser->parse((string) $docblock, [
                DocParser::VAR_TYPE
            ], null);

            foreach ($docblockData['var'] as $variableName => $data) {
                if ($data['type']) {
                    $this->variableTypeInfoMap[mb_substr($variableName, 1)]['bestTypeOverrideMatch'] = $data['type'];
                    $this->variableTypeInfoMap[mb_substr($variableName, 1)]['bestTypeOverrideMatchLine'] = $node->getLine();
                }
            }
        }
    }

    /**
     * @param Node|null $bestMatch
     * @param string    $variable
     *
     * @return static
     */
    protected function setBestMatch($variable, Node $bestMatch = null)
    {
        $this->resetConditionalState($variable);

        $this->variableTypeInfoMap[$variable]['bestMatch'] = $bestMatch;

        return $this;
    }

    /**
     * @param string $variable
     */
    protected function resetConditionalState($variable)
    {
        $this->variableTypeInfoMap[$variable]['conditionalTypes'] = [];
    }

    /**
     * @return void
     */
    protected function resetStateForNewScope()
    {
        $this->variableTypeInfoMap = [];
    }

    /**
     * @param array $exclusionList
     */
    protected function resetStateForNewScopeForAllBut(array $exclusionList)
    {
        $newMap = [];

        foreach ($this->variableTypeInfoMap as $variable => $data) {
            if (in_array($variable, $exclusionList)) {
                $newMap[$variable] = $data;
            }
        }

        $this->variableTypeInfoMap = $newMap;
    }

    /**
     * @return array
     */
    public function getVariableTypeInfoMap()
    {
        return $this->variableTypeInfoMap;
    }
}
