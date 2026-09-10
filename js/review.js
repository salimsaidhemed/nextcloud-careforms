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
    function notify(message) { if (OC.Notification && OC.Notification.showTemporary) OC.Notification.showTemporary(message); else window.alert(message); }
    function definition(formId, version) {
        var definitions = window.CareForms && window.CareForms.formDefinitions ? window.CareForms.formDefinitions : {};
        var item = Object.keys(definitions).map(function (key) { return definitions[key]; }).find(function (candidate) {
            return candidate && candidate.id === formId;
        }) || null;
        return item ? Object.assign({}, item, {version:Number(version) || item.version}) : null;
    }

    function renderQueue() {
        var mount = document.getElementById('careforms-review-browser');
        if (!mount) return;
        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Review Queue</h2><p class="careforms-muted">Submitted forms awaiting supervisor review.</p></div></div><p class="careforms-muted">Loading…</p>';

        Promise.all([
            request('/api/review/submissions', {method:'GET'}),
            request('/api/patients', {method:'GET'}).catch(function () { return []; })
        ]).then(function (results) {
            var submissions = results[0];
            var patients = results[1];
            var patientMap = {};
            patients.forEach(function (p) { patientMap[p.id] = p; });
            mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Review Queue</h2><p class="careforms-muted">Submitted forms awaiting supervisor review.</p></div></div>';
            if (!submissions.length) {
                mount.innerHTML += '<div class="careforms-empty-state"><h3>Nothing waiting for review</h3><p>Newly submitted forms will appear here.</p></div>';
                return;
            }
            var list = document.createElement('div');
            list.className = 'careforms-submission-list';
            var rendered = 0;
            submissions.forEach(function (submission) {
                var d = definition(submission.formId, submission.formVersion);
                if (!d) return;
                rendered += 1;
                var patient = patientMap[submission.patientId];
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'careforms-submission-item';
                var main = document.createElement('span');
                main.className = 'careforms-submission-main';
                main.innerHTML = '<strong></strong><span></span>';
                main.querySelector('strong').textContent = patient ? patient.displayName : 'Patient #' + submission.patientId;
                main.querySelector('span').textContent = d.name + ' v' + submission.formVersion + ' · Submitted ' + (submission.submittedAt ? new Date(submission.submittedAt * 1000).toLocaleString() : '—');
                var status = document.createElement('span');
                status.className = 'careforms-status careforms-status-submitted';
                status.textContent = 'Awaiting review';
                item.appendChild(main); item.appendChild(status);
                item.addEventListener('click', function () { renderReview(submission, d, patient, mount); });
                list.appendChild(item);
            });
            if (!rendered) {
                mount.innerHTML += '<div class="careforms-empty-state"><h3>Could not display queued forms</h3><p>The queued submissions reference form definitions that are not available in this browser session.</p></div>';
                return;
            }
            mount.appendChild(list);
        }).catch(function (error) {
            mount.innerHTML = '<div class="careforms-empty-state"><h3>Could not load Review Queue</h3><p></p></div>';
            mount.querySelector('p').textContent = error.message;
        });
    }

    function renderReview(submission, d, patient, mount) {
        window.CareForms.FormRenderer.render(d, mount, {
            values: submission.data || {},
            patient: patient || null,
            readOnly: true,
            backLabel: 'Back to Review Queue'
        });

        var box = document.createElement('section');
        box.className = 'careforms-review-actions';
        box.innerHTML = '<h3>Supervisor Review</h3><p class="careforms-muted">Approve this submission, or return it with a correction note.</p><textarea rows="3" placeholder="Correction note (required when returning)"></textarea><div><button type="button" class="careforms-secondary-button" data-review="return">Return for correction</button> <button type="button" class="careforms-primary-button" data-review="approve">Approve</button></div>';
        mount.appendChild(box);

        box.querySelector('[data-review="approve"]').addEventListener('click', function () { decide('approve'); });
        box.querySelector('[data-review="return"]').addEventListener('click', function () { decide('return'); });
        function decide(action) {
            var note = box.querySelector('textarea').value.trim();
            if (action === 'return' && !note) { notify('Enter a correction note before returning the submission.'); return; }
            box.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
            request('/api/review/submissions/' + submission.id, {method:'POST', body:JSON.stringify({action:action,note:note})})
                .then(function () { notify(action === 'approve' ? 'Submission approved.' : 'Submission returned for correction.'); renderQueue(); })
                .catch(function (error) { notify(error.message); box.querySelectorAll('button').forEach(function (b) { b.disabled = false; }); });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        request('/api/access', {method:'GET'}).then(function (access) {
            var canReview = Array.isArray(access.capabilities) && access.capabilities.indexOf('submission.review') !== -1;
            var tab = document.querySelector('.careforms-tab[data-view="review"]');
            if (tab) tab.hidden = !canReview;
            if (!canReview) return;
            tab.addEventListener('click', function () {
                document.querySelectorAll('.careforms-tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
                document.querySelectorAll('[data-view-panel]').forEach(function (panel) { panel.hidden = panel.dataset.viewPanel !== 'review'; });
                renderQueue();
            });
        }).catch(function () {});

        document.addEventListener('click', function (event) {
            var back = event.target.closest('[data-action="back-to-forms"]');
            var reviewPanel = document.querySelector('[data-view-panel="review"]');
            if (back && reviewPanel && !reviewPanel.hidden) { event.preventDefault(); renderQueue(); }
        }, true);
    });
}());