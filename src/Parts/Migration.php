<?php

namespace Vengine\Libraries\Migrations\Parts;

use Vengine\Libraries\DBAL\Adapter;

abstract class Migration
{
    protected Adapter $db;

    public function setDatabaseAdapter(Adapter $db): static
    {
        $this->db = $db;

        return $this;
    }

    abstract public function up(): void;
}
