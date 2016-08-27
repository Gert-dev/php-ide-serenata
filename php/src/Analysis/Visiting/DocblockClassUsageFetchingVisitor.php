<?php

namespace PhpIntegrator\Analysis\Visiting;

use PhpIntegrator\Parsing\DocblockParser;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpParser\Node;
use PhpParser\Comment;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that fetches usages of class, trait, and interface names from docblocks.
 */
class DocblockClassUsageFetchingVisitor extends NodeVisitorAbstract
{
    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var DocblockParser
     */
    protected $docblockParser;

    /**
     * @var array
     */
    protected $classUsageList = [];

    /**
     * @var string|null
     */
    protected $lastNamespace = null;

    /**
     * @param TypeAnalyzer $typeAnalyzer
     * @param DocblockParser    $docblockParser
     */
    public function __construct(TypeAnalyzer $typeAnalyzer, DocblockParser $docblockParser)
    {
        $this->typeAnalyzer = $typeAnalyzer;
        $this->docblockParser = $docblockParser;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        $docblock = $node->getDocComment();

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->lastNamespace = (string) $node->name;
        }

        if (!$docblock) {
            return;
        }

        $this->fetchTypeClasses($docblock);
        $this->fetchAnnotationClasses($docblock);
    }

    /**
     * Fetches classes being used as type (e.g. @param Foo, @throws \My\Class, ...).
     *
     * @param Comment\Doc $docblock
     */
    protected function fetchTypeClasses(Comment\Doc $docblock)
    {
        preg_match_all(
            '/@(?:param|throws|return|var)\s+((?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)(?:\[\])?(?:\|(?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)(?:\[\])?)*)(?:$|\s|\})/',
            $docblock,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $match) {
            $this->validateType($docblock, $match[1][0], $match[1][1]);
        }
    }

    /**
     * Fetches class names being used as annotation (e.g. @\My\Class).
     *
     * @param Comment\Doc $docblock
     */
    protected function fetchAnnotationClasses(Comment\Doc $docblock)
    {
        preg_match_all(
            '/\*\s+@((?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)(?:\[\])?(?:\|(?:\\\\?[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*)(?:\[\])?)*)(?:$|\W|\})/',
            $docblock,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $match) {
            $this->validateType($docblock, $match[1][0], $match[1][1]);
        }
    }

    /**
     * @param Comment\Doc $docblock
     * @param mixed       $typeString
     * @param mixed       $typeStringOffset
     */
    protected function validateType(Comment\Doc $docblock, $typeString, $typeStringOffset)
    {
        $types = explode(DocblockParser::TYPE_SPLITTER, $typeString);
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

                $this->classUsageList[] = [
                    'name'             => $type,
                    'firstPart'        => $firstPart,
                    'isFullyQualified' => $isFullyQualified,
                    'namespace'        => $this->lastNamespace,
                    'line'             => $docblock->getLine()    ? $docblock->getLine() : null,
                    'start'            => $docblock->getFilePos() ?
                        ($docblock->getFilePos() + $typeStringOffset) : null,

                    'end'              => $docblock->getFilePos() ?
                        ($docblock->getFilePos() + $typeStringOffset + mb_strlen($typeString)) : null
                ];
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
         return
            !$this->typeAnalyzer->isSpecialType($type) &&
            !$this->docblockParser->isValidTag($type);
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
