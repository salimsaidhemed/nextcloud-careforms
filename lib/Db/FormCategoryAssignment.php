<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\Entity;

class FormCategoryAssignment extends Entity
{
    protected string $formId = '';
    protected string $categoryKey = '';
    protected int $updatedAt = 0;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('updatedAt', 'integer');
    }
}
