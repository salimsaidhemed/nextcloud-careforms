<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class FormVersion extends Entity implements JsonSerializable
{
    protected string $formId = '';
    protected int $versionNumber = 1;
    protected string $status = 'draft';
    protected string $createdBy = '';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;
    protected ?int $publishedAt = null;
    protected ?int $archivedAt = null;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('versionNumber', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
        $this->addType('publishedAt', 'integer');
        $this->addType('archivedAt', 'integer');
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'formId' => $this->formId,
            'version' => $this->versionNumber,
            'status' => $this->status,
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'publishedAt' => $this->publishedAt,
            'archivedAt' => $this->archivedAt,
        ];
    }
}
