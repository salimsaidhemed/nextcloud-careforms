<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\FormVersionService;
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
        private FormVersionService $formVersions,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function me(): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        $forms = $this->accessService->allowedForms($userId);
        $published = [];
        foreach ($forms as $formId) $published[$formId] = $this->formVersions->publishedVersion($formId);
        return new JSONResponse([
            'userId' => $userId,
            'roles' => $this->accessService->roles($userId),
            'capabilities' => $this->accessService->capabilities($userId),
            'forms' => $forms,
            'formVersions' => $published,
            'canViewReports' => $this->accessService->canViewReports($userId),
            'canViewAudit' => $this->accessService->isCareFormsAdministrator($userId),
            'canManageForms' => $this->accessService->canManageForms($userId),
            'canSelectPatients' => $this->accessService->canSelectPatients($userId),
            'canViewPatients' => $this->accessService->canViewPatientDetails($userId),
            'canManagePatients' => $this->accessService->canManagePatients($userId),
        ]);
    }
}
