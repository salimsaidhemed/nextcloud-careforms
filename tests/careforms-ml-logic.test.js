'use strict';

var assert = require('assert');
global.window = {CareForms: {}};
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
                    logic: {
                        showWhen: {field: 'has_pain', operator: 'equals', value: true},
                        requiredWhen: {field: 'has_pain', operator: 'equals', value: true}
                    }
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
                    logic: {showWhen: {field: 'pain_score', operator: 'isNotEmpty'}}
                }
            ]
        }
    ]
};

var source = ml.toCareFormML(definition);
assert.ok(source.includes('show_when field="has_pain" operator=equals value=true'));
assert.ok(source.includes('required_when field="has_pain" operator=equals value=true'));
assert.ok(source.includes('show_when field="pain_score" operator=notEquals value=0'));
assert.ok(source.includes('show_when field="pain_score" operator=isNotEmpty'));

var parsed = ml.fromCareFormML(source, definition);
assert.deepStrictEqual(parsed.sections[0].fields[1].logic, definition.sections[0].fields[1].logic);
assert.deepStrictEqual(parsed.sections[1].logic, definition.sections[1].logic);
assert.deepStrictEqual(parsed.sections[1].fields[0].logic, definition.sections[1].fields[0].logic);

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

console.log('CareFormsML logic tests passed.');
