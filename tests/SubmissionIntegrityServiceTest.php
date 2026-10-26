<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Service/SubmissionIntegrityService.php';

use OCA\CareForms\Service\SubmissionIntegrityService;

$service = new SubmissionIntegrityService();
$definition = [
    'schemaVersion' => 1,
    'id' => 'integrity-test',
    'version' => 2,
    'sections' => [['id' => 'main', 'fields' => [['id' => 'score', 'type' => 'number']]]],
];
$sameDefinitionDifferentKeyOrder = [
    'sections' => [['fields' => [['type' => 'number', 'id' => 'score']], 'id' => 'main']],
    'version' => 2,
    'id' => 'integrity-test',
    'schemaVersion' => 1,
];
if ($service->definitionHash($definition) !== $service->definitionHash($sameDefinitionDifferentKeyOrder)) {
    throw new RuntimeException('Canonical definition hashing depends on object key order.');
}

$record = [
    'formId' => 'integrity-test',
    'formVersion' => '2',
    'patientId' => 14,
    'data' => '{"score":7}',
    'signedBy' => 'clinician',
    'signerName' => 'Care Clinician',
    'signedAt' => 1792972800,
    'signatureData' => 'data:image/png;base64,c2lnbmF0dXJl',
];
$created = $service->create($definition, $record);
$signed = array_merge($record, $created);
$verification = $service->verify($sameDefinitionDifferentKeyOrder, $signed);
if ($verification['status'] !== 'valid' || $verification['valid'] !== true) throw new RuntimeException('Valid integrity v2 record was rejected.');

foreach (['data' => '{"score":8}', 'signatureData' => 'data:image/png;base64,YWx0ZXJlZA==', 'signerName' => 'Altered Name'] as $key => $altered) {
    $tampered = $signed;
    $tampered[$key] = $altered;
    if ($service->verify($definition, $tampered)['status'] !== 'invalid') throw new RuntimeException('Tampered ' . $key . ' was not rejected.');
}
$changedDefinition = $definition;
$changedDefinition['sections'][0]['fields'][0]['label'] = 'Changed after signing';
if ($service->verify($changedDefinition, $signed)['message'] !== 'The captured form definition no longer matches this signed submission.') {
    throw new RuntimeException('Changed historical definition was not detected.');
}

$legacy = $record;
$legacy['integrityHash'] = hash('sha256', implode('|', [
    $record['formId'], $record['formVersion'], (string)$record['patientId'], $record['data'], $record['signedBy'], (string)$record['signedAt'],
]));
if ($service->verify($definition, $legacy)['status'] !== 'legacy-valid') throw new RuntimeException('Valid legacy hash was rejected.');
$legacy['data'] = '{"score":9}';
if ($service->verify($definition, $legacy)['status'] !== 'invalid') throw new RuntimeException('Altered legacy record was accepted.');
if ($service->verify($definition, $record)['status'] !== 'unsigned') throw new RuntimeException('Unsigned record status is incorrect.');

fwrite(STDOUT, "SubmissionIntegrityService tests passed.\n");
