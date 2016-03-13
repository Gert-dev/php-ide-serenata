<?php

namespace PhpIntegrator\Application\Command\SemanticLint\Visitor;

use PhpParser\Node;

/**
 * Node visitor that fetches usages of class, trait, and interface names from docblocks.
 */
class DocblockClassUsageFetchingVisitor extends ClassUsageFetchingVisitor
{
    /**
     * @var string|null
     */
    protected $lastNamespace = null;

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        $docblock = $node->getDocComment();

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->lastNamespace = (string) $node->name;
        }

        if ($docblock) {
            // Look for types right after a tag.
            preg_match_all(
                '/@(?:param|throws|return|var)\s+(\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)($|\s|\})/',
                $docblock,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                if ($this->isValidType($match[1])) {
                    $type = $match[1];
                    $parts = explode('\\', $type);
                    $firstPart = array_shift($parts);

                    $isFullyQualified = false;

                    if (!empty($type) && $type[0] === '\\') {
                        $isFullyQualified = true;
                        $type = mb_substr($type, 1);
                    }

                    // NOTE: We use the same start position as end position as we can't fetch the location of the
                    // docblock from the parser.
                    // TODO: This could potentially be done using some magic with token fetching or walking backwards
                    // from the node itself to find the docblock and then calculating the position inside the docblock.
                    $this->classUsageList[] = [
                        'name'             => $type,
                        'firstPart'        => $firstPart,
                        'isFullyQualified' => $isFullyQualified,
                        'namespace'        => $this->lastNamespace,
                        'line'             => $node->getAttribute('startLine')    ? $node->getAttribute('startLine')        : null,
                        'start'            => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')     : null,
                        'end'              => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos') + 1 : null
                    ];
                }
            }
        }
    }

    /// @inherited
    protected function isValidType($type)
    {
        return !in_array($type, [
            // As per https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md#keyword
            'string',
            'int',
            'bool',
            'float',
            'object',
            'mixed',
            'array',
            'resource',
            'void',
            'null',
            'callable',
            'false',
            'true',
            'self',
            'static',
            'parent',
            '$this'
        ]);
    }
}
