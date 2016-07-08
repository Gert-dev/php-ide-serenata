<?php

namespace PhpIntegrator\Application\Command\SemanticLint\Visitor;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Application\Command\ClassInfo;
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
    protected $methodCallList = [];

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
     * @param TypeAnalyzer $typeAnalyzer
     * @param string       $file
     * @param string       $code
     */
    public function __construct(
        DeduceTypes $deduceTypes,
        ClassInfo $classInfo,
        TypeAnalyzer $typeAnalyzer,
        $file,
        $code
    ) {
        $this->deduceTypes = $deduceTypes;
        $this->classInfo = $classInfo;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->file = $file;
        $this->code = $code;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Expr\MethodCall) {
            $objectTypes = $this->deduceTypes->deduceTypesFromNode(
                $this->file,
                $this->code,
                $node->var,
                $node->getAttribute('startFilePos')
            );

            if (empty($objectTypes)) {
                $this->methodCallList[] = [
                    'type'       => self::TYPE_EXPRESSION_HAS_NO_TYPE,
                    'memberName' => is_string($node->name) ? $node->name : null,
                    'start'      => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                    'end'        => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
                ];

                // TODO: Issue an error "Invalid method call on an expression that has no type".

                return;
            }

            foreach ($objectTypes as $objectType) {
                if (!$this->typeAnalyzer->isClassType($objectType)) {
                    // TODO: Issue a warning "Can not guarantee that object type is a classlike, it may also be of type X, Y."

                    $this->methodCallList[] = [
                        'type'           => self::TYPE_EXPRESSION_IS_NOT_CLASSLIKE,
                        'memberName'     => is_string($node->name) ? $node->name : null,
                        'expressionType' => $objectType,
                        'start'          => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                        'end'            => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
                    ];


                } elseif (is_string($node->name)) {
                    $classInfo = $this->classInfo->getClassInfo($objectType);

                    if (!$classInfo || !isset($classInfo['methods'][$node->name])) {
                        // TODO: "No such method Z found for type X.".

                        $this->methodCallList[] = [
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

    /**
     * Retrieves the class usage list.
     *
     * @return array
     */
    public function getMethodCallList()
    {
        return $this->methodCallList;
    }
}
