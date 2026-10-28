<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class SubmissionAddendum extends Entity implements JsonSerializable
{
    protected int $submissionId = 0;
    protected int $sequenceNumber = 0;
    protected string $authorId = '';
    protected string $reason = '';
    protected string $data = '{}';
    protected string $status = 'draft';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;
    protected ?int $submittedAt = null;
    protected ?string $reviewedBy = null;
    protected ?int $reviewedAt = null;
    protected ?string $reviewNote = null;
    protected ?string $integrityHash = null;

    public function __construct()
    {
        foreach (['id', 'submissionId', 'sequenceNumber', 'createdAt', 'updatedAt', 'submittedAt', 'reviewedAt'] as $property) {
            $this->addType($property, 'integer');
        }
    }

    public function jsonSerialize(): array
    {
        $decoded = json_decode($this->data, true);
        return [
            'id' => $this->getId(),
            'submissionId' => $this->submissionId,
            'sequenceNumber' => $this->sequenceNumber,
            'authorId' => $this->authorId,
            'reason' => $this->reason,
            'data' => is_array($decoded) ? $decoded : [],
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'submittedAt' => $this->submittedAt,
            'reviewedBy' => $this->reviewedBy,
            'reviewedAt' => $this->reviewedAt,
            'reviewNote' => $this->reviewNote,
            'integrityHash' => $this->integrityHash,
        ];
    }
}
