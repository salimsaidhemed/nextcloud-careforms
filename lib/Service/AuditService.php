<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

use OCA\CareForms\Db\AuditEvent;
use OCA\CareForms\Db\AuditEventMapper;

class AuditService
{
    public function __construct(private AuditEventMapper $mapper)
    {
    }

    public function log(
        string $userId,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?string $formId = null,
        string $outcome = 'success',
        array $metadata = [],
    ): void {
        $event = new AuditEvent();
        $event->setUserId($userId);
        $event->setAction($action);
        $event->setResourceType($resourceType);
        $event->setResourceId($resourceId);
        $event->setFormId($formId);
        $event->setOutcome($outcome);
        $event->setMetadata(json_encode($metadata, JSON_THROW_ON_ERROR));
        $event->setCreatedAt(time());

        $this->mapper->insert($event);
    }
}
