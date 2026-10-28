<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class SubmissionChangeRequestMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'careforms_change_req', SubmissionChangeRequest::class);
    }

    /** @return SubmissionChangeRequest[] */
    public function findBySubmission(int $submissionId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId)))
            ->orderBy('sequence_number', 'ASC');
        return $this->findEntities($qb);
    }

    public function findById(int $id): SubmissionChangeRequest
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    public function nextSequence(int $submissionId): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('sequence_number')->from($this->getTableName())
            ->where($qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId)))
            ->orderBy('sequence_number', 'DESC')->setMaxResults(1);
        $result = $qb->executeQuery()->fetchOne();
        return ($result === false ? 0 : (int)$result) + 1;
    }
}
