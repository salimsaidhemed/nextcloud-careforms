'use strict';

const assert = require('assert');
const restore = require('../js/form-version-restore.js');

assert.strictEqual(restore.hasDraft([]), false);
assert.strictEqual(restore.hasDraft([
    {version: 1, status: 'archived'},
    {version: 2, status: 'published'}
]), false);
assert.strictEqual(restore.hasDraft([
    {version: 3, status: 'draft'}
]), true);
assert.strictEqual(restore.actionLabel({status: 'archived'}), 'Restore as new draft');
assert.strictEqual(restore.actionLabel({status: 'published'}), 'Use as new draft');
assert.strictEqual(
    restore.confirmationMessage(2, 5),
    'Create draft v5 from historical v2? The historical version and existing submissions will not be changed.'
);

console.log('Form version restore tests passed.');
