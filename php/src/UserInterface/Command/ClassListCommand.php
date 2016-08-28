<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;

use GetOptionKit\OptionCollection;

use PhpIntegrator\UserInterface\ClasslikeInfoBuilder;
use PhpIntegrator\UserInterface\ClasslikeInfoBuilderWhiteHolingProxyProvider;

/**
 * Command that shows a list of available classes, interfaces and traits.
 */
class ClassListCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
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

        $storageProxy = new ClasslikeInfoBuilderWhiteHolingProxyProvider($this->getClasslikeInfoBuilderProvider());

        $dataAdapter = new ClasslikeInfoBuilder(
            $this->getConstantConverter(),
            $this->getClasslikeConstantConverter(),
            $this->getPropertyConverter(),
            $this->getFunctionConverter(),
            $this->getMethodConverter(),
            $this->getClasslikeConverter(),
            $this->getInheritanceResolver(),
            $this->getInterfaceImplementationResolver(),
            $this->getTraitUsageResolver(),
            $storageProxy,
            $this->getTypeAnalyzer()
        );

        foreach ($this->getIndexDatabase()->getAllStructuresRawInfo($file) as $element) {
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
