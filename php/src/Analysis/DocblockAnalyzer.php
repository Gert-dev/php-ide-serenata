<?php

namespace PhpIntegrator\Analysis;

/**
 * Provides functionality for analyzing docblocks.
 */
class DocblockAnalyzer
{
    /**
     * Returns a boolean indicating whether the specified short description contains an indicator that the full parent
     * structural element's documentation must be inherited.
     *
     * @param string $shortDescription
     *
     * @return bool
     */
    public function isFullInheritDocSyntax($shortDescription)
    {
        $specialTags = [
            // Ticket #86 - Inherit the entire parent docblock if the docblock contains nothing but these tags.
            // According to draft PSR-5 and phpDocumentor's implementation, these are incorrect. However, some large
            // frameworks (such as Symfony 2) use these and it thus makes life easier for many  developers.
            '{@inheritdoc}', '{@inheritDoc}',

            // This tag (without curly braces) is, according to draft PSR-5, a valid way to indicate an entire docblock
            // should be inherited and to implicitly indicate that documentation was not forgotten.
            '@inheritDoc'
        ];

        return in_array($shortDescription, $specialTags);
    }
}
