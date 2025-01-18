<?php

namespace Vengine\Libraries\Migrations\Repository;

use Vengine\Libraries\Repository\AbstractRepository;
use Vengine\Libraries\Migrations\Entity\MigrationResult;

/**
 * @method MigrationResult|null createEntity(array $criteria = [])
 * @method MigrationResult|null createEntityByArray(array $data)
 */
class MigrationRepository extends AbstractRepository
{
    protected string $table = 'migrations';

    protected string $primaryKey = 'mig_id';

    protected array $columnMap = [
        'mig_file',
        'mig_hash',
        'mig_completed',
        'mig_query'
    ];

    protected string $entityClass = MigrationResult::class;
}
