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

            $statement = $this->connection->executeQuery('PRAGMA foreign_keys=ON');

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
    public function getAccessModifierid($name)
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
    public function insert($indexStorageItem, array $data)
    {
        $this->getConnection()->insert($indexStorageItem, $data);

        return $this->getConnection()->lastInsertId();
    }

    /**
     * Retrieves all storage items of the specified type.
     *
     * @param string $indexStorageItem
     *
     * @return array
     */
    /*public function getAll($indexStorageItem)
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($indexStorageItem)
            ->execute()
            ->fetchAll();

        return $result;
    }*/
}
