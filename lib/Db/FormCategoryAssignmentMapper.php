<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class FormCategoryAssignmentMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'careforms_form_cat', FormCategoryAssignment::class);
    }

    public function findByForm(string $formId): ?FormCategoryAssignment
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))->setMaxResults(1);
        try { return $this->findEntity($qb); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return null; }
    }
}
