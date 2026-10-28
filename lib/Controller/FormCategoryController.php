<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AuditService;
use OCA\CareForms\Service\FormCategoryService;
use OCA\CareForms\Service\FormDefinitionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FormCategoryController extends Controller
{
    public function __construct(
        IRequest $request,
        private AccessService $access,
        private AuditService $audit,
        private FormCategoryService $categories,
        private FormDefinitionService $definitions,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        $userId = $this->manager();
        if ($userId instanceof JSONResponse) return $userId;
        return new JSONResponse(array_map(static fn ($item): array => $item->jsonSerialize(), $this->categories->all()));
    }

    #[NoAdminRequired]
    public function create(string $label): JSONResponse
    {
        $userId = $this->manager();
        if ($userId instanceof JSONResponse) return $userId;
        try { $item = $this->categories->create($label, $userId); }
        catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST); }
        catch (\Throwable) { return new JSONResponse(['message' => 'Could not create the category. Its label may already be in use.'], Http::STATUS_CONFLICT); }
        $this->audit->log($userId, 'FORM_CATEGORY_CREATE', 'form_category', $item->getId(), null, 'success', ['categoryId' => $item->getCategoryKey(), 'label' => $item->getLabel()]);
        return new JSONResponse($item->jsonSerialize(), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(string $categoryId, string $label, int $sortOrder): JSONResponse
    {
        $userId = $this->manager();
        if ($userId instanceof JSONResponse) return $userId;
        try { $item = $this->categories->update($categoryId, $label, $sortOrder); }
        catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST); }
        $this->audit->log($userId, 'FORM_CATEGORY_UPDATE', 'form_category', $item->getId(), null, 'success', ['categoryId' => $categoryId, 'label' => $item->getLabel(), 'sortOrder' => $item->getSortOrder()]);
        return new JSONResponse($item->jsonSerialize());
    }

    #[NoAdminRequired]
    public function assign(string $formId, string $categoryId): JSONResponse
    {
        $userId = $this->manager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!$this->definitions->exists($formId)) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        try { $item = $this->categories->assign($formId, $categoryId); }
        catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST); }
        $this->audit->log($userId, 'FORM_CATEGORY_ASSIGN', 'form', null, $formId, 'success', ['categoryId' => $categoryId]);
        return new JSONResponse(['formId' => $formId, 'category' => $item->jsonSerialize()]);
    }

    private function manager(): string|JSONResponse
    {
        $userId = $this->access->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->access->canManageForms($userId)) return new JSONResponse(['message' => 'You do not have permission to manage form categories.'], Http::STATUS_FORBIDDEN);
        return $userId;
    }
}
