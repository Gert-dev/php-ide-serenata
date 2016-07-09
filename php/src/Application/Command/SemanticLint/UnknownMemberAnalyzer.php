<?php

namespace PhpIntegrator\Application\Command\SemanticLint;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Application\Command\ClassInfo;
use PhpIntegrator\Application\Command\ResolveType;
use PhpIntegrator\Application\Command\DeduceTypes;

/**
 * Looks for unknown member names.
 */
class UnknownMemberAnalyzer implements AnalyzerInterface
{
    /**
     * @var Visitor\MethodUsageFetchingVisitor
     */
    protected $methodUsageFetchingVisitor;

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
        $this->methodUsageFetchingVisitor = new Visitor\MemberUsageFetchingVisitor(
            $deduceTypes,
            $classInfo,
            $resolveType,
            $typeAnalyzer,
            $file,
            $code
        );
    }

    /**
     * @inheritDoc
     */
    public function getVisitors()
    {
        return [
            $this->methodUsageFetchingVisitor
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        $output = [
            'errors' => [
                'expressionHasNoType'              => [],
                'expressionIsNotClasslike'         => [],
                'expressionHasNoSuchMember'        => [],
            ],

            'warnings' => [
                'expressionNewMemberWillBeCreated' => []
            ]
        ];

        $memberCallList = $this->methodUsageFetchingVisitor->getMemberCallList();

        foreach ($memberCallList as $memberCall) {
            $type = $memberCall['type'];

            unset ($memberCall['type']);

            if ($type === Visitor\MemberUsageFetchingVisitor::TYPE_EXPRESSION_HAS_NO_TYPE) {
                $output['errors']['expressionHasNoType'][] = $memberCall;
            } elseif ($type === Visitor\MemberUsageFetchingVisitor::TYPE_EXPRESSION_IS_NOT_CLASSLIKE) {
                $output['errors']['expressionIsNotClasslike'][] = $memberCall;
            } elseif ($type === Visitor\MemberUsageFetchingVisitor::TYPE_EXPRESSION_HAS_NO_SUCH_MEMBER) {
                $output['errors']['expressionHasNoSuchMember'][] = $memberCall;
            } elseif ($type === Visitor\MemberUsageFetchingVisitor::TYPE_EXPRESSION_NEW_MEMBER_WILL_BE_CREATED) {
                $output['warnings']['expressionNewMemberWillBeCreated'][] = $memberCall;
            }
        }

        return $output;
    }
}
