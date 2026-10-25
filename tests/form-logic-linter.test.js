'use strict';
var assert=require('assert');
global.CareForms={};
global.CareForms.ConditionBuilder=require('../js/form-condition-builder.js');
global.CareForms.CrossFieldValidation=require('../js/cross-field-validation.js');
global.CareForms.CalculationEngine=require('../js/calculation-engine.js');
global.CareForms.ChoiceOptionDependency=require('../js/choice-option-dependency.js');
var linter=require('../js/form-logic-linter.js');

function definition(){ return {sections:[{id:'details',fields:[
    {id:'toggle',type:'checkbox',label:'Toggle'},
    {id:'score',type:'number',label:'Score'},
    {id:'country',type:'choice-group',label:'Country',options:['Canada','United States']},
    {id:'region',type:'choice-group',label:'Region',options:['Ontario','Texas'],optionDependency:{field:'country',rules:[{value:'Canada',options:['Ontario']},{value:'United States',options:['Texas']}]}},
    {id:'total',type:'number',label:'Total',readOnly:true,calculation:{expression:'score + 1',precision:1}},
    {id:'notes',type:'text',label:'Notes',logic:{showWhen:{field:'toggle',operator:'equals',value:true}},validation:{compare:{field:'country',operator:'equals',message:'Must match'}}}
]}]}; }

assert.deepStrictEqual(linter.lint(definition()),[]);
var form=definition(); form.sections[0].fields[5].logic.showWhen.field='missing';
assert.ok(linter.lint(form).some(function(x){return x.message.indexOf('no longer exists')!==-1 && x.fieldId==='notes';}));
form=definition(); form.sections[0].fields[5].logic.showWhen={field:'notes',operator:'equals',value:'x'};
assert.ok(linter.lint(form).some(function(x){return x.message==='A field cannot depend on itself.';}));
form=definition(); form.sections[0].fields[5].logic.showWhen={field:'score',operator:'contains',value:1};
assert.ok(linter.lint(form).some(function(x){return x.message.indexOf('not compatible')!==-1;}));
form=definition(); form.sections[0].fields[5].logic.showWhen={field:'country',operator:'equals',value:'Mexico'};
assert.ok(linter.lint(form).some(function(x){return x.message.indexOf('no longer an option')!==-1;}));
form=definition(); form.sections[0].fields[5].validation.compare.field='missing';
assert.ok(linter.lint(form).some(function(x){return x.path.indexOf('validation.compare.field')!==-1;}));
form=definition(); form.sections[0].fields[4].calculation.expression='missing + 1';
assert.ok(linter.lint(form).some(function(x){return x.message.indexOf('unknown field ID missing')!==-1;}));
form=definition(); form.sections[0].fields[3].optionDependency.rules[0].options=['Madrid'];
assert.ok(linter.lint(form).some(function(x){return x.message.indexOf('not an option')!==-1;}));
form=definition(); form.sections[0].fields[0].logic={showWhen:{field:'notes',operator:'isNotEmpty'}}; form.sections[0].fields[5].logic={showWhen:{field:'toggle',operator:'equals',value:true}};
assert.ok(linter.lint(form).some(function(x){return x.message.indexOf('circular field dependency')!==-1;}));
console.log('Form logic linter tests passed.');
