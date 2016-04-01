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
     * @return \Traversable
     */
    public function getStructureRawInfo($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawParents($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawChildren($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawInterfaces($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawImplementors($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawTraits($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawTraitUsers($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawConstants($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawProperties($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureRawMethods($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureTraitAliasesAssoc($id);

    /**
     * @param int $id
     *
     * @return \Traversable
     */
    public function getStructureTraitPrecedencesAssoc($id);
}
