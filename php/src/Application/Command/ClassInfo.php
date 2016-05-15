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
        if ($fqcn[0] === '\\') {
            $fqcn = mb_substr($fqcn, 1);
        }

        $id = $this->indexDatabase->getStructureId($fqcn);

        if (!$id) {
            throw new UnexpectedValueException('The structural element "' . $fqcn . '" was not found!');
        }

        return $this->getIndexDataAdapter()->getStructureInfo($id);
    }
}
