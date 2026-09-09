(function () {
    'use strict';

    function apiUrl(path) {
        return OC.generateUrl('/apps/careforms' + path);
    }

    function request(path) {
        return fetch(apiUrl(path), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'requesttoken': OC.requestToken
            }
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) {
                    throw new Error(body.message || 'CareForms request failed.');
                }
                return body;
            });
        });
    }

    function displayDate(timestamp) {
        return timestamp ? new Date(timestamp * 1000).toLocaleString() : '—';
    }

    function humanize(value) {
        return (value || '—').toString().replace(/_/g, ' ');
    }

    function renderAudit() {
        var mount = document.getElementById('careforms-audit-browser');
        if (!mount) return;

        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Audit Log</h2><p class="careforms-muted">Recent CareForms security and submission activity. Clinical form values are not stored here.</p></div></div><p class="careforms-muted">Loading…</p>';

        request('/api/audit?limit=200').then(function (events) {
            mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Audit Log</h2><p class="careforms-muted">Recent CareForms security and submission activity. Clinical form values are not stored here.</p></div></div>';

            if (!events.length) {
                mount.innerHTML += '<div class="careforms-empty-state"><h3>No audit events yet</h3><p>CareForms activity will appear here as users work with forms.</p></div>';
                return;
            }

            var controls = document.createElement('div');
            controls.className = 'careforms-audit-controls';
            controls.innerHTML = '<input type="search" placeholder="Filter user, action, form or outcome" aria-label="Filter audit log">';
            mount.appendChild(controls);

            var wrap = document.createElement('div');
            wrap.className = 'careforms-audit-table-wrap';
            var table = document.createElement('table');
            table.className = 'careforms-audit-table';
            table.innerHTML = '<thead><tr><th>Time</th><th>User</th><th>Action</th><th>Form</th><th>Resource</th><th>Outcome</th><th>Reason</th></tr></thead><tbody></tbody>';
            var tbody = table.querySelector('tbody');

            events.forEach(function (event) {
                var row = document.createElement('tr');
                row.dataset.filter = [event.userId, event.action, event.formId, event.outcome, event.resourceType, event.resourceId].join(' ').toLowerCase();

                var reason = event.metadata && event.metadata.reason ? event.metadata.reason : '—';
                [
                    displayDate(event.createdAt),
                    event.userId || '—',
                    humanize(event.action),
                    event.formId || '—',
                    (event.resourceType || '—') + (event.resourceId ? ' #' + event.resourceId : ''),
                    humanize(event.outcome),
                    humanize(reason)
                ].forEach(function (value, index) {
                    var cell = document.createElement('td');
                    cell.textContent = value;
                    if (index === 5) cell.className = 'careforms-audit-outcome careforms-audit-outcome-' + (event.outcome || '').toLowerCase();
                    row.appendChild(cell);
                });
                tbody.appendChild(row);
            });

            wrap.appendChild(table);
            mount.appendChild(wrap);

            controls.querySelector('input').addEventListener('input', function (event) {
                var query = event.target.value.trim().toLowerCase();
                tbody.querySelectorAll('tr').forEach(function (row) {
                    row.hidden = query !== '' && row.dataset.filter.indexOf(query) === -1;
                });
            });
        }).catch(function (error) {
            mount.innerHTML = '<div class="careforms-empty-state"><h3>Could not load audit log</h3><p></p></div>';
            mount.querySelector('p').textContent = error.message;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tab = document.querySelector('.careforms-tab[data-view="audit"]');
        if (!tab) return;

        request('/api/access').then(function (access) {
            tab.hidden = !access.canViewAudit;
        }).catch(function () {
            tab.hidden = true;
        });

        tab.addEventListener('click', renderAudit);
    });
}());