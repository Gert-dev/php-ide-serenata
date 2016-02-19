<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Command that shows information about a class, interface or trait.
 */
class ClassInfo extends BaseCommand
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

        $fqsen = $arguments['name']->value;

        if ($fqsen[0] === '\\') {
            $fqsen = mb_substr($fqsen, 1);
        }

        $id = $this->indexDatabase->getStructuralElementId($fqsen);

        if (!$id) {
            throw new UnexpectedValueException('The structural element "' . $fqsen . '" was not found!');
        }

        $result = $this->getIndexDataAdapter()->getStructuralElementInfo($id);

        return $this->outputJson(true, $result);
    }
}
