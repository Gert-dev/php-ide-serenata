<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\ClasslikeInfoBuilder;

use PhpIntegrator\Analysis\Typing\TypeDeducer;
use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Analysis\Visiting\MemberUsageFetchingVisitor;

/**
 * Looks for unknown member names.
 */
class UnknownMemberAnalyzer implements AnalyzerInterface
{
    /**
     * @var MemberUsageFetchingVisitor
     */
    protected $methodUsageFetchingVisitor;

    /**
     * @param TypeDeducer          $typeDeducer
     * @param ClasslikeInfoBuilder $classlikeInfoBuilder
     * @param TypeAnalyzer         $typeAnalyzer
     * @param string               $file
     * @param string               $code
     */
    public function __construct(
        TypeDeducer $typeDeducer,
        ClasslikeInfoBuilder $classlikeInfoBuilder,
        TypeAnalyzer $typeAnalyzer,
        $file,
        $code
    ) {
        $this->methodUsageFetchingVisitor = new MemberUsageFetchingVisitor(
            $typeDeducer,
            $classlikeInfoBuilder,
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

            if ($type === MemberUsageFetchingVisitor::TYPE_EXPRESSION_HAS_NO_TYPE) {
                $output['errors']['expressionHasNoType'][] = $memberCall;
            } elseif ($type === MemberUsageFetchingVisitor::TYPE_EXPRESSION_IS_NOT_CLASSLIKE) {
                $output['errors']['expressionIsNotClasslike'][] = $memberCall;
            } elseif ($type === MemberUsageFetchingVisitor::TYPE_EXPRESSION_HAS_NO_SUCH_MEMBER) {
                $output['errors']['expressionHasNoSuchMember'][] = $memberCall;
            } elseif ($type === MemberUsageFetchingVisitor::TYPE_EXPRESSION_NEW_MEMBER_WILL_BE_CREATED) {
                $output['warnings']['expressionNewMemberWillBeCreated'][] = $memberCall;
            }
        }

        return $output;
    }
}
