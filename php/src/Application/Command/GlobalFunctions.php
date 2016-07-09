<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;

/**
 * Command that shows a list of global functions.
 */
class GlobalFunctions extends AbstractCommand
{
    /**
     * @inheritDoc
     */
     protected function process(ArrayAccess $arguments)
     {
         $result = $this->getGlobalFunctions();

         return $this->outputJson(true, $result);
     }

     /**
      * @return array
      */
     public function getGlobalFunctions()
     {
         $result = [];

         foreach ($this->indexDatabase->getGlobalFunctions() as $function) {
             $result[$function['fqcn']] = $this->getIndexDataAdapter()->getFunctionInfo($function);
         }

         return $result;
     }
}
