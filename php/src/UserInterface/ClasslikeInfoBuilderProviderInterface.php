<?php

namespace PhpIntegrator\UserInterface;

/**
 * Defines functionality that must be exposed by classes that provide data to an ClasslikeInfoBuilder.
 */
interface ClasslikeInfoBuilderProviderInterface
{
    /**
     * @param string $fqcn
     *
     * @return array
     */
    public function getStructureRawInfo($fqcn);

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
