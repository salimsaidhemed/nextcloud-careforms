<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class FormVersionMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'careforms_form_versions', FormVersion::class);
    }

    /** @return FormVersion[] */
    public function findAllByForm(string $formId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))
            ->orderBy('version', 'DESC');
        return $this->findEntities($qb);
    }

    /** @throws DoesNotExistException @throws MultipleObjectsReturnedException */
    public function findByFormAndVersion(string $formId, int $version): FormVersion
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))
            ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version)));
        return $this->findEntity($qb);
    }

    public function findPublished(string $formId): ?FormVersion
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))
            ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('published')))
            ->setMaxResults(1);
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            return null;
        }
    }

    public function findDraft(string $formId): ?FormVersion
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())
            ->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))
            ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('draft')))
            ->setMaxResults(1);
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            return null;
        }
    }

    public function nextVersion(string $formId): int
    {
        $versions = $this->findAllByForm($formId);
        if ($versions === []) return 1;
        return max(array_map(static fn (FormVersion $v): int => $v->getVersion(), $versions)) + 1;
    }
}
