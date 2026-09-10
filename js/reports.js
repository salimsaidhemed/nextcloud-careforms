(function () {
    'use strict';

    function apiUrl(path) { return OC.generateUrl('/apps/careforms' + path); }
    function request(path) {
        return fetch(apiUrl(path), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'requesttoken': OC.requestToken }
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) throw new Error(body.message || 'CareForms request failed.');
                return body;
            });
        });
    }

    function card(label, value, helper) {
        var el = document.createElement('div');
        el.className = 'careforms-report-card';
        el.innerHTML = '<span class="careforms-report-label"></span><strong></strong><small></small>';
        el.querySelector('.careforms-report-label').textContent = label;
        el.querySelector('strong').textContent = value;
        el.querySelector('small').textContent = helper || '';
        return el;
    }

    function renderPairs(title, values, emptyText) {
        var section = document.createElement('section');
        section.className = 'careforms-report-panel';
        var heading = document.createElement('h3'); heading.textContent = title; section.appendChild(heading);
        var entries = Object.entries(values || {});
        if (!entries.length) {
            var empty = document.createElement('p'); empty.className = 'careforms-muted'; empty.textContent = emptyText; section.appendChild(empty);
            return section;
        }
        var list = document.createElement('div'); list.className = 'careforms-report-pairs';
        entries.forEach(function (entry) {
            var row = document.createElement('div');
            row.innerHTML = '<span></span><strong></strong>';
            row.querySelector('span').textContent = entry[0];
            row.querySelector('strong').textContent = entry[1];
            list.appendChild(row);
        });
        section.appendChild(list);
        return section;
    }

    function formDefinition(formId, version) {
        var definitions = window.CareForms && window.CareForms.formDefinitions ? window.CareForms.formDefinitions : {};
        var definition = Object.keys(definitions).map(function (key) { return definitions[key]; }).find(function (item) {
            return item && item.id === formId;
        });
        return definition ? Object.assign({}, definition, { version: Number(version) || definition.version }) : null;
    }

    function formName(formId) {
        var definition = formDefinition(formId, 1);
        return definition ? definition.name : formId;
    }

    function statusLabel(status) {
        if (status === 'approved') return 'Approved';
        if (status === 'submitted') return 'Pending approval';
        return status || 'Unknown';
    }

    function signatureInfo(submission) {
        return {
            signatureData: submission.signatureData,
            signedBy: submission.signedBy,
            signerName: submission.signerName,
            signedAt: submission.signedAt,
            integrityHash: submission.integrityHash
        };
    }

    function renderAggregate(mount, report) {
        var cards = document.createElement('div'); cards.className = 'careforms-report-card-grid';
        cards.appendChild(card('Finalized forms', report.summary.submittedTotal, 'Submitted or approved'));
        cards.appendChild(card('Last 30 days', report.summary.submittedLast30Days, 'Finalized records'));
        cards.appendChild(card('Active submitters', report.summary.activeSubmitters, 'Users with finalized work'));
        cards.appendChild(card('Aide notes', report.summary.aideSubmitted, 'Finalized'));
        cards.appendChild(card('Nursing notes', report.summary.nurseSubmitted, 'Finalized'));
        mount.appendChild(cards);

        var columns = document.createElement('div'); columns.className = 'careforms-report-columns';
        var nursing = document.createElement('section'); nursing.className = 'careforms-report-group';
        nursing.innerHTML = '<h2>Nursing</h2>';
        var nursingKpis = document.createElement('div'); nursingKpis.className = 'careforms-report-card-grid compact';
        nursingKpis.appendChild(card('Pain present', report.nursing.painPresent, 'Yes responses'));
        nursingKpis.appendChild(card('Medication changes', report.nursing.medicationChanges, 'Yes responses'));
        nursingKpis.appendChild(card('New orders', report.nursing.newOrders, 'Yes responses'));
        nursingKpis.appendChild(card('Wounds documented', report.nursing.woundDocumented, 'Location recorded'));
        nursing.appendChild(nursingKpis);
        nursing.appendChild(renderPairs('Visit types', report.nursing.visitTypes, 'No nursing visit types recorded yet.'));

        var aide = document.createElement('section'); aide.className = 'careforms-report-group';
        aide.innerHTML = '<h2>Home Health Aide</h2>';
        var aideKpis = document.createElement('div'); aideKpis.className = 'careforms-report-card-grid compact';
        aideKpis.appendChild(card('Average meal eaten', report.aide.averageMealEatenPercent === null ? '—' : report.aide.averageMealEatenPercent + '%', 'Recorded meal percentages'));
        aide.appendChild(aideKpis);
        aide.appendChild(renderPairs('Mental status observations', report.aide.mentalStatus, 'No observations recorded yet.'));
        aide.appendChild(renderPairs('Activities', report.aide.activities, 'No activities recorded yet.'));

        columns.appendChild(nursing); columns.appendChild(aide); mount.appendChild(columns);
    }

    function renderDetailedReports(mount, payload) {
        var items = payload.items || [];
        var section = document.createElement('section');
        section.className = 'careforms-entry-report-section';
        section.innerHTML = '<div class="careforms-section-heading"><div><h2>Data Entry Reports</h2><p class="careforms-muted">Search finalized submissions, preview the exact form data entered, and print individual records.</p></div></div>';

        var filters = document.createElement('div');
        filters.className = 'careforms-report-filters';
        filters.innerHTML = '' +
            '<input type="search" data-report-search placeholder="Search patient, MR#, submitter or form" aria-label="Search data entry reports">' +
            '<select data-report-form aria-label="Filter by form"><option value="">All forms</option><option value="home-health-aide-note">Home Health Aide Note</option><option value="nurses-progress-note">Nurses Progress Note</option></select>' +
            '<select data-report-status aria-label="Filter by status"><option value="">All statuses</option><option value="submitted">Pending approval</option><option value="approved">Approved</option></select>' +
            '<label>From <input type="date" data-report-from></label>' +
            '<label>To <input type="date" data-report-to></label>';
        section.appendChild(filters);

        var resultMeta = document.createElement('p'); resultMeta.className = 'careforms-muted careforms-report-result-count';
        section.appendChild(resultMeta);
        var results = document.createElement('div'); results.className = 'careforms-entry-report-list';
        section.appendChild(results);
        mount.appendChild(section);

        function matches(item) {
            var patient = item.patient || {};
            var search = filters.querySelector('[data-report-search]').value.trim().toLowerCase();
            var formFilter = filters.querySelector('[data-report-form]').value;
            var statusFilter = filters.querySelector('[data-report-status]').value;
            var from = filters.querySelector('[data-report-from]').value;
            var to = filters.querySelector('[data-report-to]').value;
            var submittedDate = item.submittedAt ? new Date(item.submittedAt * 1000).toISOString().slice(0, 10) : '';
            var haystack = [patient.displayName, patient.medicalRecordNumber, item.userId, formName(item.formId)].join(' ').toLowerCase();
            if (search && haystack.indexOf(search) === -1) return false;
            if (formFilter && item.formId !== formFilter) return false;
            if (statusFilter && item.status !== statusFilter) return false;
            if (from && (!submittedDate || submittedDate < from)) return false;
            if (to && (!submittedDate || submittedDate > to)) return false;
            return true;
        }

        function renderResults() {
            var filtered = items.filter(matches);
            resultMeta.textContent = filtered.length + ' record' + (filtered.length === 1 ? '' : 's') + ' shown';
            results.innerHTML = '';
            if (!filtered.length) {
                results.innerHTML = '<div class="careforms-empty-state"><h3>No matching records</h3><p>Change the filters or date range and try again.</p></div>';
                return;
            }

            filtered.forEach(function (item) {
                var patient = item.patient || {};
                var row = document.createElement('article'); row.className = 'careforms-entry-report-row';
                var copy = document.createElement('div'); copy.className = 'careforms-entry-report-copy';
                var patientName = patient.displayName || 'Legacy / unassigned patient';
                copy.innerHTML = '<strong></strong><span></span><small></small>';
                copy.querySelector('strong').textContent = patientName;
                copy.querySelector('span').textContent = formName(item.formId) + ' · Version ' + item.formVersion;
                copy.querySelector('small').textContent = 'MR# ' + (patient.medicalRecordNumber || '—') + ' · Submitted by ' + (item.userId || '—') + ' · ' + (item.submittedAt ? new Date(item.submittedAt * 1000).toLocaleString() : '—');

                var status = document.createElement('span');
                status.className = 'careforms-status careforms-status-' + item.status;
                status.textContent = statusLabel(item.status);

                var preview = document.createElement('button');
                preview.type = 'button';
                preview.className = 'careforms-secondary-button';
                preview.textContent = 'Preview';
                preview.addEventListener('click', function () { renderSubmissionPreview(section, item); });

                var actions = document.createElement('div'); actions.className = 'careforms-entry-report-actions';
                actions.appendChild(status); actions.appendChild(preview);
                row.appendChild(copy); row.appendChild(actions); results.appendChild(row);
            });
        }

        filters.addEventListener('input', renderResults);
        filters.addEventListener('change', renderResults);
        renderResults();
    }

    function renderSubmissionPreview(section, submission) {
        var definition = formDefinition(submission.formId, submission.formVersion);
        if (!definition || !window.CareForms || !window.CareForms.FormRenderer) return;

        var existing = section.querySelector('.careforms-report-submission-preview');
        if (existing) existing.remove();
        var preview = document.createElement('div');
        preview.className = 'careforms-report-submission-preview';
        section.appendChild(preview);

        window.CareForms.FormRenderer.render(definition, preview, {
            values: submission.data || {},
            patient: submission.patient || null,
            signature: signatureInfo(submission),
            readOnly: true,
            backLabel: 'Back to report results'
        });

        var meta = document.createElement('div');
        meta.className = 'careforms-report-preview-meta';
        meta.textContent = 'Submission #' + submission.id + ' · ' + statusLabel(submission.status) + ' · Submitted by ' + (submission.userId || '—') + (submission.reviewedBy ? ' · Reviewed by ' + submission.reviewedBy : '');
        var header = preview.querySelector('.careforms-form-header');
        if (header) header.insertAdjacentElement('afterend', meta);
        preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderReports() {
        var mount = document.getElementById('careforms-reports-browser');
        if (!mount) return;
        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Reports</h2><p class="careforms-muted">Operational summaries and finalized data-entry records.</p></div></div><p class="careforms-muted">Loading…</p>';

        Promise.all([
            request('/api/reports/overview'),
            request('/api/access')
        ]).then(function (result) {
            var report = result[0];
            var access = result[1];
            mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Reports</h2><p class="careforms-muted">Operational summaries and finalized data-entry records.</p></div></div>';
            renderAggregate(mount, report);

            var canViewDetail = Array.isArray(access.capabilities) && access.capabilities.indexOf('report.detail') !== -1;
            if (!canViewDetail) return;

            return request('/api/reports/submissions').then(function (payload) {
                renderDetailedReports(mount, payload);
            });
        }).catch(function (error) {
            mount.innerHTML = '<div class="careforms-empty-state"><h3>Could not load reports</h3><p></p></div>';
            mount.querySelector('p').textContent = error.message;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tab = document.querySelector('.careforms-tab[data-view="reports"]');
        if (tab) tab.addEventListener('click', renderReports);

        document.addEventListener('click', function (event) {
            var back = event.target.closest('.careforms-report-submission-preview [data-action="back-to-forms"]');
            if (!back) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            var preview = back.closest('.careforms-report-submission-preview');
            if (preview) preview.remove();
            var section = document.querySelector('.careforms-entry-report-section');
            if (section) section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, true);
    });
}());