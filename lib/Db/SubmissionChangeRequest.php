<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class SubmissionChangeRequest extends Entity implements JsonSerializable
{
    protected int $submissionId = 0;
    protected int $sequenceNumber = 0;
    protected string $requestedBy = '';
    protected string $instructions = '';
    protected string $status = 'open';
    protected int $createdAt = 0;
    protected ?int $respondedAt = null;
    protected ?int $resolvedAt = null;
    protected ?int $responseAddendumId = null;

    public function __construct()
    {
        foreach (['id', 'submissionId', 'sequenceNumber', 'createdAt', 'respondedAt', 'resolvedAt', 'responseAddendumId'] as $property) {
            $this->addType($property, 'integer');
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'submissionId' => $this->submissionId,
            'sequenceNumber' => $this->sequenceNumber,
            'requestedBy' => $this->requestedBy,
            'instructions' => $this->instructions,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'respondedAt' => $this->respondedAt,
            'resolvedAt' => $this->resolvedAt,
            'responseAddendumId' => $this->responseAddendumId,
        ];
    }
}
