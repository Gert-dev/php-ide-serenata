<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\ClasslikeInfoBuilder;
use PhpIntegrator\UserInterface\ClasslikeInfoBuilderWhiteHolingProxyProvider;

use Doctrine\Common\Cache\Cache;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpParser\Parser;

/**
 * Command that shows a list of available classes, interfaces and traits.
 */
class ClassListCommand extends AbstractCommand
{
    /**
     * @var ConstantConverter
     */
    protected $constantConverter;

    /**
     * @var ClasslikeConstantConverter
     */
    protected $classlikeConstantConverter;

    /**
     * @var PropertyConverter
     */
    protected $propertyConverter;

    /**
     * @var FunctionConverter
     */
    protected $functionConverter;

    /**
     * @var MethodConverter
     */
    protected $methodConverter;

    /**
     * @var ClasslikeConverter
     */
    protected $classlikeConverter;

    /**
     * @var InheritanceResolver
     */
    protected $inheritanceResolver;

    /**
     * @var InterfaceImplementationResolver
     */
    protected $interfaceImplementationResolver;

    /**
     * @var TraitUsageResolver
     */
    protected $traitUsageResolver;

    /**
     * @var ClasslikeInfoBuilderProvider
     */
    protected $classlikeInfoBuilderProvider;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;





    public function __construct(
        ConstantConverter $constantConverter,
        ClasslikeConstantConverter $classlikeConstantConverter,
        PropertyConverter $propertyConverter,
        FunctionConverter $functionConverter,
        MethodConverter $methodConverter,
        ClasslikeConverter $classlikeConverter,
        InheritanceResolver $inheritanceResolver,
        InterfaceImplementationResolver $interfaceImplementationResolver,
        TraitUsageResolver $traitUsageResolver,
        ClasslikeInfoBuilderProvider $classlikeInfoBuilderProvider,
        TypeAnalyzer $typeAnalyzer,
        IndexDatabase $indexDatabase
    ) {
        $this->constantConverter = $constantConverter;
        $this->classlikeConstantConverter = $classlikeConstantConverter;
        $this->propertyConverter = $propertyConverter;
        $this->functionConverter = $functionConverter;
        $this->methodConverter = $methodConverter;
        $this->classlikeConverter = $classlikeConverter;
        $this->inheritanceResolver = $inheritanceResolver;
        $this->interfaceImplementationResolver = $interfaceImplementationResolver;
        $this->traitUsageResolver = $traitUsageResolver;
        $this->classlikeInfoBuilderProvider = $classlikeInfoBuilderProvider;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->indexDatabase = $indexDatabase;
    }

    /**
     * @inheritDoc
     */
    public function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('file?', 'The file to filter the results by.')->isa('string');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        $file = isset($arguments['file']) ? $arguments['file']->value : null;

        $classList = $this->getClassList($file);

        return $this->outputJson(true, $classList);
    }

    /**
     * @param string|null $file
     *
     * @return array
     */
    public function getClassList($file)
    {
        $result = [];

        $storageProxy = new ClasslikeInfoBuilderWhiteHolingProxyProvider($this->classlikeInfoBuilderProvider);

        $dataAdapter = new ClasslikeInfoBuilder(
            $this->constantConverter,
            $this->classlikeConstantConverter,
            $this->propertyConverter,
            $this->functionConverter,
            $this->methodConverter,
            $this->classlikeConverter,
            $this->inheritanceResolver,
            $this->interfaceImplementationResolver,
            $this->traitUsageResolver,
            $storageProxy,
            $this->typeAnalyzer
        );

        foreach ($this->indexDatabase->getAllStructuresRawInfo($file) as $element) {
            // Directly load in the raw information we already have, this avoids performing a database query for each
            // record.
            $storageProxy->setStructureRawInfo($element);

            $info = $dataAdapter->getClasslikeInfo($element['name']);

            unset($info['constants'], $info['properties'], $info['methods']);

            $result[$element['fqcn']] = $info;
        }

        return $result;
    }
}
