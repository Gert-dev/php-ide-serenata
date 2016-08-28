<?php

namespace PhpIntegrator\Analysis\Relations;

use ArrayObject;

use PhpIntegrator\Analysis\DocblockAnalyzer;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Parsing\DocblockParser;

/**
 * Base class for resolvers.
 */
abstract class AbstractResolver
{
    /**
     * @var DocblockAnalyzer
     */
    protected $docblockAnalyzer;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @param DocblockAnalyzer $docblockAnalyzer
     * @param TypeAnalyzer     $typeAnalyzer
     */
    public function __construct(DocblockAnalyzer $docblockAnalyzer, TypeAnalyzer $typeAnalyzer)
    {
        $this->docblockAnalyzer = $docblockAnalyzer;
        $this->typeAnalyzer = $typeAnalyzer;
    }

    /**
     * Returns a boolean indicating whether the specified item will inherit documentation from a parent item (if
     * present).
     *
     * @param array $processedData
     *
     * @return bool
     */
    protected function isInheritingFullDocumentation(array $processedData)
    {
        return
            !$processedData['hasDocblock'] ||
            $this->docblockAnalyzer->isFullInheritDocSyntax($processedData['shortDescription']);
    }

    /**
     * Resolves the inheritDoc tag for the specified description.
     *
     * Note that according to phpDocumentor this only works for the long description (not the so-called 'summary' or
     * short description).
     *
     * @param string $description
     * @param string $parentDescription
     *
     * @return string
     */
    protected function resolveInheritDoc($description, $parentDescription)
    {
        return str_replace(DocblockParser::INHERITDOC, $parentDescription, $description);
    }

    /**
     * @param array $propertyData
     *
     * @return array
     */
    protected function extractInheritedPropertyInfo(array $propertyData)
    {
        $inheritedKeys = [
            'hasDocumentation',
            'isDeprecated',
            'shortDescription',
            'longDescription',
            'typeDescription',
            'types'
        ];

        $info = [];

        foreach ($propertyData as $key => $value) {
            if (in_array($key, $inheritedKeys)) {
                $info[$key] = $value;
            }
        }

        return $info;
    }

    /**
     * @param array $methodData
     * @param array $inheritingMethodData
     *
     * @return array
     */
    protected function extractInheritedMethodInfo(array $methodData, array $inheritingMethodData)
    {
        $inheritedKeys = [
            'hasDocumentation',
            'isDeprecated',
            'shortDescription',
            'longDescription',
            'returnDescription',
            'returnTypes',
            'throws'
        ];

        // Normally parameters are inherited from the parent docblock. However, this causes problems when an overridden
        // method adds an additional optional parameter or a subclass constructor uses completely different parameters.
        // In either of these cases, we don't want to inherit the docblock parameters anymore, because it isn't
        // correct anymore (and the developer should specify a new docblock specifying the changed parameters).
        $inheritedMethodParameterNames = array_map(function (array $parameter) {
            return $parameter['name'];
        }, $methodData['parameters']);

        $inheritingMethodParameterNames = array_map(function (array $parameter) {
            return $parameter['name'];
        }, $inheritingMethodData['parameters']);

        // We need elements that are present in either A or B, but not in both. array_diff only returns items that
        // are present in A, but not in B.
        $parameterNameDiff1 = array_diff($inheritedMethodParameterNames, $inheritingMethodParameterNames);
        $parameterNameDiff2 = array_diff($inheritingMethodParameterNames, $inheritedMethodParameterNames);

        if (empty($parameterNameDiff1) && empty($parameterNameDiff2)) {
            $inheritedKeys[] = 'parameters';
        }

        $info = [];

        foreach ($methodData as $key => $value) {
            if (in_array($key, $inheritedKeys)) {
                $info[$key] = $value;
            }
        }

        return $info;
    }
}
