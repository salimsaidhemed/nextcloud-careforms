<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AuditService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FormAdminController extends Controller
{
    private const FORMS = [
        AccessService::FORM_HOME_HEALTH_AIDE => [
            'name' => 'Home Health Aide Note',
            'category' => 'Home Health',
            'version' => '1',
        ],
        AccessService::FORM_NURSES_PROGRESS_NOTE => [
            'name' => 'Nurses Progress Note',
            'category' => 'Nursing',
            'version' => '1',
        ],
    ];

    public function __construct(
        IRequest $request,
        private AccessService $accessService,
        private AuditService $auditService,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }
        if (!$this->accessService->canManageForms($userId)) {
            return new JSONResponse(['message' => 'You do not have permission to manage forms.'], Http::STATUS_FORBIDDEN);
        }

        $forms = [];
        foreach (self::FORMS as $formId => $metadata) {
            $forms[] = array_merge([
                'id' => $formId,
                'enabled' => $this->accessService->isFormEnabled($formId),
            ], $metadata);
        }

        return new JSONResponse($forms);
    }

    #[NoAdminRequired]
    public function update(string $formId, bool $enabled): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }
        if (!$this->accessService->canManageForms($userId)) {
            return new JSONResponse(['message' => 'You do not have permission to manage forms.'], Http::STATUS_FORBIDDEN);
        }
        if (!isset(self::FORMS[$formId])) {
            return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        }

        $this->accessService->setFormEnabled($formId, $enabled);
        $this->auditService->log(
            $userId,
            'ADMIN_SETTING_CHANGE',
            'form',
            null,
            $formId,
            'success',
            ['setting' => 'enabled', 'enabled' => $enabled],
        );

        return new JSONResponse([
            'id' => $formId,
            'enabled' => $this->accessService->isFormEnabled($formId),
        ]);
    }
}
