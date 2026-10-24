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
$errors = $validator->validate($definition, ['reference' => 'bad', 'score' => 10.2]);
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

fwrite(STDOUT, "SubmissionDataValidator tests passed.\n");
