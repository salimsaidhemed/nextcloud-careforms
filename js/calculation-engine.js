(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) {
        root.CareForms = root.CareForms || {};
        root.CareForms.CalculationEngine = api;
    }
}(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    function tokenize(expression) {
        if (typeof expression !== 'string' || !expression.trim()) throw new Error('Expression is required.');
        if (expression.length > 1000) throw new Error('Expression must not exceed 1000 characters.');
        var tokens = [], index = 0;
        while (index < expression.length) {
            var rest = expression.slice(index), match;
            if (/^\s/.test(rest)) { index += 1; continue; }
            match = rest.match(/^(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/);
            if (match) {
                var numeric=Number(match[0]);
                if(!Number.isFinite(numeric)) throw new Error('Numeric constant must be finite at position '+(index+1)+'.');
                tokens.push({type:'number',value:numeric,position:index}); index += match[0].length; continue;
            }
            match = rest.match(/^[a-z][a-z0-9_]*/);
            if (match) { tokens.push({type:'field',value:match[0],position:index}); index += match[0].length; continue; }
            if ('+-*/()'.indexOf(expression.charAt(index)) !== -1) {
                tokens.push({type:expression.charAt(index),value:expression.charAt(index),position:index}); index += 1; continue;
            }
            throw new Error('Unsupported token at position ' + (index + 1) + '.');
        }
        tokens.push({type:'eof',position:index});
        return tokens;
    }

    function parse(expression) {
        var tokens = tokenize(expression), index = 0;
        function peek(){ return tokens[index]; }
        function take(type){ if(peek().type!==type) throw new Error('Expected '+type+' at position '+(peek().position+1)+'.'); return tokens[index++]; }
        function primary(){
            if(peek().type==='number') return {type:'number',value:take('number').value};
            if(peek().type==='field') return {type:'field',id:take('field').value};
            if(peek().type==='('){ take('('); var node=addition(); take(')'); return node; }
            throw new Error('Expected a number, field ID, or parenthesis at position '+(peek().position+1)+'.');
        }
        function unary(){
            if(peek().type==='+' || peek().type==='-'){ var operator=peek().type; index += 1; return {type:'unary',operator:operator,operand:unary()}; }
            return primary();
        }
        function multiplication(){
            var node=unary();
            while(peek().type==='*' || peek().type==='/'){ var operator=peek().type; index += 1; node={type:'binary',operator:operator,left:node,right:unary()}; }
            return node;
        }
        function addition(){
            var node=multiplication();
            while(peek().type==='+' || peek().type==='-'){ var operator=peek().type; index += 1; node={type:'binary',operator:operator,left:node,right:multiplication()}; }
            return node;
        }
        var ast=addition();
        if(peek().type!=='eof') throw new Error('Unexpected token at position '+(peek().position+1)+'.');
        return ast;
    }

    function references(expression) {
        var found=[];
        function visit(node){
            if(node.type==='field' && found.indexOf(node.id)===-1) found.push(node.id);
            if(node.type==='unary') visit(node.operand);
            if(node.type==='binary'){ visit(node.left); visit(node.right); }
        }
        visit(parse(expression));
        return found;
    }

    function isEmpty(value) {
        return value === null || value === undefined || (typeof value === 'string' && value.trim() === '');
    }

    function evaluate(expression, values, precision) {
        var ast;
        try { ast=parse(expression); } catch(error) { return {status:'error',error:error.message}; }
        function visit(node){
            if(node.type==='number') return {status:'ok',value:node.value};
            if(node.type==='field'){
                var raw=values ? values[node.id] : undefined;
                if(isEmpty(raw)) return {status:'empty'};
                if(typeof raw!=='number' || !Number.isFinite(raw)) return {status:'error',error:'Field '+node.id+' must contain a number.'};
                return {status:'ok',value:raw};
            }
            if(node.type==='unary'){
                var unaryValue=visit(node.operand); if(unaryValue.status!=='ok') return unaryValue;
                return {status:'ok',value:node.operator==='-' ? -unaryValue.value : unaryValue.value};
            }
            var left=visit(node.left); if(left.status!=='ok') return left;
            var right=visit(node.right); if(right.status!=='ok') return right;
            if(node.operator==='/' && right.value===0) return {status:'error',error:'Division by zero.'};
            var result=node.operator==='+' ? left.value+right.value : node.operator==='-' ? left.value-right.value : node.operator==='*' ? left.value*right.value : left.value/right.value;
            return Number.isFinite(result) ? {status:'ok',value:result} : {status:'error',error:'Calculation did not produce a finite number.'};
        }
        var result=visit(ast);
        if(result.status==='ok' && precision!==undefined) result.value=Number(result.value.toFixed(precision));
        return result;
    }

    function validate(expression, fieldsById, targetId) {
        var errors=[], refs;
        try { refs=references(expression); } catch(error) { return [error.message]; }
        refs.forEach(function(id){
            if(id===targetId) errors.push('A calculation cannot reference its own field.');
            else if(!fieldsById[id]) errors.push('Calculation references unknown field ID '+id+'.');
            else if(fieldsById[id].type!=='number') errors.push('Calculation field '+id+' must be a number field.');
        });
        return errors;
    }

    function cycleFields(fields) {
        var dependencies={};
        fields.forEach(function(field){
            if(!field.calculation) return;
            try { dependencies[field.id]=references(field.calculation.expression).filter(function(id){ return fields.some(function(candidate){ return candidate.id===id && candidate.calculation; }); }); }
            catch(error) { dependencies[field.id]=[]; }
        });
        var visiting={}, visited={}, cycles=[];
        function walk(id){
            if(visiting[id]) { if(cycles.indexOf(id)===-1) cycles.push(id); return true; }
            if(visited[id]) return false;
            visiting[id]=true;
            var cyclic=false;
            (dependencies[id] || []).forEach(function(next){ if(walk(next)){ cyclic=true; if(cycles.indexOf(id)===-1) cycles.push(id); } });
            delete visiting[id]; visited[id]=true; return cyclic;
        }
        Object.keys(dependencies).forEach(walk);
        return cycles;
    }

    function summary(calculation) {
        if(!calculation) return '';
        return calculation.expression + (calculation.precision===undefined ? '' : ' (round to '+calculation.precision+' decimal'+(calculation.precision===1?'':'s')+')');
    }

    return {parse:parse,references:references,evaluate:evaluate,validate:validate,cycleFields:cycleFields,summary:summary};
}));
