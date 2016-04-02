<?php

namespace PhpIntegrator\Application\Command\SemanticLint\Visitor;

use PhpIntegrator\DocParser;
use PhpIntegrator\TypeAnalyzer;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that fetches usages of class, trait, and interface names from docblocks.
 */
class DocblockClassUsageFetchingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    protected $classUsageList = [];

    /**
     * @var TypeAnalyzer|null
     */
    protected $typeAnalyzer = null;

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
                '/@(?:param|throws|return|var)\s+((?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)(?:\[\])?(?:\|(?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)(?:\[\])?)*)(?:$|\s|\})/',
                $docblock,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $types = explode(DocParser::TYPE_SPLITTER, $match[1]);

                foreach ($types as $type) {
                    if (mb_substr($type, -2) === '[]') {
                        $type = mb_substr($type, 0, -2);
                    }

                    if ($this->isValidType($type)) {
                        $parts = explode('\\', $type);
                        $firstPart = array_shift($parts);

                        $isFullyQualified = false;

                        if (!empty($type) && $type[0] === '\\') {
                            $isFullyQualified = true;
                            $type = mb_substr($type, 1);
                        }

                        // NOTE: We use the same start position as end position as we can't fetch the location of the
                        // docblock from the parser.
                        // TODO: A next release of php-parser will allow for this, see also
                        // https://github.com/nikic/PHP-Parser/issues/263#issuecomment-204693050
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
    }

    /**
     * @param string $type
     *
     * @return bool
     */
     protected function isValidType($type)
     {
         return !$this->getTypeAnalyzer()->isSpecialType($type);
     }

    /**
     * @return TypeAnalyzer
     */
    protected function getTypeAnalyzer()
    {
        if (!$this->typeAnalyzer) {
            $this->typeAnalyzer = new TypeAnalyzer();
        }

        return $this->typeAnalyzer;
    }

    /**
     * Retrieves the class usage list.
     *
     * @return array
     */
    public function getClassUsageList()
    {
        return $this->classUsageList;
    }
}
