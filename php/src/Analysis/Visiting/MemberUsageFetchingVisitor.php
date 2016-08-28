<?php

namespace PhpIntegrator\Analysis\Visiting;

use UnexpectedValueException;

use PhpIntegrator\Application\Command\ClassInfoCommand;
use PhpIntegrator\Application\Command\DeduceTypesCommand;
use PhpIntegrator\Application\Command\ResolveTypeCommand;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

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
     * @var int
     */
    const TYPE_EXPRESSION_NEW_MEMBER_WILL_BE_CREATED = 8;

    /**
     * @var array
     */
    protected $memberCallList = [];

    /**
     * @var Node|null
     */
    protected $lastNode = null;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var DeduceTypesCommand
     */
    protected $deduceTypesCommand;

    /**
     * @var ResolveTypeCommand
     */
    protected $resolveTypeCommand;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var ClassInfoCommand
     */
    protected $classInfoCommand;

    /**
     * @param DeduceTypesCommand $deduceTypesCommand
     * @param ClassInfoCommand   $classInfoCommand
     * @param ResolveTypeCommand $resolveTypeCommand
     * @param TypeAnalyzer       $typeAnalyzer
     * @param string             $file
     * @param string             $code
     */
    public function __construct(
        DeduceTypesCommand $deduceTypesCommand,
        ClassInfoCommand $classInfoCommand,
        ResolveTypeCommand $resolveTypeCommand,
        TypeAnalyzer $typeAnalyzer,
        $file,
        $code
    ) {
        $this->deduceTypesCommand = $deduceTypesCommand;
        $this->classInfoCommand = $classInfoCommand;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->resolveTypeCommand = $resolveTypeCommand;
        $this->file = $file;
        $this->code = $code;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        $previousNode = $this->lastNode;
        $this->lastNode = $node;

        if (!$node instanceof Node\Expr\MethodCall &&
            !$node instanceof Node\Expr\StaticCall &&
            !$node instanceof Node\Expr\PropertyFetch &&
            !$node instanceof Node\Expr\StaticPropertyFetch &&
            !$node instanceof Node\Expr\ClassConstFetch
        ) {
            return;
        }

        $objectTypes = [];
        $nodeToDeduceTypeFrom = null;

        if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\PropertyFetch) {
            $nodeToDeduceTypeFrom = $node->var;
        } elseif (
            $node instanceof Node\Expr\StaticCall ||
            $node instanceof Node\Expr\StaticPropertyFetch ||
            $node instanceof Node\Expr\ClassConstFetch
        ) {
            $nodeToDeduceTypeFrom = $node->class;
        }

        $objectTypes = $this->deduceTypesCommand->deduceTypesFromNode(
            $this->file,
            $this->code,
            $nodeToDeduceTypeFrom,
            $node->getAttribute('startFilePos')
        );

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
                    $classInfo = $this->classInfoCommand->getClassInfo($objectType);
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
                    if (!$this->isClassExcluded($objectType)) {
                        if ($previousNode instanceof Node\Expr\Assign ||
                            $previousNode instanceof Node\Expr\AssignOp ||
                            $previousNode instanceof Node\Expr\AssignRef
                        ) {
                            $this->memberCallList[] = [
                                'type'           => self::TYPE_EXPRESSION_NEW_MEMBER_WILL_BE_CREATED,
                                'memberName'     => is_string($node->name) ? $node->name : null,
                                'expressionType' => $objectType,
                                'start'          => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                                'end'            => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
                            ];
                        } else {
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
        }
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    protected function isClassExcluded($className)
    {
        $className = $this->typeAnalyzer->getNormalizedFqcn($className);

        return ($className === '\stdClass');
    }

    /**
     * @return array
     */
    public function getMemberCallList()
    {
        return $this->memberCallList;
    }
}
