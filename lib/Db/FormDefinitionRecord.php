<?php

declare(strict_types=1);

namespace OCA\CareForms\Db;

use OCP\AppFramework\Db\Entity;

class FormDefinitionRecord extends Entity
{
    protected string $formId = '';
    protected int $formVersion = 0;
    protected int $schemaVersion = 0;
    protected string $definitionJson = '';
    protected string $createdBy = '';
    protected int $createdAt = 0;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('formVersion', 'integer');
        $this->addType('schemaVersion', 'integer');
        $this->addType('createdAt', 'integer');
    }
}
