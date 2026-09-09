<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class AuditEvent extends Entity implements JsonSerializable
{
    protected string $userId = '';
    protected string $action = '';
    protected string $resourceType = '';
    protected ?int $resourceId = null;
    protected ?string $formId = null;
    protected string $outcome = 'success';
    protected string $metadata = '{}';
    protected int $createdAt = 0;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('resourceId', 'integer');
        $this->addType('createdAt', 'integer');
    }

    public function jsonSerialize(): array
    {
        $decoded = json_decode($this->metadata, true);

        return [
            'id' => $this->getId(),
            'userId' => $this->userId,
            'action' => $this->action,
            'resourceType' => $this->resourceType,
            'resourceId' => $this->resourceId,
            'formId' => $this->formId,
            'outcome' => $this->outcome,
            'metadata' => is_array($decoded) ? $decoded : [],
            'createdAt' => $this->createdAt,
        ];
    }
}
