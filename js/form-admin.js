(function () {
    'use strict';

    function apiUrl(path) { return OC.generateUrl('/apps/careforms' + path); }
    function request(path, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = Object.assign({'Accept':'application/json','Content-Type':'application/json','requesttoken':OC.requestToken}, options.headers || {});
        return fetch(apiUrl(path), options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) throw new Error(body.message || 'CareForms request failed.');
                return body;
            });
        });
    }
    function notify(message) { if (OC.Notification && OC.Notification.showTemporary) OC.Notification.showTemporary(message); }

    function lifecycleBadge(version) {
        var badge = document.createElement('span');
        badge.className = 'careforms-form-state-badge ' + (version.status === 'published' ? 'is-enabled' : (version.status === 'archived' ? 'is-disabled' : ''));
        badge.textContent = 'v' + version.version + ' · ' + version.status.charAt(0).toUpperCase() + version.status.slice(1);
        return badge;
    }

    function actionButton(label, className, handler) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className || '';
        button.textContent = label;
        button.addEventListener('click', function () {
            button.disabled = true;
            Promise.resolve(handler()).catch(function (error) {
                notify(error.message);
                button.disabled = false;
            });
        });
        return button;
    }

    function render() {
        var mount = document.getElementById('careforms-form-admin-browser');
        if (!mount) return;
        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Form Administration</h2><p class="careforms-muted">Manage form availability and the Draft → Published → Archived version lifecycle.</p></div></div><p class="careforms-muted">Loading…</p>';

        request('/api/forms/admin', {method:'GET'}).then(function (forms) {
            mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Form Administration</h2><p class="careforms-muted">Manage form availability and the Draft → Published → Archived version lifecycle.</p></div></div>';
            var notice = document.createElement('div');
            notice.className = 'careforms-info-banner';
            notice.textContent = 'Only one version is published at a time. Publishing a draft automatically archives the previous published version. Existing submissions stay tied to the version they were created with.';
            mount.appendChild(notice);

            var list = document.createElement('div');
            list.className = 'careforms-admin-form-list';
            forms.forEach(function (form) {
                var row = document.createElement('div');
                row.className = 'careforms-admin-form-row';
                row.style.alignItems = 'flex-start';

                var details = document.createElement('div');
                details.className = 'careforms-admin-form-details';
                var heading = document.createElement('strong'); heading.textContent = form.name;
                var meta = document.createElement('span'); meta.textContent = form.category + ' · Form ID: ' + form.id + ' · Published v' + form.publishedVersion;
                details.appendChild(heading); details.appendChild(meta);

                var versions = document.createElement('div');
                versions.style.display = 'flex'; versions.style.flexWrap = 'wrap'; versions.style.gap = '8px'; versions.style.marginTop = '8px';
                form.versions.forEach(function (version) {
                    var group = document.createElement('span');
                    group.style.display = 'inline-flex'; group.style.alignItems = 'center'; group.style.gap = '6px';
                    group.appendChild(lifecycleBadge(version));
                    if (version.status === 'draft') {
                        group.appendChild(actionButton('Publish', '', function () {
                            return request('/api/forms/admin/' + encodeURIComponent(form.id) + '/versions/' + version.version + '/publish', {method:'POST'}).then(function(){ notify('Version ' + version.version + ' published.'); render(); });
                        }));
                        group.appendChild(actionButton('Archive draft', 'careforms-secondary-button', function () {
                            return request('/api/forms/admin/' + encodeURIComponent(form.id) + '/versions/' + version.version + '/archive', {method:'POST'}).then(function(){ render(); });
                        }));
                    }
                    versions.appendChild(group);
                });
                details.appendChild(versions);

                var state = document.createElement('div');
                state.className = 'careforms-admin-form-state';
                var enabledBadge = document.createElement('span');
                enabledBadge.className = 'careforms-form-state-badge ' + (form.enabled ? 'is-enabled' : 'is-disabled');
                enabledBadge.textContent = form.enabled ? 'Enabled' : 'Disabled';
                state.appendChild(enabledBadge);
                state.appendChild(actionButton(form.enabled ? 'Disable' : 'Enable', form.enabled ? 'careforms-secondary-button' : '', function () {
                    return request('/api/forms/admin/' + encodeURIComponent(form.id), {method:'PUT', body:JSON.stringify({enabled:!form.enabled})}).then(function(){ render(); });
                }));
                if (!form.versions.some(function(v){ return v.status === 'draft'; })) {
                    state.appendChild(actionButton('Create new draft', '', function () {
                        return request('/api/forms/admin/' + encodeURIComponent(form.id) + '/versions', {method:'POST'}).then(function (draft) { notify('Draft version ' + draft.version + ' created.'); render(); });
                    }));
                }

                row.appendChild(details); row.appendChild(state); list.appendChild(row);
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
        request('/api/access', {method:'GET'}).then(function (access) { tab.hidden = !access.canManageForms; }).catch(function () { tab.hidden = true; });
        tab.addEventListener('click', render);
    });
}());
