<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;

/**
 * Command that shows a list of global functions.
 */
class GlobalFunctionsCommand extends AbstractCommand
{
    /**
     * @var FunctionConverter
     */
    protected $functionConverter;




    public function __construct(
        FunctionConverter $functionConverter,
        Parser $parser = null,
        Cache $cache = null,
        IndexDatabase $indexDatabase = null
    ) {
        parent::__construct($parser, $cache, $indexDatabase);

        $this->functionConverter = $functionConverter;
    }



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
             $result[$function['fqcn']] = $this->functionConverter->convert($function);
         }

         return $result;
     }
}
