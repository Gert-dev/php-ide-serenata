<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;

use PhpIntegrator\Analysis\Conversion\FunctionConverter;

use PhpIntegrator\Indexing\IndexDatabase;

/**
 * Command that shows a list of global functions.
 */
class GlobalFunctionsCommand extends AbstractCommand
{
    /**
     * @var FunctionConverter
     */
    protected $functionConverter;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @param FunctionConverter $functionConverter
     * @param IndexDatabase     $indexDatabase
     */
    public function __construct(FunctionConverter $functionConverter, IndexDatabase $indexDatabase)
    {
        $this->functionConverter = $functionConverter;
        $this->indexDatabase = $indexDatabase;
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
