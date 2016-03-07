<?php

namespace PhpIntegrator\Application\Command\SemanticLint;

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
                '/@[a-zA-Z-]+\s+(\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)/',
                $docblock,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                if ($this->isValidType($match[1])) {
                    $parts = explode('\\', $match[1]);
                    $firstPart = array_shift($parts);
                    $firstCharacter = !empty($firstPart) ? $firstPart[0] : null;

                    // NOTE: We use the same start position as end position as we can't fetch the location of the
                    // docblock from the parser.
                    // TODO: This could potentially be done using some magic with token fetching or walking backwards
                    // from the node itself to find the docblock and then calculating the position inside the docblock.
                    $this->classUsageList[] = [
                        'name'             => $match[1],
                        'firstPart'        => $firstPart,
                        'isFullyQualified' => $firstCharacter === '\\',
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
        return parent::isValidType($type) && !in_array($type, [
            'int', 'float', 'string', 'bool', 'resource', 'array', 'mixed'
        ]);
    }
}
