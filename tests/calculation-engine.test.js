'use strict';

var assert = require('assert');
var engine = require('../js/calculation-engine.js');

assert.strictEqual(engine.evaluate('2 + 3 * 4', {}).value, 14);
assert.strictEqual(engine.evaluate('(2 + 3) * 4', {}).value, 20);
assert.strictEqual(engine.evaluate('-2 + +5', {}).value, 3);
assert.deepStrictEqual(engine.references('weight / (height * height)'), ['weight', 'height']);
assert.deepStrictEqual(engine.evaluate('weight / (height * height)', {weight: 70, height: 1.75}, 2), {status:'ok',value:22.86});
assert.deepStrictEqual(engine.evaluate('weight / height', {weight: 70, height: ''}), {status:'empty'});
assert.deepStrictEqual(engine.evaluate('weight / height', {weight: 70, height: 0}), {status:'error',error:'Division by zero.'});
assert.throws(function(){ engine.parse('Math.random()'); }, /Unsupported token/);
assert.throws(function(){ engine.parse('weight ** 2'); }, /Expected a number/);

var fields={weight:{id:'weight',type:'number'},notes:{id:'notes',type:'text'},bmi:{id:'bmi',type:'number'}};
assert.deepStrictEqual(engine.validate('weight + 1',fields,'bmi'), []);
assert.deepStrictEqual(engine.validate('missing + 1',fields,'bmi'), ['Calculation references unknown field ID missing.']);
assert.deepStrictEqual(engine.validate('notes + 1',fields,'bmi'), ['Calculation field notes must be a number field.']);
assert.deepStrictEqual(engine.validate('bmi + 1',fields,'bmi'), ['A calculation cannot reference its own field.']);
assert.deepStrictEqual(engine.cycleFields([
    {id:'first',type:'number',calculation:{expression:'second + 1'}},
    {id:'second',type:'number',calculation:{expression:'first + 1'}}
]).sort(), ['first','second']);

console.log('CalculationEngine tests passed.');
