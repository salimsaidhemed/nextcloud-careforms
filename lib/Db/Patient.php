<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Patient extends Entity implements JsonSerializable
{
    protected string $medicalRecordNumber = '';
    protected string $firstName = '';
    protected string $lastName = '';
    protected ?string $dateOfBirth = null;
    protected string $status = 'active';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    public function displayName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'medicalRecordNumber' => $this->medicalRecordNumber,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'displayName' => $this->displayName(),
            'dateOfBirth' => $this->dateOfBirth,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
