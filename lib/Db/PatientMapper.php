<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class PatientMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'careforms_patients', Patient::class);
    }

    /** @return Patient[] */
    public function findAll(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())->orderBy('last_name', 'ASC')->addOrderBy('first_name', 'ASC');
        return $this->findEntities($qb);
    }

    /** @throws DoesNotExistException @throws MultipleObjectsReturnedException */
    public function find(int $id): Patient
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }
}
