'use strict';

var assert = require('assert');
var validation = require('../js/cross-field-validation.js');

var number = {id: 'end', type: 'number', label: 'End'};
var otherNumber = {id: 'start', type: 'number', label: 'Start'};
var date = {id: 'date', type: 'date', label: 'Date'};
var text = {id: 'confirmation', type: 'text', label: 'Confirmation'};
var textarea = {id: 'original', type: 'textarea', label: 'Original'};

assert.deepStrictEqual(validation.operatorsFor(number, otherNumber), [
    'equals', 'notEquals', 'greaterThan', 'greaterThanOrEqual', 'lessThan', 'lessThanOrEqual'
]);
assert.deepStrictEqual(validation.operatorsFor(text, textarea), ['equals', 'notEquals']);
assert.deepStrictEqual(validation.operatorsFor(number, date), []);

function evaluate(operator, actual, expected) {
    return validation.evaluateRule({target: 'end', field: 'start', operator: operator}, {end: actual, start: expected});
}

assert.strictEqual(evaluate('equals', 5, 5), true);
assert.strictEqual(evaluate('notEquals', 5, 6), true);
assert.strictEqual(evaluate('greaterThan', 6, 5), true);
assert.strictEqual(evaluate('greaterThanOrEqual', 5, 5), true);
assert.strictEqual(evaluate('lessThan', '2026-10-23', '2026-10-24'), true);
assert.strictEqual(evaluate('lessThanOrEqual', '09:00', '09:00'), true);
assert.strictEqual(evaluate('greaterThan', 4, 5), false);
assert.strictEqual(evaluate('unsupported', 5, 5), false);
assert.strictEqual(evaluate('greaterThan', '', 5), true);
assert.strictEqual(evaluate('greaterThan', 5, ''), true);

assert.strictEqual(
    validation.ruleSummary({field: 'start', operator: 'greaterThanOrEqual'}, {start: otherNumber}, number),
    'End is greater than or equal to Start'
);

console.log('CrossFieldValidation tests passed.');
