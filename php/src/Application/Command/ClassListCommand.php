<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;

use GetOptionKit\OptionCollection;

use PhpIntegrator\UserInterface\IndexDataAdapter;
use PhpIntegrator\UserInterface\IndexDataAdapterWhiteHolingProxyProvider;

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

         $storageProxy = new IndexDataAdapterWhiteHolingProxyProvider($this->getIndexDataAdapterProvider());
         $dataAdapter = new IndexDataAdapter($storageProxy);

         foreach ($this->getIndexDatabase()->getAllStructuresRawInfo($file) as $element) {
             // Directly load in the raw information we already have, this avoids performing a database query for each
             // record.
             $storageProxy->setStructureRawInfo($element);

             $info = $dataAdapter->getStructureInfo($element['name']);

             unset($info['constants'], $info['properties'], $info['methods']);

             $result[$element['fqcn']] = $info;
         }

         return $result;
     }
}
