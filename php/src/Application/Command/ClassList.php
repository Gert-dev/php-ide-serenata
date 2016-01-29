<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Command that shows a list of available classes, interfaces and traits.
 */
class ClassList extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function process(array $arguments)
    {
        $result = [];

        $storageProxy = new IndexDataAdapter\ClassListProxyProvider($this->indexDatabase);
        $dataAdapter = new IndexDataAdapter($storageProxy);

        foreach ($this->indexDatabase->getAllStructuralElementsRawInfo() as $element) {
            // Directly load in the raw information we already have, this avoids performing a database query for each
            // record.
            $storageProxy->setStructuralElementRawInfo($element);

            $info = $dataAdapter->getStructuralElementInfo($element['id']);

            // $constructor = null;

            // if (isset($info['methods']['__construct'])) {
                // $constructor = $info['methods']['__construct'];
            // }

            unset($info['constants'], $info['properties'], $info['methods']);

            // if ($constructor) {
                // $info['methods']['__construct'] = $constructor;
            // }

            $result[$element['fqsen']] = $info;
        }

        return $this->outputJson(true, $result);
    }
}
