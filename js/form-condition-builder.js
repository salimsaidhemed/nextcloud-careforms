(function (root, factory) {
    'use strict';

    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) {
        root.CareForms = root.CareForms || {};
        root.CareForms.ConditionBuilder = api;
    }
}(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    var EMPTY_OPERATORS = ['isEmpty', 'isNotEmpty'];
    var LABELS = {
        equals: 'equals',
        notEquals: 'does not equal',
        isEmpty: 'is empty',
        isNotEmpty: 'is not empty',
        contains: 'contains'
    };

    function operatorsFor(field) {
        if (!field) return [];
        if (field.type === 'checkbox-group') return ['contains', 'isEmpty', 'isNotEmpty'];
        if (field.type === 'checkbox') return ['equals', 'notEquals', 'isEmpty', 'isNotEmpty'];
        if (field.type === 'number' || field.type === 'date' || field.type === 'time') {
            return ['equals', 'notEquals', 'isEmpty', 'isNotEmpty'];
        }
        return ['equals', 'notEquals', 'contains', 'isEmpty', 'isNotEmpty'];
    }

    function needsValue(operator) {
        return EMPTY_OPERATORS.indexOf(operator) === -1;
    }

    function coerceValue(field, value) {
        if (!field) return value;
        if (field.type === 'checkbox') return value === true || value === 'true';
        if (field.type === 'number') return value === '' ? '' : Number(value);
        return value;
    }

    function valueLabel(value) {
        if (value === true) return 'Yes';
        if (value === false) return 'No';
        return String(value);
    }

    function createRule(fieldId, operator, field, value) {
        var rule = {field: fieldId, operator: operator};
        if (needsValue(operator)) rule.value = coerceValue(field, value);
        return rule;
    }

    function ruleSummary(rule, fieldsById) {
        if (!rule) return '';
        var field = fieldsById && fieldsById[rule.field];
        var source = field ? (field.label || field.id) : rule.field;
        var summary = 'Shown when ' + source + ' ' + (LABELS[rule.operator] || rule.operator);
        if (needsValue(rule.operator)) summary += ' “' + valueLabel(rule.value) + '”';
        return summary;
    }

    return {
        labels: LABELS,
        operatorsFor: operatorsFor,
        needsValue: needsValue,
        coerceValue: coerceValue,
        createRule: createRule,
        ruleSummary: ruleSummary
    };
}));
