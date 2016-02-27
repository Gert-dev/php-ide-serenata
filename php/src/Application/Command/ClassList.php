<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;

use GetOptionKit\OptionCollection;

use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Command that shows a list of available classes, interfaces and traits.
 */
class ClassList extends BaseCommand
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

         $storageProxy = new IndexDataAdapter\ClassListProxyProvider($this->indexDatabase);
         $dataAdapter = new IndexDataAdapter($storageProxy);

         foreach ($this->indexDatabase->getAllStructuralElementsRawInfo($file) as $element) {
             // Directly load in the raw information we already have, this avoids performing a database query for each
             // record.
             $storageProxy->setStructuralElementRawInfo($element);

             $info = $dataAdapter->getStructuralElementInfo($element['id']);

             unset($info['constants'], $info['properties'], $info['methods']);

             $result[$element['fqsen']] = $info;
         }

         return $result;
     }
}
