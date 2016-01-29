<?php

namespace PhpIntegrator\Application\Command;

use UnexpectedValueException;

use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Command that shows information about a class, interface or trait.
 */
class ClassInfo extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function process(array $arguments)
    {
        if (empty($arguments)) {
            throw new UnexpectedValueException(
                'The fully qualified name of the structural element is required for this command.'
            );
        }

        $fqsen = array_shift($arguments);
        $fqsen = str_replace('\\\\', '\\', $fqsen);

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
