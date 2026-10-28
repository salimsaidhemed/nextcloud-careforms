<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class PaginationService
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    /** @return array{page: int, pageSize: int, offset: int} */
    public function normalize(int $page, int $pageSize): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(self::MAX_PAGE_SIZE, $pageSize));
        return ['page' => $page, 'pageSize' => $pageSize, 'offset' => ($page - 1) * $pageSize];
    }

    /** @param list<mixed> $items */
    public function response(array $items, int $page, int $pageSize, int $total): array
    {
        $totalPages = $total === 0 ? 0 : (int)ceil($total / $pageSize);
        return [
            'items' => array_values($items),
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $totalPages,
                'hasPrevious' => $page > 1,
                'hasNext' => $page < $totalPages,
                'previousPage' => $page > 1 ? $page - 1 : null,
                'nextPage' => $page < $totalPages ? $page + 1 : null,
            ],
        ];
    }
}
