'use strict';

const assert = require('assert');
const groups = require('../js/form-category-groups.js');

const visibleForms = [
    {id:'b',name:'Zulu',categoryId:'clinical',category:'Clinical',categoryOrder:20},
    {id:'a',name:'Alpha',categoryId:'clinical',category:'Clinical',categoryOrder:20},
    {id:'c',name:'Admin form',categoryId:'administrative',category:'Administrative',categoryOrder:10},
];
const result = groups.group(visibleForms);
assert.deepStrictEqual(result.map(item => item.id), ['administrative','clinical']);
assert.deepStrictEqual(result[1].forms.map(item => item.id), ['a','b']);
assert.strictEqual(result[1].forms.length, 2, 'Counts must be based only on the forms supplied after permission filtering.');
assert.strictEqual(groups.storageKey('forms','alice','clinical'), 'careforms:category:forms:alice:clinical');

console.log('Form category grouping tests passed.');
