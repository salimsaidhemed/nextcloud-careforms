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
    public function findActivePage(string $search, int $limit, int $offset): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
            ->orderBy('last_name', 'ASC')
            ->addOrderBy('first_name', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $this->applySearch($qb, $search);

        return $this->findEntities($qb);
    }

    public function countActive(string $search): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter('active')));
        $this->applySearch($qb, $search);
        return (int)$qb->executeQuery()->fetchOne();
    }

    private function applySearch(\OCP\DB\QueryBuilder\IQueryBuilder $qb, string $search): void
    {
        $search = trim($search);
        if ($search === '') return;
        foreach (preg_split('/\s+/', $search) ?: [] as $token) {
            $term = $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($token) . '%');
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->iLike('first_name', $term),
                $qb->expr()->iLike('last_name', $term),
                $qb->expr()->iLike('medical_record_number', $term),
            ));
        }
    }

    /** @throws DoesNotExistException @throws MultipleObjectsReturnedException */
    public function find(int $id): Patient
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

        return $this->findEntity($qb);
    }
}
