<?php

namespace PhpIntegrator\Indexing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

use PhpIntegrator\Analysis\ClasslikeInfoBuilderProviderInterface;

use PhpIntegrator\Analysis\Typing\NamespaceImportProviderInterface;

/**
 * Represents that database that is used for indexing.
 */
class IndexDatabase implements StorageInterface, ClasslikeInfoBuilderProviderInterface, NamespaceImportProviderInterface
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
    protected function getConnection()
    {
        if (!$this->connection) {
            $isNewDatabase = !file_exists($this->databasePath);

            $configuration = new Configuration();

            $this->connection = DriverManager::getConnection([
                'driver' => 'pdo_sqlite',
                'path'   => $this->databasePath
            ], $configuration);

            $outOfDate = null;

            if ($isNewDatabase) {
                $this->createDatabaseTables($this->connection);

                // NOTE: This causes a database write and will cause locking problems if multiple PHP processes are
                // spawned and another one is also writing (e.g. indexing).
                $this->connection->executeQuery('PRAGMA user_version=' . $this->databaseVersion);
            } else {
                $version = $this->connection->executeQuery('PRAGMA user_version')->fetchColumn();

                if ($version < $this->databaseVersion) {
                    $this->connection->close();
                    $this->connection = null;

                    @unlink($this->databasePath);

                    return $this->getConnection(); // Do it again.
                }
            }

            // Data could become corrupted if the operating system were to crash during synchronization, but this
            // matters very little as we will just reindex the project next time. In the meantime, this majorly reduces
            // hard disk I/O during indexing and increases indexing speed dramatically (dropped from over a minute to a
            // couple of seconds for a very small (!) project).
            $this->connection->executeQuery('PRAGMA synchronous=OFF');

            // Activate memory-mapped I/O. See also https://www.sqlite.org/mmap.html . In a test case, this halved the
            // time it took to build information about a structure (from 250 ms to 125 ms). On systems that do not
            // support it, this pragma just does nothing.
            $this->connection->executeQuery('PRAGMA mmap_size=100000000'); // About 100 MB.
        }

        // Have to be a douche about this as these PRAGMA's seem to reset, even though the connection is not closed.
        $this->connection->executeQuery('PRAGMA foreign_keys=ON');

        // Use the new Write-Ahead Logging mode, which offers performance benefits for our purposes. See also
        // https://www.sqlite.org/draft/wal.html
        $this->connection->executeQuery('PRAGMA journal_mode=WAL');

        return $this->connection;
    }

    /**
     * Retrieves the currently set databasePath.
     *
     * @return string
     */
    public function getDatabasePath()
    {
        return $this->databasePath;
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function getAccessModifierMap()
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::ACCESS_MODIFIERS)
            ->execute();

        $map = [];

        foreach ($result as $row) {
            $map[$row['name']] = $row['id'];
        }

        return $map;
    }

    /**
     * @inheritDoc
     */
    public function getStructureTypeMap()
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(IndexStorageItemEnum::STRUCTURE_TYPES)
            ->execute();

        $map = [];

        foreach ($result as $row) {
            $map[$row['name']] = $row['id'];
        }

        return $map;
    }

    /**
     * @inheritDoc
     */
    public function deleteFile($path)
    {
        $this->getConnection()->createQueryBuilder()
            ->delete(IndexStorageItemEnum::FILES)
            ->where('path = ?')
            ->setParameter(0, $path)
            ->execute();
    }

    /**
     * {@inheritDoc}
     */
    public function getSetting($name)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('id', 'value')
            ->from(IndexStorageItemEnum::SETTINGS)
            ->where('name = ?')
            ->setParameter(0, $name)
            ->execute()
            ->fetch();
    }

    /**
     * Retrieves a query builder that fetches raw information about all structural elements.
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    protected function getStructureRawInfoQueryBuilder()
    {
        return $this->getConnection()->createQueryBuilder()
            ->select(
                'se.*',
                'fi.path',
                '(setype.name) AS type_name'//,
                // 'sepl.linked_structure_id'
            )
            ->from(IndexStorageItemEnum::STRUCTURES, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURE_TYPES, 'setype', 'setype.id = se.structure_type_id')
            // ->leftJoin('se', IndexStorageItemEnum::STRUCTURES_PARENTS_LINKED, 'sepl', 'sepl.structure_id = se.id')
            // ->leftJoin('se', IndexStorageItemEnum::STRUCTURES, 'se_parent', 'se_parent.fqcn = sepl.linked_structure_fqcn')
            ->leftJoin('se', IndexStorageItemEnum::FILES, 'fi', 'fi.id = se.file_id');
    }

    /**
     * Retrieves raw information about all structural elements.
     *
     * @param string|null $file
     *
     * @return \Traversable
     */
    public function getAllStructuresRawInfo($file)
    {
        $queryBuilder = $this->getStructureRawInfoQueryBuilder();

        if ($file) {
            $queryBuilder
                ->where('fi.path = ?')
                ->setParameter(0, $file);
        }

        return $queryBuilder->execute();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawInfo($fqcn)
    {
        return $this->getStructureRawInfoQueryBuilder()
            ->where('se.fqcn = ?')
            ->setParameter(0, $fqcn)
            ->execute()
            ->fetch();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawParents($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('se.id', 'se.fqcn')
            ->from(IndexStorageItemEnum::STRUCTURES, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES_PARENTS_LINKED, 'sepl', 'sepl.linked_structure_fqcn = se.fqcn')
            ->where('sepl.structure_id = ?')
            ->groupBy('se.fqcn')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawChildren($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('se.id', 'se.fqcn')
            ->from(IndexStorageItemEnum::STRUCTURES, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES_PARENTS_LINKED, 'sepl', 'sepl.structure_id = se.id')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES, 'se2', 'se2.id = ?')
            ->where('sepl.linked_structure_fqcn = se2.fqcn')
            ->groupBy('se.fqcn')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawInterfaces($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('se.id', 'se.fqcn')
            ->from(IndexStorageItemEnum::STRUCTURES, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES_INTERFACES_LINKED, 'seil', 'seil.linked_structure_fqcn = se.fqcn')
            ->where('seil.structure_id = ?')
            ->groupBy('se.fqcn')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawImplementors($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('se.id', 'se.fqcn')
            ->from(IndexStorageItemEnum::STRUCTURES, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES_INTERFACES_LINKED, 'seil', 'seil.structure_id = se.id')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES, 'se2', 'se2.id = ?')
            ->where('seil.linked_structure_fqcn = se2.fqcn')
            ->groupBy('se.fqcn')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawTraits($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('se.id', 'se.fqcn')
            ->from(IndexStorageItemEnum::STRUCTURES, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES_TRAITS_LINKED, 'setl', 'setl.linked_structure_fqcn = se.fqcn')
            ->where('setl.structure_id = ?')
            ->groupBy('se.fqcn')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawTraitUsers($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('se.id', 'se.fqcn')
            ->from(IndexStorageItemEnum::STRUCTURES, 'se')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES_TRAITS_LINKED, 'setl', 'setl.structure_id = se.id')
            ->innerJoin('se', IndexStorageItemEnum::STRUCTURES, 'se2', 'se2.id = ?')
            ->where('setl.linked_structure_fqcn = se2.fqcn')
            ->groupBy('se.fqcn')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawConstants($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('c.*', 'fi.path')
            ->from(IndexStorageItemEnum::CONSTANTS, 'c')
            ->leftJoin('c', IndexStorageItemEnum::FILES, 'fi', 'fi.id = c.file_id')
            ->where('structure_id = ?')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawProperties($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('p.*', 'am.name AS access_modifier')
            ->from(IndexStorageItemEnum::PROPERTIES, 'p')
            ->innerJoin('p', IndexStorageItemEnum::ACCESS_MODIFIERS, 'am', 'am.id = p.access_modifier_id')
            ->where('structure_id = ?')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureRawMethods($id)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('fu.*', 'fi.path', 'am.name AS access_modifier')
            ->from(IndexStorageItemEnum::FUNCTIONS, 'fu')
            ->leftJoin('fu', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fu.file_id')
            ->innerJoin('fu', IndexStorageItemEnum::ACCESS_MODIFIERS, 'am', 'am.id = fu.access_modifier_id')
            ->where('structure_id = ?')
            ->setParameter(0, $id)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getStructureTraitAliasesAssoc($id)
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('seta.*', 'se.fqcn AS trait_fqcn', 'am.name AS access_modifier')
            ->from(IndexStorageItemEnum::STRUCTURES_TRAITS_ALIASES, 'seta')
            ->leftJoin('seta', IndexStorageItemEnum::ACCESS_MODIFIERS, 'am', 'am.id = seta.access_modifier_id')
            ->leftJoin('seta', IndexStorageItemEnum::STRUCTURES, 'se', 'se.fqcn = seta.trait_structure_fqcn')
            ->where('structure_id = ?')
            ->setParameter(0, $id)
            ->execute();

        $aliases = [];

        foreach ($result as $row) {
            $aliases[$row['name']] = $row;
        }

        return $aliases;
    }

    /**
     * @inheritDoc
     */
    public function getStructureTraitPrecedencesAssoc($id)
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->select('setp.*', 'se.fqcn AS trait_fqcn')
            ->from(IndexStorageItemEnum::STRUCTURES_TRAITS_PRECEDENCES, 'setp')
            ->innerJoin('setp', IndexStorageItemEnum::STRUCTURES, 'se', 'se.fqcn = setp.trait_structure_fqcn')
            ->where('structure_id = ?')
            ->setParameter(0, $id)
            ->execute();

        $precedences = [];

        foreach ($result as $row) {
            $precedences[$row['name']] = $row;
        }

        return $precedences;
    }

    /**
     * @inheritDoc
     */
    public function insertStructure(array $data)
    {
        return $this->insert(IndexStorageItemEnum::STRUCTURES, $data);
    }

    /**
     * @inheritDoc
     */
    public function insert($indexStorageItem, array $data)
    {
        $this->getConnection()->insert($indexStorageItem, $data);

        return $this->getConnection()->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function update($indexStorageItem, $id, array $data)
    {
        $this->getConnection()->update($indexStorageItem, $data, is_array($id) ? $id : ['id' => $id]);
    }

    /**
     * Fetches a list of global constants.
     *
     * @return \Traversable
     */
    public function getGlobalConstants()
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('c.*', 'fi.path')
            ->from(IndexStorageItemEnum::CONSTANTS, 'c')
            ->leftJoin('c', IndexStorageItemEnum::FILES, 'fi', 'fi.id = c.file_id')
            ->where('structure_id IS NULL')
            ->execute();
    }

    /**
     * Fetches a list of global functions.
     *
     * @return \Traversable
     */
    public function getGlobalFunctions()
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('fu.*', 'fi.path')
            ->from(IndexStorageItemEnum::FUNCTIONS, 'fu')
            ->leftJoin('fu', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fu.file_id')
            ->where('structure_id IS NULL')
            ->execute();
    }

    /**
     * @param string $fqcn
     *
     * @return array|null
     */
    public function getGlobalFunctionByFqcn($fqcn)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('fu.*', 'fi.path')
            ->from(IndexStorageItemEnum::FUNCTIONS, 'fu')
            ->leftJoin('fu', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fu.file_id')
            ->where('structure_id IS NULL')
            ->andWhere('fqcn = ?')
            ->setParameter(0, $fqcn)
            ->execute()
            ->fetch();
    }

    /**
     * @inheritDoc
     */
    public function getNamespacesForFile($filePath)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('fn.namespace AS name', 'fn.start_line AS startLine', 'fn.end_line AS endLine')
            ->from(IndexStorageItemEnum::FILES_NAMESPACES, 'fn')
            ->join('fn', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fn.file_id')
            ->andWhere('fi.path = ?')
            ->setParameter(0, $filePath)
            ->execute()
            ->fetchAll();
    }

    /**
     * @inheritDoc
     */
    public function getUseStatementsForFile($filePath)
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('fni.name', 'fni.alias', 'fni.line')
            ->from(IndexStorageItemEnum::FILES_NAMESPACES_IMPORTS, 'fni')
            ->join('fni', IndexStorageItemEnum::FILES_NAMESPACES, 'fn', 'fn.id = fni.files_namespace_id')
            ->join('fn', IndexStorageItemEnum::FILES, 'fi', 'fi.id = fn.file_id')
            ->andWhere('fi.path = ?')
            ->setParameter(0, $filePath)
            ->execute()
            ->fetchAll();
    }

    /**
     * Starts a transaction.
     */
    public function beginTransaction()
    {
        $this->getConnection()->beginTransaction();
    }

    /**
     * Commits a transaction.
     */
    public function commitTransaction()
    {
        $this->getConnection()->commit();
    }

    /**
     * Rolls back a transaction.
     */
    public function rollbackTransaction()
    {
        $this->getConnection()->rollBack();
    }
}
