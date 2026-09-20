'use strict';

var assert = require('assert');
var logic = require('../js/form-logic.js');

assert.strictEqual(logic.isVisible(null, {}), true);
assert.strictEqual(logic.isVisible({}, {}), true);

assert.strictEqual(logic.evaluateRule({field: 'consent', operator: 'equals', value: true}, {consent: true}), true);
assert.strictEqual(logic.evaluateRule({field: 'consent', operator: 'equals', value: true}, {consent: false}), false);
assert.strictEqual(logic.evaluateRule({field: 'status', operator: 'notEquals', value: 'closed'}, {status: 'open'}), true);

assert.strictEqual(logic.evaluateRule({field: 'notes', operator: 'isEmpty'}, {notes: ''}), true);
assert.strictEqual(logic.evaluateRule({field: 'notes', operator: 'isEmpty'}, {notes: '   '}), true);
assert.strictEqual(logic.evaluateRule({field: 'score', operator: 'isEmpty'}, {score: 0}), false);
assert.strictEqual(logic.evaluateRule({field: 'enabled', operator: 'isEmpty'}, {enabled: false}), false);
assert.strictEqual(logic.evaluateRule({field: 'items', operator: 'isEmpty'}, {items: []}), true);
assert.strictEqual(logic.evaluateRule({field: 'missing', operator: 'isEmpty'}, {}), true);
assert.strictEqual(logic.evaluateRule({field: 'notes', operator: 'isNotEmpty'}, {notes: 'value'}), true);

assert.strictEqual(logic.evaluateRule({field: 'symptoms', operator: 'contains', value: 'Pain'}, {symptoms: ['Pain', 'Fever']}), true);
assert.strictEqual(logic.evaluateRule({field: 'notes', operator: 'contains', value: 'pain'}, {notes: 'lower back pain'}), true);
assert.strictEqual(logic.evaluateRule({field: 'score', operator: 'contains', value: 2}, {score: 12}), false);

assert.strictEqual(logic.evaluateRule({field: 'status', operator: 'unsupported', value: 'open'}, {status: 'open'}), false);

console.log('FormLogic tests passed.');
