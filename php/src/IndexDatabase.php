<?php

namespace PhpIntegrator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;

use Doctrine\DBAL\Exception\TableNotFoundException;

class IndexDatabase implements IndexStorageInterface
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * The path to the database to use.
     *
     * @var string
     */
    protected $databasePath;

    /**
     * The version of the database index.
     *
     * @var string
     */
    protected $databaseVersion;

    /**
     * Constructor.
     *
     * @param string $databasePath
     * @param int    $databaseVersion
     */
    public function __construct($databasePath, $databaseVersion)
    {
        $this->databasePath = $databasePath;
        $this->databaseVersion = $databaseVersion;

        // Force establishing the connection and creation of tables.
        $this->getConnection();
    }

    /**
     * Retrieves hte index database.
     *
     * @return Connection
     */
    public function getConnection()
    {
        if (!$this->connection) {
            $configuration = new Configuration();

            $this->connection = DriverManager::getConnection([
                'driver' => 'pdo_sqlite',
                'path'   => $this->databasePath
            ], $configuration);

            $outOfDate = null;

            try {
                $version = $this->getConnection()->createQueryBuilder()
                    ->select('value')
                    ->from(IndexStorageItemEnum::SETTINGS)
                    ->where('name = ?')
                    ->setParameter(0, 'version')
                    ->execute()
                    ->fetchColumn();

                $outOfDate = ($version < $this->databaseVersion);
            } catch (TableNotFoundException $exception) {

            }

            $this->connection->close();

            if ($outOfDate === true) {
                $this->connection->close();
                $this->connection = null;

                @unlink($this->databasePath);

                return $this->getConnection(); // Do it again.
            } elseif ($outOfDate === null) {
                $this->createDatabaseTables($this->connection);
            }
        }

        // Have to be a douche about this as it seems to reset itself, even though the connection is not closed.
        $statement = $this->connection->executeQuery('PRAGMA foreign_keys=ON');

        return $this->connection;
    }

    /**
     * (Re)creates the database tables in the database using the specified connection.
     *
     * @param Connection $connection
     */
    protected function createDatabaseTables(Connection $connection)
    {
        $files = glob(__DIR__ . '/Sql/*.sql');

        foreach ($files as $file) {
            $sql = file_get_contents($file);

            foreach (explode(';', $sql) as $sqlQuery) {
                $statement = $connection->prepare($sqlQuery);
                $statement->execute();
            }
        }

        $connection->insert(IndexStorageItemEnum::SETTINGS, [
            'name'  => 'version',
            'value' => $this->databaseVersion
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getFileModifiedMap()
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('path', 'indexed_time')
            ->from(IndexStorageItemEnum::FILES)
            ->execute();

        $files = [];

        foreach ($result as $record) {
            $files[$record['path']] = new \DateTime($record['indexed_time']);
        }

        return $files;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilePathById($id)
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('path')
            ->from(IndexStorageItemEnum::FILES)
            ->where('id = ?')
            ->setParameter(0, $id)
            ->execute()
            ->fetchColumn();

        return $result ? $result : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getFileId($path)
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from(IndexStorageItemEnum::FILES)
            ->where('path = ?')
            ->setParameter(0, $path)
            ->execute()
            ->fetchColumn();

        return $result ? $result : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessModifierId($name)
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from(IndexStorageItemEnum::ACCESS_MODIFIERS)
            ->where('name = ?')
            ->setParameter(0, $name)
            ->execute()
            ->fetchColumn();

        return $result ? $result : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementTypeId($name)
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENT_TYPES)
            ->where('name = ?')
            ->setParameter(0, $name)
            ->execute()
            ->fetchColumn();

        return $result ? $result : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getStructuralElementId($fqsen)
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from(IndexStorageItemEnum::STRUCTURAL_ELEMENTS)
            ->where('fqsen = ?')
            ->setParameter(0, $fqsen)
            ->execute()
            ->fetchColumn();

        return $result ? $result : null;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteFile($fileId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::FILES)
            ->where('id = ?')
            ->setParameter(0, $fileId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deletePropertiesByFileId($fileId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::PROPERTIES)
            ->where('file_id = ?')
            ->setParameter(0, $fileId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteConstantsByFileId($fileId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::CONSTANTS)
            ->where('file_id = ?')
            ->setParameter(0, $fileId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteFunctionsByFileId($fileId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::FUNCTIONS)
            ->where('file_id = ?')
            ->setParameter(0, $fileId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deletePropertiesFor($seId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::PROPERTIES)
            ->where('structural_element_id = ?')
            ->setParameter(0, $seId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMethodsFor($seId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::FUNCTIONS)
            ->where('structural_element_id = ?')
            ->setParameter(0, $seId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteConstantsFor($seId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::CONSTANTS)
            ->where('structural_element_id = ?')
            ->setParameter(0, $seId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteParentLinksFor($seId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_PARENTS_LINKED)
            ->where('structural_element_id = ?')
            ->setParameter(0, $seId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteInterfaceLinksFor($seId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_INTERFACES_LINKED)
            ->where('structural_element_id = ?')
            ->setParameter(0, $seId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteTraitLinksFor($seId)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::STRUCTURAL_ELEMENTS_TRAITS_LINKED)
            ->where('structural_element_id = ?')
            ->setParameter(0, $seId)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteExcludedStructuralElementsByFileId($fileId, array $excludedIds)
    {
        if (empty($excludedIds)) {
            $this->getConnection()->createQueryBuilder()
                ->delete(IndexStorageItemEnum::STRUCTURAL_ELEMENTS)
                ->where('file_id = ?')
                ->setParameter(0, $fileId)
                ->execute();
        } else {
            $queryBuilder = $this->getConnection()->createQueryBuilder();

            $queryBuilder
                ->delete(IndexStorageItemEnum::STRUCTURAL_ELEMENTS)
                ->where(
                    'file_id = ' . $queryBuilder->createNamedParameter($fileId) .
                    ' AND ' .
                    'id NOT IN (' . $queryBuilder->createNamedParameter($excludedIds, Connection::PARAM_INT_ARRAY) . ')'
                )
                ->execute();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function insert($indexStorageItem, array $data)
    {
        $this->getConnection()->insert($indexStorageItem, $data);

        return $this->getConnection()->lastInsertId();
    }

    /**
     * {@inheritDoc}
     */
    public function update($indexStorageItem, $id, array $data)
    {
        $this->getConnection()->update($indexStorageItem, $data, is_array($id) ? $id : ['id' => $id]);
    }
}
