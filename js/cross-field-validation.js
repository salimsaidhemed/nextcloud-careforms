(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) {
        root.CareForms = root.CareForms || {};
        root.CareForms.CrossFieldValidation = api;
    }
}(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    var EQUALITY_OPERATORS = ['equals', 'notEquals'];
    var ORDERED_OPERATORS = ['greaterThan', 'greaterThanOrEqual', 'lessThan', 'lessThanOrEqual'];
    var LABELS = {
        equals: 'equals',
        notEquals: 'does not equal',
        greaterThan: 'is greater than',
        greaterThanOrEqual: 'is greater than or equal to',
        lessThan: 'is less than',
        lessThanOrEqual: 'is less than or equal to'
    };

    function valueFamily(field) {
        if (!field) return null;
        if (field.type === 'text' || field.type === 'textarea' || field.type === 'choice-group') return 'text';
        if (field.type === 'number' || field.type === 'date' || field.type === 'time' || field.type === 'checkbox') return field.type;
        return null;
    }

    function operatorsFor(target, source) {
        var targetFamily = valueFamily(target);
        var sourceFamily = valueFamily(source);
        if (!targetFamily || targetFamily !== sourceFamily) return [];
        if (['number', 'date', 'time'].indexOf(targetFamily) !== -1) {
            return EQUALITY_OPERATORS.concat(ORDERED_OPERATORS);
        }
        return EQUALITY_OPERATORS.slice();
    }

    function isEmpty(value) {
        return value === null || value === undefined || (typeof value === 'string' && value.trim() === '') || (Array.isArray(value) && value.length === 0);
    }

    function evaluateRule(rule, values) {
        if (!rule) return true;
        var actual = values ? values[rule.target] : undefined;
        var expected = values ? values[rule.field] : undefined;
        if (isEmpty(actual) || isEmpty(expected)) return true;
        switch (rule.operator) {
            case 'equals': return actual === expected;
            case 'notEquals': return actual !== expected;
            case 'greaterThan': return actual > expected;
            case 'greaterThanOrEqual': return actual >= expected;
            case 'lessThan': return actual < expected;
            case 'lessThanOrEqual': return actual <= expected;
            default: return false;
        }
    }

    function ruleSummary(rule, fieldsById, targetField) {
        if (!rule) return '';
        var source = fieldsById && fieldsById[rule.field];
        var targetLabel = targetField ? (targetField.label || targetField.id) : 'This field';
        var sourceLabel = source ? (source.label || source.id) : rule.field;
        return targetLabel + ' ' + (LABELS[rule.operator] || rule.operator) + ' ' + sourceLabel;
    }

    return {
        labels: LABELS,
        valueFamily: valueFamily,
        operatorsFor: operatorsFor,
        isEmpty: isEmpty,
        evaluateRule: evaluateRule,
        ruleSummary: ruleSummary
    };
}));
