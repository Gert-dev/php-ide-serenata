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
    public function getStructureRawInfo($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawParents($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawChildren($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawInterfaces($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawImplementors($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawTraits($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawTraitUsers($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawConstants($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawProperties($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureRawMethods($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureTraitAliasesAssoc($id);

    /**
     * @param int $id
     *
     * @return array
     */
    public function getStructureTraitPrecedencesAssoc($id);
}
