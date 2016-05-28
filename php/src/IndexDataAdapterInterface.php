<?php

namespace PhpIntegrator;

/**
 * Interface for classes that adapt and resolve data from the index as needed to receive an appropriate output data
 * format.
 */
interface IndexDataAdapterInterface
{
    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureInfo($id);
}
