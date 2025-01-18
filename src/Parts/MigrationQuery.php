<?php

namespace Vengine\Libraries\Migrations\Parts;

use Vengine\Libraries\Migrations\Storage\MigrationTypeStorage;

class MigrationQuery
{
    protected string $sqlQuery = '';

    protected Migration $phpMigration;

    protected string $migrationFile = '';

    protected string $fileHash = '';

    protected string $type = MigrationTypeStorage::NONE;

    public function __construct(string $file)
    {
        $this->migrationFile = $file;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setSqlQuery(string $sqlQuery): static
    {
        $this->type = MigrationTypeStorage::SQL;

        $this->sqlQuery = $sqlQuery;

        return $this;
    }

    public function setPhpMigration(Migration $phpMigration): static
    {
        $this->type = MigrationTypeStorage::PHP;

        $this->phpMigration = $phpMigration;

        return $this;
    }

    public function getPhpMigration(): Migration
    {
        return $this->phpMigration;
    }

    public function getMigrationFile(): string
    {
        return $this->migrationFile;
    }


    public function getSqlQuery(): string
    {
        return $this->sqlQuery;
    }

    public function getFileHash(): string
    {
        return $this->fileHash;
    }

    public function setFileHash(string $fileHash): static
    {
        $this->fileHash = $fileHash;

        return $this;
    }
}
