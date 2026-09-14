<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\FormDefinitionService;
use OCA\CareForms\Service\FormVersionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

final class FormDefinitionController extends Controller
{
    public function __construct(
        IRequest $request,
        private AccessService $accessService,
        private FormDefinitionService $definitions,
        private FormVersionService $formVersions,
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

        $result = [];
        foreach ($this->accessService->allowedForms($userId) as $formId) {
            try {
                $publishedVersion = $this->formVersions->publishedVersion($formId);
                $result[] = $this->definitions->getVersion($formId, $publishedVersion);
            } catch (\Throwable) {
                continue;
            }
        }

        return new JSONResponse($result);
    }

    #[NoAdminRequired]
    public function show(string $formId, int $version): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        if (!in_array($formId, $this->accessService->allowedForms($userId), true)) {
            return new JSONResponse(['message' => 'You do not have permission to access this form.'], Http::STATUS_FORBIDDEN);
        }

        try {
            return new JSONResponse($this->definitions->getVersion($formId, $version));
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        }
    }
}
