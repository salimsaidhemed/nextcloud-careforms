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
    protected ?int $patientId = null;
    protected string $status = 'draft';
    protected string $data = '{}';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;
    protected ?int $submittedAt = null;
    protected ?string $reviewedBy = null;
    protected ?int $reviewedAt = null;
    protected ?string $reviewNote = null;
    protected ?string $signedBy = null;
    protected ?string $signerName = null;
    protected ?int $signedAt = null;
    protected ?string $signatureData = null;
    protected ?string $integrityHash = null;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('patientId', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
        $this->addType('submittedAt', 'integer');
        $this->addType('reviewedAt', 'integer');
        $this->addType('signedAt', 'integer');
    }

    public function jsonSerialize(): array
    {
        $decoded = json_decode($this->data, true);
        return [
            'id' => $this->getId(),
            'patientId' => $this->patientId,
            'formId' => $this->formId,
            'formVersion' => $this->formVersion,
            'status' => $this->status,
            'data' => is_array($decoded) ? $decoded : [],
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'submittedAt' => $this->submittedAt,
            'reviewedBy' => $this->reviewedBy,
            'reviewedAt' => $this->reviewedAt,
            'reviewNote' => $this->reviewNote,
            'signedBy' => $this->signedBy,
            'signerName' => $this->signerName,
            'signedAt' => $this->signedAt,
            'signatureData' => $this->signatureData,
            'integrityHash' => $this->integrityHash,
        ];
    }
}
