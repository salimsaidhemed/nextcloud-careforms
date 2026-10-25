(function (root, factory) {
    'use strict';
    var api=factory();
    if(typeof module==='object' && module.exports) module.exports=api;
    if(root){ root.CareForms=root.CareForms||{}; root.CareForms.ChoiceOptionDependency=api; }
}(typeof window!=='undefined' ? window : null, function(){
    'use strict';
    var CHOICE_TYPES=['choice-group','checkbox-group'];

    function selectedValues(value){
        if(Array.isArray(value)) return value;
        if(value===null || value===undefined || (typeof value==='string' && value.trim()==='')) return [];
        return [value];
    }

    function activeOptions(dependency, controllerValue, targetOptions){
        if(!dependency || !Array.isArray(dependency.rules)) return (targetOptions || []).slice();
        var selected=selectedValues(controllerValue), allowed={};
        dependency.rules.forEach(function(rule){
            if(selected.indexOf(rule.value)===-1) return;
            (rule.options || []).forEach(function(option){ allowed[option]=true; });
        });
        return (targetOptions || []).filter(function(option){ return allowed[option]===true; });
    }

    function validate(target, fieldsById){
        var dependency=target && target.optionDependency, errors=[];
        if(!dependency) return errors;
        if(CHOICE_TYPES.indexOf(target.type)===-1) errors.push('Option dependencies are only valid for choice fields.');
        if(!Array.isArray(dependency.rules) || !dependency.rules.length) errors.push('Option dependency requires at least one mapping.');
        var source=fieldsById && fieldsById[dependency.field];
        if(!source) errors.push('Option dependency references unknown field ID '+dependency.field+'.');
        else if(source.id===target.id) errors.push('An option dependency cannot reference its own field.');
        else if(CHOICE_TYPES.indexOf(source.type)===-1) errors.push('Option dependency controller '+source.id+' must be a choice field.');
        var seen={};
        (dependency.rules || []).forEach(function(rule){
            if(seen[rule.value]) errors.push('Option dependency contains duplicate controller value '+rule.value+'.');
            seen[rule.value]=true;
            if(source && (source.options || []).indexOf(rule.value)===-1) errors.push('Controller value '+rule.value+' is not an option of '+source.id+'.');
            if(!Array.isArray(rule.options) || !rule.options.length) errors.push('Controller value '+rule.value+' must expose at least one option.');
            var seenOptions={};
            (rule.options || []).forEach(function(option){
                if(seenOptions[option]) errors.push('Controller value '+rule.value+' repeats dependent option '+option+'.');
                seenOptions[option]=true;
                if((target.options || []).indexOf(option)===-1) errors.push('Dependent option '+option+' is not an option of '+target.id+'.');
            });
        });
        return errors;
    }

    function cycleFields(fields){
        var dependencies={}, visiting={}, visited={}, stack=[], cycles={};
        fields.forEach(function(field){ if(field.optionDependency) dependencies[field.id]=field.optionDependency.field; });
        function walk(id){
            if(Object.prototype.hasOwnProperty.call(visiting,id)){
                stack.slice(visiting[id]).forEach(function(item){cycles[item]=true;}); return;
            }
            if(visited[id]) return;
            visiting[id]=stack.length; stack.push(id);
            var next=dependencies[id]; if(next && dependencies[next]) walk(next);
            stack.pop(); delete visiting[id]; visited[id]=true;
        }
        Object.keys(dependencies).forEach(walk);
        return Object.keys(cycles);
    }

    function summary(dependency, fieldsById){
        if(!dependency) return '';
        var source=fieldsById && fieldsById[dependency.field];
        return 'Options depend on '+(source ? (source.label || source.id) : dependency.field)+' ('+(dependency.rules || []).length+' mapping'+((dependency.rules || []).length===1?'':'s')+')';
    }

    return {choiceTypes:CHOICE_TYPES,selectedValues:selectedValues,activeOptions:activeOptions,validate:validate,cycleFields:cycleFields,summary:summary};
}));
