<?php

namespace Vengine\Libraries\Migrations;

use Vengine\Libraries\DBAL\Adapter;
use Vengine\Libraries\Console\ConsoleLogger;
use Vengine\Libraries\Migrations\Parts\Migration;
use Vengine\Libraries\Migrations\Parts\MigrationQuery;
use Vengine\Libraries\Migrations\Repository\MigrationRepository;
use Vengine\Libraries\Migrations\Storage\MigrationTypeStorage;
use Doctrine\DBAL\Exception;

class MigrationManager
{
    protected Adapter $db;

    protected MigrationRepository $repository;

    /**
     * @var array<MigrationQuery>
     */
    protected array $queryList = [];

    /**
     * @throws Exception
     */
    public function __construct(MigrationRepository $repository, Adapter $db)
    {
        ConsoleLogger::showMessage('create migration Manager');

        $this->repository = $repository;
        $this->db = $db;

        if (!empty($_SERVER['install.dir'])) {
            $installDir = $_SERVER['install.dir'];

            if (is_dir($installDir)) {
                $this->collectMigrationFiles($installDir, $this->repository->hasTable());

                $this->run();
            }
        }
    }

    /**
     * @throws Exception
     */
    public function run(): bool
    {
        foreach ($this->queryList as $query) {
            if (!$query instanceof MigrationQuery) {
                continue;
            }

            if ($this->migrationExecute($query)) {
                $this->removeMigrationByQuery($query);
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function collectMigrationFiles(string $dirPath = '', bool $checkHash = true, bool $force = false): bool
    {
        if (!is_dir($dirPath)) {
            return false;
        }

        $dir = scandir($dirPath);

        if (!$dir) {
            return false;
        }

        $failCountFile = 0;
        foreach ($dir as $file) {
            if (!is_file($dirPath . $file)) {
                continue;
            }

            $query = $this->createMigrationQuery($dirPath . $file, $checkHash);

            if ($query === null) {
                $failCountFile++;

                continue;
            }

            if ($force) {
                if ($this->migrationExecute($query)) {
                    $this->removeMigrationByQuery($query);
                }
            }
        }

        return $failCountFile === 0;
    }

    /**
     * @throws Exception
     */
    public function createMigrationQuery(string $queryLink, bool $checkHash = true): null|MigrationQuery
    {
        if (!file_exists($queryLink)) {
            return null;
        }

        $fileHash = sha1_file($queryLink);

        if ($checkHash && (!empty($this->queryList[$fileHash]) || $this->repository->has(['mig_hash' => $fileHash]))) {
            return null;
        }

        $query = (new MigrationQuery($queryLink))->setFileHash($fileHash);

        if (pathinfo($queryLink, PATHINFO_EXTENSION) === 'sql') {
            $sql = file_get_contents($queryLink);

            if (empty($sql)) {
                return null;
            }

            $query = $query->setSqlQuery($sql);

            $this->addMigrationQuery($query);

            return $query;
        }

        if (pathinfo($queryLink, PATHINFO_EXTENSION) === 'php') {
            $php = require($queryLink);

            if (!$php instanceof Migration) {
                if (is_array($php) && !empty($php['migrationClass']) && class_exists($php['migrationClass'])) {
                    $obj = new $php['migrationClass'];

                    if (!$obj instanceof Migration) {
                        return null;
                    }

                    return $query->setPhpMigration($obj->setDatabaseAdapter($this->db));
                }

                return null;
            }

            $query = $query->setPhpMigration($php->setDatabaseAdapter($this->db));

            $this->addMigrationQuery($query);

            return $query;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function migrationExecute(MigrationQuery $query): bool
    {
        if ($this->repository->hasTable() && $this->repository->has(['mig_hash' => $query->getFileHash()])) {
            return true;
        }

        if ($query->getType() === MigrationTypeStorage::NONE || empty($query->getMigrationFile())) {
            return false;
        }

        if ($query->getType() === MigrationTypeStorage::AUTO) {
            $query = $this->reCreateMigrationQuery($query->getMigrationFile());

            if ($query === null) {
                return false;
            }
        }

        ConsoleLogger::showMessage("execute migration: {$query->getFileHash()}");

        if ($query->getType() === MigrationTypeStorage::PHP) {
            $query->getPhpMigration()->up();

            $result = $this->repository->createEntityByArray([
                'mig_file' => $query->getMigrationFile(),
                'mig_hash' => $query->getFileHash(),
                'mig_query' => 'php_migration'
            ]);
        } else {
            $sqlQuery = $query->getSqlQuery();

            try {
                $sqlQueryResult = $this->db->getConnection()->executeStatement($query->getSqlQuery());
            } catch (Exception $e) {
                $msg = 'SQL src fail: ' . $e->getMessage();

                ConsoleLogger::showMessage($msg);

                $this->removeMigrationByQuery($query);

                $result = $this->repository->createEntityByArray([
                    'mig_file' => $query->getMigrationFile(),
                    'mig_hash' => $query->getFileHash(),
                    'mig_query' => $msg
                ]);

                $this->repository->saveByEntity($result);

                return false;
            }

            $result = $this->repository->createEntityByArray([
                'mig_file' => $query->getMigrationFile(),
                'mig_hash' => $query->getFileHash(),
                'mig_query' => "{$sqlQuery} ==> {$sqlQueryResult}"
            ]);
        }

        return $this->repository->saveByEntity($result);
    }

    public function addMigrationQuery(MigrationQuery $query): static
    {
        if (!empty($this->queryList[$query->getFileHash()])) {
            return $this;
        }

        $this->queryList[$query->getFileHash()] = $query;

        ConsoleLogger::showMessage("add migration: {$query->getFileHash()}");

        return $this;
    }

    public function removeMigrationByHash(string $hash): static
    {
        unset($this->queryList[$hash]);

        ConsoleLogger::showMessage("remove migration: {$hash}");

        return $this;
    }

    public function removeMigrationByQuery(MigrationQuery $query): static
    {
        return $this->removeMigrationByHash($query->getFileHash());
    }

    /**
     * @throws Exception
     */
    private function reCreateMigrationQuery(string $queryLink): null|MigrationQuery
    {
        return $this->createMigrationQuery($queryLink);
    }
}
