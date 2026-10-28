'use strict';

const assert = require('assert');
const pagination = require('../js/list-pagination.js');

assert.strictEqual(pagination.query(2, 25, ' Jane Doe '), 'page=2&pageSize=25&search=Jane+Doe');
assert.strictEqual(pagination.query(-5, 500, ''), 'page=1&pageSize=100');
assert.deepStrictEqual(pagination.payload({items:[1],pagination:{page:1}}).items, [1]);
assert.strictEqual(pagination.payload([1,2]).pagination.total, 2);

console.log('List pagination tests passed.');
