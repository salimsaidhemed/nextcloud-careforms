<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AuditEventMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'careforms_audit', AuditEvent::class);
    }

    /** @return AuditEvent[] */
    public function findRecent(int $limit = 100): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit);

        return $this->findEntities($qb);
    }
}
