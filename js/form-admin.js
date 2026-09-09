(function () {
    'use strict';

    function apiUrl(path) {
        return OC.generateUrl('/apps/careforms' + path);
    }

    function request(path, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = Object.assign({
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'requesttoken': OC.requestToken
        }, options.headers || {});

        return fetch(apiUrl(path), options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) throw new Error(body.message || 'CareForms request failed.');
                return body;
            });
        });
    }

    function render() {
        var mount = document.getElementById('careforms-form-admin-browser');
        if (!mount) return;

        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Form Administration</h2><p class="careforms-muted">Control which published CareForms forms are available to users.</p></div></div><p class="careforms-muted">Loading…</p>';

        request('/api/forms/admin', { method: 'GET' }).then(function (forms) {
            mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Form Administration</h2><p class="careforms-muted">Control which published CareForms forms are available to users.</p></div></div>';

            var notice = document.createElement('div');
            notice.className = 'careforms-info-banner';
            notice.textContent = 'Disabling a form prevents new access and draft reopening for that form. Existing submitted records remain stored for reporting and audit purposes.';
            mount.appendChild(notice);

            var list = document.createElement('div');
            list.className = 'careforms-admin-form-list';

            forms.forEach(function (form) {
                var row = document.createElement('div');
                row.className = 'careforms-admin-form-row';

                var details = document.createElement('div');
                details.className = 'careforms-admin-form-details';
                details.innerHTML = '<strong></strong><span></span><small></small>';
                details.querySelector('strong').textContent = form.name;
                details.querySelector('span').textContent = form.category;
                details.querySelector('small').textContent = 'Form ID: ' + form.id + ' · Version ' + form.version;

                var state = document.createElement('div');
                state.className = 'careforms-admin-form-state';

                var badge = document.createElement('span');
                badge.className = 'careforms-form-state-badge ' + (form.enabled ? 'is-enabled' : 'is-disabled');
                badge.textContent = form.enabled ? 'Enabled' : 'Disabled';

                var button = document.createElement('button');
                button.type = 'button';
                button.className = form.enabled ? 'careforms-secondary-button' : '';
                button.textContent = form.enabled ? 'Disable' : 'Enable';

                button.addEventListener('click', function () {
                    button.disabled = true;
                    var nextState = !form.enabled;
                    request('/api/forms/admin/' + encodeURIComponent(form.id), {
                        method: 'PUT',
                        body: JSON.stringify({ enabled: nextState })
                    }).then(function (result) {
                        form.enabled = result.enabled;
                        render();
                    }).catch(function (error) {
                        if (OC.Notification && OC.Notification.showTemporary) OC.Notification.showTemporary(error.message);
                        button.disabled = false;
                    });
                });

                state.appendChild(badge);
                state.appendChild(button);
                row.appendChild(details);
                row.appendChild(state);
                list.appendChild(row);
            });

            mount.appendChild(list);
        }).catch(function (error) {
            mount.innerHTML = '<div class="careforms-empty-state"><h3>Could not load form administration</h3><p></p></div>';
            mount.querySelector('p').textContent = error.message;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tab = document.querySelector('.careforms-tab[data-view="form-admin"]');
        if (!tab) return;

        request('/api/access', { method: 'GET' }).then(function (access) {
            tab.hidden = !access.canManageForms;
        }).catch(function () {
            tab.hidden = true;
        });

        tab.addEventListener('click', render);
    });
}());
