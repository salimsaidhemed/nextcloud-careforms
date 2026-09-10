<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class SubmissionMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'careforms_submissions', Submission::class);
    }

    /** @return Submission[] */
    public function findAllByUser(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('updated_at', 'DESC');
        return $this->findEntities($qb);
    }

    /** @return Submission[] */
    public function findAllSubmitted(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter('submitted')))
            ->orderBy('submitted_at', 'DESC');
        return $this->findEntities($qb);
    }

    /** @return Submission[] */
    public function findAllReportable(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->in('status', [
                $qb->createNamedParameter('submitted'),
                $qb->createNamedParameter('approved'),
            ]))
            ->orderBy('submitted_at', 'DESC');
        return $this->findEntities($qb);
    }

    /** @return Submission[] */
    public function findAwaitingReview(): array
    {
        return $this->findAllSubmitted();
    }

    /** @return Submission[] */
    public function findAllByPatient(int $patientId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('patient_id', $qb->createNamedParameter($patientId)))
            ->orderBy('updated_at', 'DESC');
        return $this->findEntities($qb);
    }

    /** @throws DoesNotExistException @throws MultipleObjectsReturnedException */
    public function findById(int $id): Submission
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /** @throws DoesNotExistException @throws MultipleObjectsReturnedException */
    public function findByIdAndUser(int $id, string $userId): Submission
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        return $this->findEntity($qb);
    }
}
