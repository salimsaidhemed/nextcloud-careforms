<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class FormCategoryMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'careforms_categories', FormCategory::class);
    }

    /** @return FormCategory[] */
    public function findAllOrdered(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())->orderBy('sort_order', 'ASC')->addOrderBy('label', 'ASC');
        return $this->findEntities($qb);
    }

    public function findByKey(string $key): ?FormCategory
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('category_key', $qb->createNamedParameter($key)))->setMaxResults(1);
        try { return $this->findEntity($qb); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return null; }
    }
}
