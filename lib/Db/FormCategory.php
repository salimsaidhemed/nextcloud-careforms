<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class FormCategory extends Entity implements JsonSerializable
{
    protected string $categoryKey = '';
    protected string $label = '';
    protected int $sortOrder = 0;
    protected string $createdBy = '';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;

    public function __construct()
    {
        foreach (['id', 'sortOrder', 'createdAt', 'updatedAt'] as $property) $this->addType($property, 'integer');
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->categoryKey, 'label' => $this->label, 'sortOrder' => $this->sortOrder];
    }
}
