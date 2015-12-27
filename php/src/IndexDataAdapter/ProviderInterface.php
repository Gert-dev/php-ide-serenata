<?php

namespace PhpIntegrator\IndexDataAdapter;

/**
 * Defines functionality that must be exposed by classes that provide data to an IndexDataAdapter.
 */
interface ProviderInterface
{
    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementRawInfo($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementRawInterfaces($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementRawTraits($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementRawConstants($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementRawProperties($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementRawMethods($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementTraitAliasesAssoc($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementTraitPrecedencesAssoc($id);

    /**
     * @param int $functionId
     *
     * @return \Traversable
     */
    public function getFunctionParameters($functionId);

    /**
     * @param int $functionId
     *
     * @return \Traversable
     */
    public function getFunctionThrows($functionId);

    /**
     * Retrieves a list of parent FQSEN's for the specified structural element.
     *
     * @param int $seId
     *
     * @return array An associative array mapping structural element ID's to their FQSEN.
     */
    public function getParentFqsens($seId);
}
