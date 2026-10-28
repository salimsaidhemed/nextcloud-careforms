<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Service/PaginationService.php';

use OCA\CareForms\Service\PaginationService;

$service = new PaginationService();
$normalized = $service->normalize(-2, 1000);
if ($normalized !== ['page' => 1, 'pageSize' => 100, 'offset' => 0]) {
    throw new RuntimeException('Pagination bounds were not normalized.');
}

$response = $service->response(['a', 'b'], 2, 2, 5);
if ($response['pagination'] !== [
    'page' => 2,
    'pageSize' => 2,
    'total' => 5,
    'totalPages' => 3,
    'hasPrevious' => true,
    'hasNext' => true,
    'previousPage' => 1,
    'nextPage' => 3,
]) {
    throw new RuntimeException('Pagination metadata is incorrect.');
}

$empty = $service->response([], 1, 25, 0);
if ($empty['pagination']['totalPages'] !== 0 || $empty['pagination']['hasNext']) {
    throw new RuntimeException('Empty pagination metadata is incorrect.');
}

echo "PaginationService tests passed.\n";
