<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Service/AddendumIntegrityService.php';

use OCA\CareForms\Service\AddendumIntegrityService;

$service = new AddendumIntegrityService();
$material = [
    'submissionId' => 42,
    'submissionIntegrityHash' => str_repeat('a', 64),
    'sequenceNumber' => 1,
    'authorId' => 'clinician',
    'reason' => 'Late laboratory result',
    'data' => '{"note":"Result received","changes":{"score":8}}',
    'createdAt' => 1793149200,
    'submittedAt' => 1793149260,
];
$hash = $service->hash($material);
if (!$service->verify($material, $hash)) throw new RuntimeException('Valid addendum integrity hash was rejected.');
$reordered = array_reverse($material, true);
if ($service->hash($reordered) !== $hash) throw new RuntimeException('Addendum integrity depends on object key order.');
$material['reason'] = 'Altered reason';
if ($service->verify($material, $hash)) throw new RuntimeException('Altered addendum content was accepted.');
if ($service->verify($material, null)) throw new RuntimeException('Missing addendum integrity hash was accepted.');

fwrite(STDOUT, "AddendumIntegrityService tests passed.\n");
