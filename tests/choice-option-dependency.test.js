'use strict';

var assert=require('assert');
var helper=require('../js/choice-option-dependency.js');

var dependency={field:'country',rules:[
    {value:'United States',options:['California','Texas']},
    {value:'Canada',options:['Ontario','Quebec']}
]};
var options=['California','Texas','Ontario','Quebec'];
assert.deepStrictEqual(helper.activeOptions(dependency,'Canada',options),['Ontario','Quebec']);
assert.deepStrictEqual(helper.activeOptions(dependency,['Canada','United States'],options),options);
assert.deepStrictEqual(helper.activeOptions(dependency,'',options),[]);
assert.deepStrictEqual(helper.activeOptions(dependency,'Mexico',options),[]);

var fields={
    country:{id:'country',type:'choice-group',options:['United States','Canada']},
    region:{id:'region',type:'checkbox-group',options:options}
};
assert.deepStrictEqual(helper.validate({id:'region',type:'checkbox-group',options:options,optionDependency:dependency},fields),[]);
assert.ok(helper.validate({id:'region',type:'checkbox-group',options:options,optionDependency:{field:'missing',rules:[]}},fields).indexOf('Option dependency references unknown field ID missing.')!==-1);
assert.ok(helper.validate({id:'region',type:'checkbox-group',options:options,optionDependency:{field:'country',rules:[{value:'Mexico',options:['California']}]}},fields)[0].indexOf('not an option')!==-1);
assert.ok(helper.validate({id:'region',type:'checkbox-group',options:options,optionDependency:{field:'country',rules:[{value:'Canada',options:['Madrid']}]}},fields)[0].indexOf('not an option')!==-1);
assert.deepStrictEqual(helper.cycleFields([
    {id:'first',optionDependency:{field:'second'}},
    {id:'second',optionDependency:{field:'first'}},
    {id:'outside',optionDependency:{field:'first'}}
]).sort(),['first','second']);
assert.strictEqual(helper.summary(dependency,fields),'Options depend on country (2 mappings)');

console.log('ChoiceOptionDependency tests passed.');
