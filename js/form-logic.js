(function (root, factory) {
    'use strict';

    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    if (root) {
        root.CareForms = root.CareForms || {};
        root.CareForms.FormLogic = api;
    }
}(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    function isEmpty(value) {
        return value === undefined || value === null ||
            (typeof value === 'string' && value.trim() === '') ||
            (Array.isArray(value) && value.length === 0);
    }

    function evaluateRule(rule, values) {
        if (!rule || typeof rule !== 'object') return true;

        var actual = values ? values[rule.field] : undefined;
        if (rule.operator === 'equals') return actual === rule.value;
        if (rule.operator === 'notEquals') return actual !== rule.value;
        if (rule.operator === 'isEmpty') return isEmpty(actual);
        if (rule.operator === 'isNotEmpty') return !isEmpty(actual);
        if (rule.operator === 'contains') {
            if (Array.isArray(actual)) return actual.indexOf(rule.value) !== -1;
            if (typeof actual === 'string') return actual.indexOf(String(rule.value)) !== -1;
            return false;
        }
        return false;
    }

    function isVisible(logic, values) {
        if (!logic || !logic.showWhen) return true;
        return evaluateRule(logic.showWhen, values || {});
    }

    return {
        isEmpty: isEmpty,
        evaluateRule: evaluateRule,
        isVisible: isVisible
    };
}));
