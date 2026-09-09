<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Submission extends Entity implements JsonSerializable
{
    protected string $userId = '';
    protected string $formId = '';
    protected string $formVersion = '';
    protected string $status = 'draft';
    protected string $data = '{}';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;
    protected ?int $submittedAt = null;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
        $this->addType('submittedAt', 'integer');
    }

    public function jsonSerialize(): array
    {
        $decoded = json_decode($this->data, true);

        return [
            'id' => $this->getId(),
            'formId' => $this->formId,
            'formVersion' => $this->formVersion,
            'status' => $this->status,
            'data' => is_array($decoded) ? $decoded : [],
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'submittedAt' => $this->submittedAt,
        ];
    }
}
