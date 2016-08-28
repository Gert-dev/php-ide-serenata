<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

/**
 * Command that shows information about a class, interface or trait.
 */
class ClassInfoCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('name:', 'The name of the class, trait or interface to fetch information about.')->isa('string');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['name'])) {
            throw new UnexpectedValueException(
                'The fully qualified name of the structural element is required for this command.'
            );
        }

        $result = $this->getClassInfo($arguments['name']->value);

        return $this->outputJson(true, $result);
    }

    /**
     * @param string $fqcn
     *
     * @return array
     */
    public function getClassInfo($fqcn)
    {
        $fqcn = $this->getTypeAnalyzer()->getNormalizedFqcn($fqcn);

        return $this->getClasslikeInfoBuilder()->getClasslikeInfo($fqcn);
    }
}
