<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class FormDefinitionRecordMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'careforms_definitions', FormDefinitionRecord::class);
    }

    /** @return FormDefinitionRecord[] */
    public function findAllRecords(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('form_id', 'ASC')
            ->addOrderBy('form_version', 'DESC');

        return $this->findEntities($qb);
    }

    public function findLatestByForm(string $formId): ?FormDefinitionRecord
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))
            ->orderBy('form_version', 'DESC')
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            return null;
        }
    }

    public function findLatestAtOrBefore(string $formId, int $version): ?FormDefinitionRecord
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))
            ->andWhere($qb->expr()->lte('form_version', $qb->createNamedParameter($version)))
            ->orderBy('form_version', 'DESC')
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            return null;
        }
    }

    public function findByFormAndVersion(string $formId, int $version): ?FormDefinitionRecord
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))
            ->andWhere($qb->expr()->eq('form_version', $qb->createNamedParameter($version)))
            ->setMaxResults(1);

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            return null;
        }
    }

    public function replaceDefinition(FormDefinitionRecord $record, string $definitionJson, string $userId): FormDefinitionRecord
    {
        $record->setDefinitionJson($definitionJson);
        $record->setCreatedBy($userId);
        $record->setCreatedAt(time());
        return $this->update($record);
    }

    public function exists(string $formId, int $version): bool
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId)))
            ->andWhere($qb->expr()->eq('form_version', $qb->createNamedParameter($version)))
            ->setMaxResults(1);

        return $qb->executeQuery()->fetchOne() !== false;
    }
}
