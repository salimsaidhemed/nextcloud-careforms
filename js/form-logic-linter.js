(function (root, factory) {
    'use strict';
    var api=factory(root);
    if(typeof module==='object' && module.exports) module.exports=api;
    if(root){ root.CareForms=root.CareForms || {}; root.CareForms.FormLogicLinter=api; }
}(typeof window!=='undefined' ? window : (typeof global!=='undefined' ? global : null), function (root) {
    'use strict';

    function helpers(){ return root && root.CareForms ? root.CareForms : {}; }
    function issue(path,message,fieldId,sectionId,severity){
        return {severity:severity || 'error',path:path,message:message,fieldId:fieldId || null,sectionId:sectionId || null};
    }
    function indexDefinition(definition){
        var fields={},fieldLocations={},sectionLocations={},ordered=[];
        (definition.sections || []).forEach(function(section,sectionIndex){
            if(!section || typeof section!=='object') return;
            sectionLocations[section.id]={section:section,index:sectionIndex};
            (section.fields || []).forEach(function(field,fieldIndex){
                if(!field || typeof field!=='object') return;
                fields[field.id]=field;
                fieldLocations[field.id]={field:field,section:section,sectionIndex:sectionIndex,fieldIndex:fieldIndex};
                ordered.push(field);
            });
        });
        return {fields:fields,fieldLocations:fieldLocations,sectionLocations:sectionLocations,ordered:ordered};
    }
    function lintRule(rule,path,target,index,issues){
        if(!rule || typeof rule!=='object' || Array.isArray(rule)){
            issues.push(issue(path,'Condition must be an object.',target.fieldId,target.sectionId)); return;
        }
        var sourceId=rule.field, source=index.fields[sourceId];
        if(typeof sourceId!=='string' || !sourceId){ issues.push(issue(path+'.field','Choose a controlling field.',target.fieldId,target.sectionId)); return; }
        if(!source){ issues.push(issue(path+'.field','Controlling field “'+sourceId+'” no longer exists.',target.fieldId,target.sectionId)); return; }
        if(target.fieldId && sourceId===target.fieldId){ issues.push(issue(path+'.field','A field cannot depend on itself.',target.fieldId,target.sectionId)); }
        var condition=helpers().ConditionBuilder;
        var operators=condition && condition.operatorsFor ? condition.operatorsFor(source) : ['equals','notEquals','isEmpty','isNotEmpty','contains'];
        if(operators.indexOf(rule.operator)===-1){ issues.push(issue(path+'.operator','Operator “'+String(rule.operator || '')+'” is not compatible with '+(source.label || source.id)+'.',target.fieldId,target.sectionId)); return; }
        var needsValue=condition && condition.needsValue ? condition.needsValue(rule.operator) : ['isEmpty','isNotEmpty'].indexOf(rule.operator)===-1;
        if(needsValue && !Object.prototype.hasOwnProperty.call(rule,'value')){
            issues.push(issue(path+'.value','Add a comparison value.',target.fieldId,target.sectionId)); return;
        }
        if(!needsValue && Object.prototype.hasOwnProperty.call(rule,'value')) issues.push(issue(path+'.value','Remove the comparison value for this operator.',target.fieldId,target.sectionId));
        if(!needsValue) return;
        if((source.type==='choice-group' || source.type==='checkbox-group') && (source.options || []).indexOf(rule.value)===-1){
            issues.push(issue(path+'.value','“'+String(rule.value)+'” is no longer an option of '+(source.label || source.id)+'.',target.fieldId,target.sectionId));
        }
        if(source.type==='checkbox' && typeof rule.value!=='boolean') issues.push(issue(path+'.value','Yes / No conditions must use a boolean value.',target.fieldId,target.sectionId));
        if(source.type==='number' && (typeof rule.value!=='number' || !Number.isFinite(rule.value))) issues.push(issue(path+'.value','Number conditions must use a numeric value.',target.fieldId,target.sectionId));
    }
    function dependencyCycles(edges){
        var visiting={},visited={},stack=[],cycles={};
        function walk(id){
            if(Object.prototype.hasOwnProperty.call(visiting,id)){ stack.slice(visiting[id]).forEach(function(item){cycles[item]=true;}); return; }
            if(visited[id]) return;
            visiting[id]=stack.length; stack.push(id);
            (edges[id] || []).forEach(function(next){ if(edges[next]) walk(next); });
            stack.pop(); delete visiting[id]; visited[id]=true;
        }
        Object.keys(edges).forEach(walk); return Object.keys(cycles);
    }
    function lint(definition){
        var issues=[],index=indexDefinition(definition || {}),visibilityEdges={};
        (definition.sections || []).forEach(function(section,sectionIndex){
            if(!section || typeof section!=='object') return;
            var sectionPath='sections['+sectionIndex+']';
            if(section.logic && section.logic.showWhen) lintRule(section.logic.showWhen,sectionPath+'.logic.showWhen',{sectionId:section.id},index,issues);
            (section.fields || []).forEach(function(field,fieldIndex){
                if(!field || typeof field!=='object') return;
                var path=sectionPath+'.fields['+fieldIndex+']', target={fieldId:field.id,sectionId:section.id};
                ['showWhen','requiredWhen'].forEach(function(name){
                    if(field.logic && field.logic[name]){
                        lintRule(field.logic[name],path+'.logic.'+name,target,index,issues);
                        if(name==='showWhen' && index.fields[field.logic[name].field]){
                            visibilityEdges[field.id]=visibilityEdges[field.id] || [];
                            visibilityEdges[field.id].push(field.logic[name].field);
                        }
                    }
                });
                var comparison=field.validation && field.validation.compare;
                if(comparison){
                    var source=index.fields[comparison.field], cross=helpers().CrossFieldValidation;
                    if(!source) issues.push(issue(path+'.validation.compare.field','Compared field “'+String(comparison.field || '')+'” no longer exists.',field.id,section.id));
                    else if(source.id===field.id) issues.push(issue(path+'.validation.compare.field','A field cannot be compared with itself.',field.id,section.id));
                    else if(!cross || cross.operatorsFor(field,source).indexOf(comparison.operator)===-1) issues.push(issue(path+'.validation.compare.operator','Comparison operator is not compatible with these field types.',field.id,section.id));
                    if(typeof comparison.message!=='string' || !comparison.message.trim()) issues.push(issue(path+'.validation.compare.message','Add an error message for this comparison.',field.id,section.id));
                }
                if(field.calculation){
                    var calculation=helpers().CalculationEngine;
                    if(!calculation) issues.push(issue(path+'.calculation','Calculation validation is unavailable.',field.id,section.id));
                    else (calculation.validate(field.calculation.expression,index.fields,field.id) || []).forEach(function(message){ issues.push(issue(path+'.calculation.expression',message,field.id,section.id)); });
                    if(field.type!=='number') issues.push(issue(path+'.calculation','Calculated values must use a number field.',field.id,section.id));
                    if(field.readOnly!==true) issues.push(issue(path+'.readOnly','Calculated fields must be read-only.',field.id,section.id));
                    if(field.calculation.precision!==undefined && (!Number.isInteger(field.calculation.precision) || field.calculation.precision<0 || field.calculation.precision>10)) issues.push(issue(path+'.calculation.precision','Decimal places must be an integer between 0 and 10.',field.id,section.id));
                }
                if(field.optionDependency){
                    var optionHelper=helpers().ChoiceOptionDependency;
                    if(!optionHelper) issues.push(issue(path+'.optionDependency','Choice dependency validation is unavailable.',field.id,section.id));
                    else (optionHelper.validate(field,index.fields) || []).forEach(function(message){ issues.push(issue(path+'.optionDependency',message,field.id,section.id)); });
                }
            });
        });
        dependencyCycles(visibilityEdges).forEach(function(id){
            var loc=index.fieldLocations[id]; if(loc) issues.push(issue('sections['+loc.sectionIndex+'].fields['+loc.fieldIndex+'].logic','Conditional rules contain a circular field dependency.',id,loc.section.id));
        });
        var calculation=helpers().CalculationEngine;
        if(calculation) calculation.cycleFields(index.ordered).forEach(function(id){
            var loc=index.fieldLocations[id]; if(loc) issues.push(issue('sections['+loc.sectionIndex+'].fields['+loc.fieldIndex+'].calculation','Calculated fields contain a circular dependency.',id,loc.section.id));
        });
        var optionHelper=helpers().ChoiceOptionDependency;
        if(optionHelper) optionHelper.cycleFields(index.ordered).forEach(function(id){
            var loc=index.fieldLocations[id]; if(loc) issues.push(issue('sections['+loc.sectionIndex+'].fields['+loc.fieldIndex+'].optionDependency','Choice fields contain a circular option dependency.',id,loc.section.id));
        });
        var unique={};
        return issues.filter(function(item){ var key=item.path+'|'+item.message; if(unique[key]) return false; unique[key]=true; return true; });
    }
    return {lint:lint,indexDefinition:indexDefinition};
}));
