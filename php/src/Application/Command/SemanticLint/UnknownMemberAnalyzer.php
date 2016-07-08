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
            'expressionHasNoType'       => [],
            'expressionIsNotClasslike'  => [],
            'expressionHasNoSuchMember' => []
        ];

        $memberCallList = $this->methodUsageFetchingVisitor->getMemberCallList();

        foreach ($memberCallList as $memberCall) {
            if ($memberCall['type'] === Visitor\MemberUsageFetchingVisitor::TYPE_EXPRESSION_HAS_NO_TYPE) {
                unset ($memberCall['type']);

                $output['expressionHasNoType'][] = $memberCall;
            } elseif ($memberCall['type'] === Visitor\MemberUsageFetchingVisitor::TYPE_EXPRESSION_IS_NOT_CLASSLIKE) {
                unset ($memberCall['type']);

                $output['expressionIsNotClasslike'][] = $memberCall;
            } elseif ($memberCall['type'] === Visitor\MemberUsageFetchingVisitor::TYPE_EXPRESSION_HAS_NO_SUCH_MEMBER) {
                unset ($memberCall['type']);

                $output['expressionHasNoSuchMember'][] = $memberCall;
            }
        }

        return $output;
    }
}
