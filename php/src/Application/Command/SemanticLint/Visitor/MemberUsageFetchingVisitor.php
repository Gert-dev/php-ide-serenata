<?php

namespace PhpIntegrator\Application\Command\SemanticLint\Visitor;

use UnexpectedValueException;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Application\Command\ClassInfo;
use PhpIntegrator\Application\Command\ResolveType;
use PhpIntegrator\Application\Command\DeduceTypes;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that fetches usages of member names.
 */
class MemberUsageFetchingVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    const TYPE_EXPRESSION_HAS_NO_TYPE = 1;

    /**
     * @var int
     */
    const TYPE_EXPRESSION_IS_NOT_CLASSLIKE = 2;

    /**
     * @var int
     */
    const TYPE_EXPRESSION_HAS_NO_SUCH_MEMBER = 4;

    /**
     * @var array
     */
    protected $memberCallList = [];

    /**
     * @var string
     */
    protected $file;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var DeduceTypes
     */
    protected $deduceTypes;

    /**
     * @var ResolveType
     */
    protected $resolveType;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var ClassInfo
     */
    protected $classInfo;

    /**
     * @param DeduceTypes  $deduceTypes
     * @param ClassInfo    $classInfo
     * @param ResolveType  $resolveType
     * @param TypeAnalyzer $typeAnalyzer
     * @param string       $file
     * @param string       $code
     */
    public function __construct(
        DeduceTypes $deduceTypes,
        ClassInfo $classInfo,
        ResolveType $resolveType,
        TypeAnalyzer $typeAnalyzer,
        $file,
        $code
    ) {
        $this->deduceTypes = $deduceTypes;
        $this->classInfo = $classInfo;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->resolveType = $resolveType;
        $this->file = $file;
        $this->code = $code;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if (!$node instanceof Node\Expr\MethodCall &&
            !$node instanceof Node\Expr\StaticCall &&
            !$node instanceof Node\Expr\PropertyFetch &&
            !$node instanceof Node\Expr\StaticPropertyFetch &&
            !$node instanceof Node\Expr\ClassConstFetch
        ) {
            return;
        }

        $objectTypes = [];

        if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\PropertyFetch) {
            $objectTypes = $this->deduceTypes->deduceTypesFromNode(
                $this->file,
                $this->code,
                $node->var,
                $node->getAttribute('startFilePos')
            );
        } elseif (
            $node instanceof Node\Expr\StaticCall ||
            $node instanceof Node\Expr\StaticPropertyFetch ||
            $node instanceof Node\Expr\ClassConstFetch
        ) {
            $className = (string) $node->class;

            if ($this->typeAnalyzer->isClassType($className)) {
                $className = $this->resolveType->resolveType($className, $this->file, $node->getAttribute('startLine'));
            }

            $objectTypes = [$className];
        }

        if (empty($objectTypes)) {
            $this->memberCallList[] = [
                'type'       => self::TYPE_EXPRESSION_HAS_NO_TYPE,
                'memberName' => is_string($node->name) ? $node->name : null,
                'start'      => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                'end'        => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
            ];

            return;
        }

        foreach ($objectTypes as $objectType) {
            if (!$this->typeAnalyzer->isClassType($objectType)) {
                $this->memberCallList[] = [
                    'type'           => self::TYPE_EXPRESSION_IS_NOT_CLASSLIKE,
                    'memberName'     => is_string($node->name) ? $node->name : null,
                    'expressionType' => $objectType,
                    'start'          => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                    'end'            => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
                ];


            } elseif (is_string($node->name)) {
                $classInfo = null;

                try {
                    $classInfo = $this->classInfo->getClassInfo($objectType);
                } catch (UnexpectedValueException $e) {
                    // Ignore exception, no class information means we return an error anyhow.
                }

                $key = null;

                if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\StaticCall) {
                    $key = 'methods';
                } elseif ($node instanceof Node\Expr\PropertyFetch || $node instanceof Node\Expr\StaticPropertyFetch) {
                    $key = 'properties';
                } elseif ($node instanceof Node\Expr\ClassConstFetch) {
                    $key = 'constants';
                }

                if (!$classInfo || !isset($classInfo[$key][$node->name])) {
                    $this->memberCallList[] = [
                        'type'           => self::TYPE_EXPRESSION_HAS_NO_SUCH_MEMBER,
                        'memberName'     => is_string($node->name) ? $node->name : null,
                        'expressionType' => $objectType,
                        'start'          => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                        'end'            => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
                    ];
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getMemberCallList()
    {
        return $this->memberCallList;
    }
}
