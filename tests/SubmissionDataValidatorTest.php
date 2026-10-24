<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Service/SubmissionDataValidator.php';

use OCA\CareForms\Service\SubmissionDataValidator;

$validator = new SubmissionDataValidator();
$definition = [
    'sections' => [[
        'id' => 'assessment',
        'fields' => [
            ['id' => 'has_pain', 'type' => 'checkbox', 'label' => 'Has pain'],
            [
                'id' => 'pain_notes',
                'type' => 'textarea',
                'label' => 'Pain notes',
                'logic' => ['requiredWhen' => ['field' => 'has_pain', 'operator' => 'equals', 'value' => true]],
            ],
            ['id' => 'visit_date', 'type' => 'date', 'label' => 'Visit date', 'required' => true],
        ],
    ]],
];

if ($validator->validate($definition, ['has_pain' => false, 'visit_date' => '2026-09-20']) !== []) {
    throw new RuntimeException('Inactive requiredWhen must not require the field.');
}
$errors = $validator->validate($definition, ['has_pain' => true, 'visit_date' => '2026-09-20']);
if ($errors !== ['Pain notes is required.']) throw new RuntimeException('Active requiredWhen was not enforced.');
$errors = $validator->validate($definition, ['has_pain' => true, 'pain_notes' => 'Documented', 'visit_date' => '']);
if ($errors !== ['Visit date is required.']) throw new RuntimeException('Static required field was not enforced.');

$definition['sections'][0]['fields'][1]['logic']['showWhen'] = ['field' => 'has_pain', 'operator' => 'equals', 'value' => false];
if ($validator->validate($definition, ['has_pain' => true, 'visit_date' => '2026-09-20']) !== []) {
    throw new RuntimeException('Hidden fields must not be required.');
}

$definition = [
    'sections' => [[
        'id' => 'validation',
        'fields' => [
            [
                'id' => 'reference', 'type' => 'text', 'label' => 'Reference',
                'minLength' => 3, 'maxLength' => 8,
                'pattern' => '[A-Z]{2}-[0-9]{2}',
                'validationMessage' => 'Use format AB-12.',
            ],
            ['id' => 'score', 'type' => 'number', 'label' => 'Score', 'min' => 0, 'max' => 10, 'step' => 0.5],
        ],
    ]],
];
if ($validator->validate($definition, ['reference' => '', 'score' => '']) !== []) {
    throw new RuntimeException('Empty optional values must not fail advanced constraints.');
}
$errors = $validator->validate($definition, ['reference' => 'bad', 'score' => 9.2]);
if ($errors !== ['Use format AB-12.', 'Score must use increments of 0.5.']) {
    throw new RuntimeException('Pattern and numeric step constraints were not enforced.');
}
$errors = $validator->validate($definition, ['reference' => 'AB-12345', 'score' => 11]);
if ($errors !== ['Reference must not exceed 8 characters.', 'Use format AB-12.', 'Score must not exceed 10.']) {
    throw new RuntimeException('Maximum and pattern constraints were not enforced.');
}

$definition['sections'][0]['fields'][0]['logic']['showWhen'] = ['field' => 'toggle', 'operator' => 'equals', 'value' => true];
$definition['sections'][0]['fields'][] = ['id' => 'toggle', 'type' => 'checkbox', 'label' => 'Toggle'];
if ($validator->validate($definition, ['toggle' => false, 'reference' => 'bad', 'score' => 5]) !== []) {
    throw new RuntimeException('Hidden fields must not fail advanced constraints.');
}

$definition = [
    'sections' => [[
        'id' => 'visit',
        'fields' => [
            ['id' => 'start_date', 'type' => 'date', 'label' => 'Start date'],
            [
                'id' => 'end_date', 'type' => 'date', 'label' => 'End date',
                'validation' => ['compare' => [
                    'field' => 'start_date',
                    'operator' => 'greaterThanOrEqual',
                    'message' => 'End date must be on or after the start date.',
                ]],
            ],
        ],
    ]],
];
if ($validator->validate($definition, ['start_date' => '2026-10-24', 'end_date' => '2026-10-25']) !== []) {
    throw new RuntimeException('A valid cross-field comparison was rejected.');
}
$errors = $validator->validate($definition, ['start_date' => '2026-10-24', 'end_date' => '2026-10-23']);
if ($errors !== ['End date must be on or after the start date.']) {
    throw new RuntimeException('An invalid cross-field comparison was not enforced.');
}
if ($validator->validate($definition, ['start_date' => '', 'end_date' => '']) !== []) {
    throw new RuntimeException('Empty optional comparison values must remain valid.');
}

$operatorCases = [
    'equals' => [5, 5, 6],
    'notEquals' => [5, 6, 6],
    'greaterThan' => [6, 5, 5],
    'greaterThanOrEqual' => [5, 5, 4],
    'lessThan' => [4, 5, 5],
    'lessThanOrEqual' => [5, 5, 6],
];
foreach ($operatorCases as $operator => [$validActual, $expected, $invalidActual]) {
    $definition['sections'][0]['fields'][0] = ['id' => 'source', 'type' => 'number', 'label' => 'Source'];
    $definition['sections'][0]['fields'][1] = [
        'id' => 'target', 'type' => 'number', 'label' => 'Target',
        'validation' => ['compare' => ['field' => 'source', 'operator' => $operator, 'message' => 'Comparison failed.']],
    ];
    $actualErrors = $validator->validate($definition, ['source' => $expected, 'target' => $validActual]);
    if ($actualErrors !== []) throw new RuntimeException('Operator ' . $operator . ' rejected a valid comparison.');
    $actualErrors = $validator->validate($definition, ['source' => $expected, 'target' => $invalidActual]);
    if ($actualErrors !== ['Comparison failed.']) throw new RuntimeException('Operator ' . $operator . ' accepted an invalid comparison.');
}

fwrite(STDOUT, "SubmissionDataValidator tests passed.\n");
