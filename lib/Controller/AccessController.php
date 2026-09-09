<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AccessController extends Controller
{
    public function __construct(
        IRequest $request,
        private AccessService $accessService,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function me(): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        return new JSONResponse([
            'userId' => $userId,
            'roles' => $this->accessService->roles($userId),
            'capabilities' => $this->accessService->capabilities($userId),
            'forms' => $this->accessService->allowedForms($userId),
            'canViewReports' => $this->accessService->canViewReports($userId),
        ]);
    }
}
