<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\FormDefinitionService;
use OCA\CareForms\Service\FormCategoryService;
use OCA\CareForms\Service\FormVersionService;
use OCA\CareForms\Service\PaginationService;
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
        private FormCategoryService $categories,
        private FormVersionService $formVersions,
        private PaginationService $pagination,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(int $page = 1, int $pageSize = PaginationService::DEFAULT_PAGE_SIZE, string $search = ''): JSONResponse
    {
        $userId = $this->accessService->currentUserId();

        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        }

        $formIds = $this->accessService->allowedForms($userId);
        sort($formIds, SORT_STRING);
        $search = mb_strtolower(mb_substr(trim($search), 0, 128));
        if ($search !== '') {
            $formIds = array_values(array_filter($formIds, function (string $formId) use ($search): bool {
                if (str_contains(mb_strtolower($formId), $search)) return true;
                try {
                    $definition = $this->definitions->getVersion($formId, $this->formVersions->publishedVersion($formId));
                    $category = $this->categories->forForm($formId, is_string($definition['category'] ?? null) ? $definition['category'] : null);
                    return str_contains(mb_strtolower((string)($definition['name'] ?? '')), $search)
                        || str_contains(mb_strtolower($category->getLabel()), $search);
                } catch (\Throwable) {
                    return false;
                }
            }));
        }

        $paging = $this->pagination->normalize($page, $pageSize);
        $total = count($formIds);
        $result = [];
        foreach (array_slice($formIds, $paging['offset'], $paging['pageSize']) as $formId) {
            try {
                $publishedVersion = $this->formVersions->publishedVersion($formId);
                $result[] = $this->categories->decorate($this->definitions->getVersion($formId, $publishedVersion));
            } catch (\Throwable) {
                continue;
            }
        }

        return new JSONResponse($this->pagination->response($result, $paging['page'], $paging['pageSize'], $total));
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
