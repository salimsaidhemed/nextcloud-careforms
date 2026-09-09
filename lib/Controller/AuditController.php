<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Db\AuditEvent;
use OCA\CareForms\Db\AuditEventMapper;
use OCA\CareForms\Service\AccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AuditController extends Controller
{
    public function __construct(
        IRequest $request,
        private AuditEventMapper $mapper,
        private AccessService $accessService,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(int $limit = 100): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        if (!$this->accessService->isCareFormsAdministrator($userId)) {
            return new JSONResponse(['message' => 'Audit log access requires CareForms administrator privileges.'], Http::STATUS_FORBIDDEN);
        }

        $limit = max(1, min($limit, 500));
        $events = $this->mapper->findRecent($limit);

        return new JSONResponse(array_map(
            static fn (AuditEvent $event): array => $event->jsonSerialize(),
            $events,
        ));
    }
}
