<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\FormDefinitionService;
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

        return new JSONResponse(
            $this->definitions->forForms($this->accessService->allowedForms($userId)),
        );
    }
}
