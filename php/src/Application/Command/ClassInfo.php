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
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

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

        $id = $this->indexDatabase->getStructureId($fqcn);

        if (!$id) {
            throw new UnexpectedValueException('The structural element "' . $fqcn . '" was not found!');
        }

        return $this->getIndexDataAdapter()->getStructureInfo($id);
    }

    /**
     * Retrieves an instance of TypeAnalyzer. The object will only be created once if needed.
     *
     * @return TypeAnalyzer
     */
    protected function getTypeAnalyzer()
    {
        if (!$this->typeAnalyzer instanceof TypeAnalyzer) {
            $this->typeAnalyzer = new TypeAnalyzer();
        }

        return $this->typeAnalyzer;
    }
}
