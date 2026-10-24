(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    root.CareForms = root.CareForms || {};
    root.CareForms.FormVersionRestore = api;
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    function hasDraft(versions) {
        return (versions || []).some(function (version) {
            return version.status === 'draft';
        });
    }

    function actionLabel(version) {
        return version.status === 'archived' ? 'Restore as new draft' : 'Use as new draft';
    }

    function confirmationMessage(sourceVersion, targetVersion) {
        return 'Create draft v' + targetVersion + ' from historical v' + sourceVersion + '? The historical version and existing submissions will not be changed.';
    }

    return {
        hasDraft: hasDraft,
        actionLabel: actionLabel,
        confirmationMessage: confirmationMessage
    };
}));
