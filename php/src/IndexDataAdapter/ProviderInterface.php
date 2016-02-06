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
    public function getStructuralElementRawParents($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructuralElementRawChildren($id);

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
    public function getStructuralElementRawImplementors($id);

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
    public function getStructuralElementRawTraitUsers($id);

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
}
