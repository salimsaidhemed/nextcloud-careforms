(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) { root.CareForms = root.CareForms || {}; root.CareForms.FormCategoryGroups = api; }
}(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    function group(forms) {
        var categories = {};
        (forms || []).forEach(function (form) {
            var id = form.categoryId || 'uncategorized';
            var order = Number(form.categoryOrder);
            if (!categories[id]) categories[id] = {id:id,label:form.category||'Uncategorized',order:Number.isFinite(order)?order:100000,forms:[]};
            categories[id].forms.push(form);
        });
        return Object.keys(categories).map(function (id) { return categories[id]; }).sort(function (a,b) {
            return a.order-b.order || a.label.localeCompare(b.label);
        }).map(function (category) {
            category.forms.sort(function (a,b) { return a.name.localeCompare(b.name); });
            return category;
        });
    }

    function storageKey(scope, userId, categoryId) {
        return 'careforms:category:' + scope + ':' + (userId || 'user') + ':' + categoryId;
    }

    return {group:group,storageKey:storageKey};
}));
