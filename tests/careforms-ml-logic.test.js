'use strict';

var assert = require('assert');
global.window = {CareForms: {}};
require('../js/cross-field-validation.js');
require('../js/calculation-engine.js');
require('../js/form-designer.js');

var ml = window.CareForms.FormDesigner;
var definition = {
    schemaVersion: 1,
    id: 'logic-test',
    name: 'Logic test',
    category: 'Tests',
    version: 1,
    sections: [
        {
            id: 'screening',
            label: 'Screening',
            fields: [
                {id: 'has_pain', type: 'checkbox', label: 'Has pain'},
                {
                    id: 'pain_score',
                    type: 'number',
                    label: 'Pain score',
                    min: 0,
                    max: 10,
                    step: 0.5,
                    validation: {
                        compare: {
                            field: 'baseline_score',
                            operator: 'greaterThanOrEqual',
                            message: 'Pain score must be at least the baseline score.'
                        }
                    },
                    logic: {
                        showWhen: {field: 'has_pain', operator: 'equals', value: true},
                        requiredWhen: {field: 'has_pain', operator: 'equals', value: true}
                    }
                },
                {id: 'baseline_score', type: 'number', label: 'Baseline score'},
                {
                    id: 'combined_score', type: 'number', label: 'Combined score', readOnly: true,
                    calculation: {expression: 'pain_score + baseline_score', precision: 1}
                }
            ]
        },
        {
            id: 'details',
            label: 'Details',
            logic: {showWhen: {field: 'pain_score', operator: 'notEquals', value: 0}},
            fields: [
                {
                    id: 'pain_notes',
                    type: 'textarea',
                    label: 'Pain notes',
                    minLength: 5,
                    maxLength: 200,
                    pattern: '[A-Za-z ]+',
                    validationMessage: 'Use letters and spaces only.',
                    logic: {showWhen: {field: 'pain_score', operator: 'isNotEmpty'}}
                }
            ]
        }
    ]
};

var source = ml.toCareFormML(definition);
assert.ok(source.includes('show_when field="has_pain" operator=equals value=true'));
assert.ok(source.includes('required_when field="has_pain" operator=equals value=true'));
assert.ok(source.includes('min=0 max=10 step=0.5'));
assert.ok(source.includes('min_length=5 max_length=200'));
assert.ok(source.includes('pattern "[A-Za-z ]+"'));
assert.ok(source.includes('validation_message "Use letters and spaces only."'));
assert.ok(source.includes('compare field="baseline_score" operator=greaterThanOrEqual message="Pain score must be at least the baseline score."'));
assert.ok(source.includes('calculate expression="pain_score + baseline_score" precision=1'));
assert.ok(source.includes('show_when field="pain_score" operator=notEquals value=0'));
assert.ok(source.includes('show_when field="pain_score" operator=isNotEmpty'));

var parsed = ml.fromCareFormML(source, definition);
assert.deepStrictEqual(parsed.sections[0].fields[1].logic, definition.sections[0].fields[1].logic);
assert.deepStrictEqual(parsed.sections[1].logic, definition.sections[1].logic);
assert.deepStrictEqual(parsed.sections[1].fields[0].logic, definition.sections[1].fields[0].logic);
assert.strictEqual(parsed.sections[0].fields[1].step, 0.5);
assert.deepStrictEqual(parsed.sections[0].fields[1].validation, definition.sections[0].fields[1].validation);
assert.deepStrictEqual(parsed.sections[0].fields[3].calculation, definition.sections[0].fields[3].calculation);
assert.strictEqual(parsed.sections[1].fields[0].minLength, 5);
assert.strictEqual(parsed.sections[1].fields[0].validationMessage, 'Use letters and spaces only.');

var forward = [
    'form "Forward reference"',
    '  id "forward-reference"',
    '  version 1',
    '  schema 1',
    '  category "Tests"',
    '  section "First" id="first"',
    '    field "Details" id="details" type=text',
    '      show_when field="controller" operator=contains value="yes"',
    '  section "Second" id="second"',
    '    field "Controller" id="controller" type=text'
].join('\n');
assert.strictEqual(ml.fromCareFormML(forward, definition).sections[0].fields[0].logic.showWhen.value, 'yes');

function expectError(text, message) {
    assert.throws(function () { ml.fromCareFormML(text, definition); }, function (error) {
        return error.message.indexOf(message) !== -1;
    });
}

expectError(forward.replace('operator=contains value="yes"', 'operator=contains'), 'requires value=');
expectError(forward.replace('operator=contains value="yes"', 'operator=isEmpty value="yes"'), 'does not accept value=');
expectError(forward.replace('operator=contains', 'operator=greaterThan'), 'unsupported show_when operator');
expectError(forward.replace('field="controller" operator=contains', 'field="missing" operator=contains'), 'references unknown field ID missing');

var requiredForward = forward.replace(
    'show_when field="controller" operator=contains value="yes"',
    'required_when field="controller" operator=equals value="yes"'
);
assert.strictEqual(ml.fromCareFormML(requiredForward, definition).sections[0].fields[0].logic.requiredWhen.value, 'yes');
expectError(requiredForward.replace('required_when field="controller" operator=equals value="yes"', 'required_when field="missing" operator=equals value="yes"'), 'required_when references unknown field ID missing');

expectError(forward.replace('type=text', 'type=text min_length=10 max_length=2'), 'min_length cannot be greater than max_length');
expectError(forward.replace('type=text', 'type=text step=0'), 'step must be greater than zero');
expectError(forward.replace('show_when field="controller" operator=contains value="yes"', 'pattern "["'), 'pattern is not a valid regular expression');

var comparisonForward = forward.replace(
    'show_when field="controller" operator=contains value="yes"',
    'show_when field="controller" operator=contains value="yes"\n      compare field="controller" operator=notEquals message="Values must differ."'
);
assert.strictEqual(ml.fromCareFormML(comparisonForward, definition).sections[0].fields[0].validation.compare.message, 'Values must differ.');
expectError(comparisonForward.replace('field="controller" operator=notEquals message="Values must differ."', 'field="missing" operator=notEquals message="Values must differ."'), 'compare references unknown field ID missing');
expectError(comparisonForward.replace('field="controller" operator=notEquals message="Values must differ."', 'field="details" operator=notEquals message="Values must differ."'), 'compare must reference a different field');
expectError(comparisonForward.replace('operator=notEquals message="Values must differ."', 'operator=greaterThan message="Values must differ."'), 'compare operator greaterThan is not compatible');

var calculationForward = forward
    .replace('type=text', 'type=number')
    .replace('show_when field="controller" operator=contains value="yes"', 'calculate expression="controller + 1" precision=2')
    .replace('id="controller" type=text', 'id="controller" type=number');
assert.strictEqual(ml.fromCareFormML(calculationForward, definition).sections[0].fields[0].calculation.expression, 'controller + 1');
expectError(calculationForward.replace('controller + 1', 'missing + 1'), 'Calculation references unknown field ID missing.');
expectError(calculationForward.replace('controller + 1', 'details + 1'), 'A calculation cannot reference its own field.');
expectError(calculationForward.replace('precision=2', 'precision=20'), 'precision must be an integer between 0 and 10');

console.log('CareFormsML logic tests passed.');
