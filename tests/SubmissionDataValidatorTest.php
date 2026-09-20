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

fwrite(STDOUT, "SubmissionDataValidator tests passed.\n");
