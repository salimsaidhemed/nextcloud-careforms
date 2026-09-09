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

    function renderReports() {
        var mount = document.getElementById('careforms-reports-browser');
        if (!mount) return;
        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Reports</h2><p class="careforms-muted">Aggregate operational and clinical indicators from submitted forms.</p></div></div><p class="careforms-muted">Loading…</p>';

        request('/api/reports/overview').then(function (report) {
            mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Reports</h2><p class="careforms-muted">Aggregate operational and clinical indicators from submitted forms.</p></div></div>';

            var cards = document.createElement('div'); cards.className = 'careforms-report-card-grid';
            cards.appendChild(card('Submitted forms', report.summary.submittedTotal, 'All time'));
            cards.appendChild(card('Last 30 days', report.summary.submittedLast30Days, 'Submitted records'));
            cards.appendChild(card('Active submitters', report.summary.activeSubmitters, 'Users with submitted work'));
            cards.appendChild(card('Aide notes', report.summary.aideSubmitted, 'Submitted'));
            cards.appendChild(card('Nursing notes', report.summary.nurseSubmitted, 'Submitted'));
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
        }).catch(function (error) {
            mount.innerHTML = '<div class="careforms-empty-state"><h3>Could not load reports</h3><p></p></div>';
            mount.querySelector('p').textContent = error.message;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tab = document.querySelector('.careforms-tab[data-view="reports"]');
        if (tab) tab.addEventListener('click', renderReports);
    });
}());