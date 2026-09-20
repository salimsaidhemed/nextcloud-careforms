<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Service/FormSchemaValidator.php';

use OCA\CareForms\Service\FormSchemaValidator;

$validator = new FormSchemaValidator();

function definition(): array
{
    return [
        'schemaVersion' => 1,
        'id' => 'logic-test',
        'name' => 'Logic test',
        'category' => 'Tests',
        'version' => 1,
        'sections' => [[
            'id' => 'details',
            'label' => 'Details',
            'fields' => [
                ['id' => 'details_text', 'type' => 'text', 'label' => 'Details'],
                ['id' => 'has_details', 'type' => 'checkbox', 'label' => 'Has details'],
            ],
        ]],
    ];
}

function expectErrors(FormSchemaValidator $validator, array $definition, array $expected): void
{
    $errors = $validator->validate($definition);
    foreach ($expected as $message) {
        if (!in_array($message, $errors, true)) {
            throw new RuntimeException(sprintf(
                "Expected error not found:\n%s\nActual errors:\n%s",
                $message,
                implode("\n", $errors),
            ));
        }
    }
}

function expectValid(FormSchemaValidator $validator, array $definition): void
{
    $errors = $validator->validate($definition);
    if ($errors !== []) {
        throw new RuntimeException("Expected a valid definition. Errors:\n" . implode("\n", $errors));
    }
}

// Existing definitions remain valid.
expectValid($validator, definition());

// A field can use a forward reference to another field.
$case = definition();
$case['sections'][0]['fields'][0]['logic']['showWhen'] = [
    'field' => 'has_details',
    'operator' => 'equals',
    'value' => true,
];
expectValid($validator, $case);
$roundTrip = json_decode((string)json_encode($case, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
if ($roundTrip['sections'][0]['fields'][0]['logic'] !== $case['sections'][0]['fields'][0]['logic']) {
    throw new RuntimeException('JSON import/export did not preserve field logic.');
}

// Sections support the same visibility structure.
$case = definition();
$case['sections'][0]['logic']['showWhen'] = [
    'field' => 'has_details',
    'operator' => 'isNotEmpty',
];
expectValid($validator, $case);

// Comparison operators require a value.
$case = definition();
$case['sections'][0]['fields'][0]['logic']['showWhen'] = [
    'field' => 'has_details',
    'operator' => 'contains',
];
expectErrors($validator, $case, [
    'sections[0].fields[0].logic.showWhen.value is required for operator contains.',
]);

// Empty/not-empty operators reject a value.
$case = definition();
$case['sections'][0]['fields'][0]['logic']['showWhen'] = [
    'field' => 'has_details',
    'operator' => 'isEmpty',
    'value' => '',
];
expectErrors($validator, $case, [
    'sections[0].fields[0].logic.showWhen.value is not valid for operator isEmpty.',
]);

// Invalid operators and unknown references are rejected.
$case = definition();
$case['sections'][0]['fields'][0]['logic']['showWhen'] = [
    'field' => 'missing_field',
    'operator' => 'greaterThan',
];
expectErrors($validator, $case, [
    'sections[0].fields[0].logic.showWhen.operator must be one of: equals, notEquals, isEmpty, isNotEmpty, contains.',
    'sections[0].fields[0].logic.showWhen.field references unknown field ID "missing_field".',
]);

// Malformed logic is rejected without warnings from the reference pass.
$case = definition();
$case['sections'][0]['logic'] = 'invalid';
expectErrors($validator, $case, ['sections[0].logic must be an object.']);

$case = definition();
$case['sections'][0]['logic'] = [];
expectErrors($validator, $case, ['sections[0].logic.showWhen is required.']);

$case = definition();
$case['sections'][0]['logic']['showWhen'] = 'invalid';
expectErrors($validator, $case, ['sections[0].logic.showWhen must be an object.']);

$case = definition();
$case['sections'][0]['fields'] = 'invalid';
expectErrors($validator, $case, ['sections[0].fields must be a non-empty array.']);

fwrite(STDOUT, "FormSchemaValidator tests passed.\n");
