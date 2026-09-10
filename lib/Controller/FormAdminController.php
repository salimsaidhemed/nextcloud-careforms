<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AuditService;
use OCA\CareForms\Service\FormVersionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FormAdminController extends Controller
{
    private const FORMS = [
        AccessService::FORM_HOME_HEALTH_AIDE => ['name' => 'Home Health Aide Note', 'category' => 'Home Health'],
        AccessService::FORM_NURSES_PROGRESS_NOTE => ['name' => 'Nurses Progress Note', 'category' => 'Nursing'],
    ];

    public function __construct(
        IRequest $request,
        private AccessService $accessService,
        private AuditService $auditService,
        private FormVersionService $formVersions,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canManageForms($userId)) return new JSONResponse(['message' => 'You do not have permission to manage forms.'], Http::STATUS_FORBIDDEN);

        $forms = [];
        foreach (self::FORMS as $formId => $metadata) {
            $versions = array_map(static fn ($version): array => $version->jsonSerialize(), $this->formVersions->versions($formId));
            $forms[] = array_merge([
                'id' => $formId,
                'enabled' => $this->accessService->isFormEnabled($formId),
                'publishedVersion' => $this->formVersions->publishedVersion($formId),
                'versions' => $versions,
            ], $metadata);
        }
        return new JSONResponse($forms);
    }

    #[NoAdminRequired]
    public function update(string $formId, bool $enabled): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!isset(self::FORMS[$formId])) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        $this->accessService->setFormEnabled($formId, $enabled);
        $this->auditService->log($userId, 'ADMIN_SETTING_CHANGE', 'form', null, $formId, 'success', ['setting' => 'enabled', 'enabled' => $enabled]);
        return new JSONResponse(['id' => $formId, 'enabled' => $this->accessService->isFormEnabled($formId)]);
    }

    #[NoAdminRequired]
    public function createDraft(string $formId): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!isset(self::FORMS[$formId])) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        $draft = $this->formVersions->createDraft($formId, $userId);
        $this->auditService->log($userId, 'FORM_CREATE', 'form_version', $draft->getId(), $formId, 'success', ['version' => $draft->getVersionNumber()]);
        return new JSONResponse($draft->jsonSerialize(), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function publish(string $formId, int $version): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!isset(self::FORMS[$formId])) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        try { $published = $this->formVersions->publish($formId, $version); }
        catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND); }
        catch (\LogicException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT); }
        $this->auditService->log($userId, 'FORM_PUBLISH', 'form_version', $published->getId(), $formId, 'success', ['version' => $version]);
        return new JSONResponse($published->jsonSerialize());
    }

    #[NoAdminRequired]
    public function archive(string $formId, int $version): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!isset(self::FORMS[$formId])) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        try { $archived = $this->formVersions->archiveDraft($formId, $version); }
        catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND); }
        catch (\LogicException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT); }
        $this->auditService->log($userId, 'FORM_UPDATE', 'form_version', $archived->getId(), $formId, 'success', ['version' => $version, 'status' => 'archived']);
        return new JSONResponse($archived->jsonSerialize());
    }

    private function requireManager(): string|JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canManageForms($userId)) return new JSONResponse(['message' => 'You do not have permission to manage forms.'], Http::STATUS_FORBIDDEN);
        return $userId;
    }
}
