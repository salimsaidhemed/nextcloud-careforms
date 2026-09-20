'use strict';

var assert = require('assert');
var builder = require('../js/form-condition-builder.js');

assert.deepStrictEqual(builder.operatorsFor({type: 'checkbox'}), ['equals', 'notEquals', 'isEmpty', 'isNotEmpty']);
assert.deepStrictEqual(builder.operatorsFor({type: 'checkbox-group'}), ['contains', 'isEmpty', 'isNotEmpty']);
assert.deepStrictEqual(builder.operatorsFor({type: 'number'}), ['equals', 'notEquals', 'isEmpty', 'isNotEmpty']);
assert.deepStrictEqual(builder.operatorsFor({type: 'text'}), ['equals', 'notEquals', 'contains', 'isEmpty', 'isNotEmpty']);

assert.strictEqual(builder.needsValue('equals'), true);
assert.strictEqual(builder.needsValue('contains'), true);
assert.strictEqual(builder.needsValue('isEmpty'), false);
assert.strictEqual(builder.needsValue('isNotEmpty'), false);

assert.strictEqual(builder.coerceValue({type: 'checkbox'}, 'true'), true);
assert.strictEqual(builder.coerceValue({type: 'checkbox'}, 'false'), false);
assert.strictEqual(builder.coerceValue({type: 'number'}, '12.5'), 12.5);
assert.strictEqual(builder.coerceValue({type: 'text'}, '12.5'), '12.5');
assert.deepStrictEqual(builder.createRule('consent', 'equals', {type: 'checkbox'}, 'true'), {field: 'consent', operator: 'equals', value: true});
assert.deepStrictEqual(builder.createRule('notes', 'isEmpty', {type: 'text'}, 'ignored'), {field: 'notes', operator: 'isEmpty'});

var fields = {has_pain: {id: 'has_pain', label: 'Does the patient have pain?'}};
assert.strictEqual(
    builder.ruleSummary({field: 'has_pain', operator: 'equals', value: true}, fields),
    'Shown when Does the patient have pain? equals “Yes”'
);
assert.strictEqual(
    builder.ruleSummary({field: 'notes', operator: 'isNotEmpty'}, {}),
    'Shown when notes is not empty'
);

console.log('ConditionBuilder tests passed.');
