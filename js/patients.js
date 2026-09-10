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
    function definition(formId) {
        var definitions = window.CareForms && window.CareForms.formDefinitions ? window.CareForms.formDefinitions : {};
        return Object.keys(definitions).map(function (key) { return definitions[key]; }).find(function (item) { return item.id === formId; });
    }
    function formatDate(timestamp) { return timestamp ? new Date(timestamp * 1000).toLocaleString() : '—'; }

    var accessState = null;
    var patients = [];
    var selectedPatientId = null;

    function renderPatients() {
        var mount = document.getElementById('careforms-patients-browser');
        if (!mount) return;
        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Patients</h2><p class="careforms-muted">Search the patient registry and review the patient\'s CareForms history.</p></div></div><div class="careforms-patient-master"><aside class="careforms-patient-list-pane"><div class="careforms-patient-list-tools"><input type="search" id="careforms-patient-search" placeholder="Search name or MR#" aria-label="Search patients"><button type="button" id="careforms-add-patient" hidden>Add Patient</button></div><div id="careforms-patient-list"><p class="careforms-muted">Loading patients…</p></div></aside><section class="careforms-patient-detail-pane" id="careforms-patient-detail"><div class="careforms-empty-state"><h3>Select a patient</h3><p>Choose a patient to view their form history.</p></div></section></div>';

        var addButton = document.getElementById('careforms-add-patient');
        addButton.hidden = !accessState || !accessState.canManagePatients;
        addButton.addEventListener('click', renderAddPatient);
        document.getElementById('careforms-patient-search').addEventListener('input', renderPatientList);

        request('/api/patients', {method:'GET'}).then(function (items) {
            patients = items;
            renderPatientList();
            if (selectedPatientId && patients.some(function (p) { return Number(p.id) === Number(selectedPatientId); })) showPatient(selectedPatientId);
        }).catch(function (error) {
            document.getElementById('careforms-patient-list').innerHTML = '<div class="careforms-empty-state"><h3>Could not load patients</h3><p></p></div>';
            document.getElementById('careforms-patient-list').querySelector('p').textContent = error.message;
        });
    }

    function renderPatientList() {
        var list = document.getElementById('careforms-patient-list');
        var search = document.getElementById('careforms-patient-search');
        if (!list || !search) return;
        var term = search.value.trim().toLowerCase();
        var filtered = patients.filter(function (patient) {
            return !term || patient.displayName.toLowerCase().indexOf(term) !== -1 || patient.medicalRecordNumber.toLowerCase().indexOf(term) !== -1;
        });
        list.innerHTML = '';
        if (!filtered.length) {
            list.innerHTML = '<div class="careforms-empty-state"><h3>No patients found</h3><p>Try another name or MR#.</p></div>';
            return;
        }
        filtered.forEach(function (patient) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'careforms-patient-list-item' + (Number(patient.id) === Number(selectedPatientId) ? ' active' : '');
            button.innerHTML = '<strong></strong><span></span>';
            button.querySelector('strong').textContent = patient.displayName;
            button.querySelector('span').textContent = 'MR# ' + patient.medicalRecordNumber;
            button.addEventListener('click', function () { selectedPatientId = patient.id; renderPatientList(); showPatient(patient.id); });
            list.appendChild(button);
        });
    }

    function renderAddPatient() {
        var detail = document.getElementById('careforms-patient-detail');
        detail.innerHTML = '<div class="careforms-patient-detail-header"><div><h3>Add Patient</h3><p class="careforms-muted">Create the patient once. Future form submissions will select this master record.</p></div></div><form id="careforms-add-patient-form" class="careforms-patient-form"><label>Medical Record Number *<input name="medicalRecordNumber" required maxlength="64"></label><label>First Name *<input name="firstName" required maxlength="128"></label><label>Last Name *<input name="lastName" required maxlength="128"></label><label>Date of Birth<input name="dateOfBirth" type="date"></label><div class="careforms-form-actions"><button type="button" class="careforms-secondary-button" id="careforms-cancel-patient">Cancel</button><button type="submit">Add Patient</button></div></form>';
        var form = document.getElementById('careforms-add-patient-form');
        document.getElementById('careforms-cancel-patient').addEventListener('click', function () { selectedPatientId ? showPatient(selectedPatientId) : renderPatients(); });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var data = Object.fromEntries(new FormData(form).entries());
            request('/api/patients', {method:'POST', body:JSON.stringify(data)}).then(function (patient) {
                patients.push(patient);
                patients.sort(function (a,b) { return a.displayName.localeCompare(b.displayName); });
                selectedPatientId = patient.id;
                renderPatientList();
                showPatient(patient.id);
            }).catch(function (error) {
                if (OC.Notification && OC.Notification.showTemporary) OC.Notification.showTemporary(error.message);
            });
        });
    }

    function showPatient(id) {
        var detail = document.getElementById('careforms-patient-detail');
        detail.innerHTML = '<p class="careforms-muted">Loading patient details…</p>';
        request('/api/patients/' + encodeURIComponent(id), {method:'GET'}).then(function (payload) {
            var patient = payload.patient;
            detail.innerHTML = '<div class="careforms-patient-detail-header"><div><h3></h3><p class="careforms-muted"></p></div></div><dl class="careforms-patient-summary"><div><dt>MR#</dt><dd></dd></div><div><dt>Date of birth</dt><dd></dd></div><div><dt>Status</dt><dd></dd></div></dl><div class="careforms-section-heading"><div><h3>Form History</h3><p class="careforms-muted">All CareForms submissions linked to this patient.</p></div></div><div id="careforms-patient-submissions"></div>';
            detail.querySelector('.careforms-patient-detail-header h3').textContent = patient.displayName;
            detail.querySelector('.careforms-patient-detail-header p').textContent = 'Patient #' + patient.id;
            var values = detail.querySelectorAll('.careforms-patient-summary dd');
            values[0].textContent = patient.medicalRecordNumber;
            values[1].textContent = patient.dateOfBirth || '—';
            values[2].textContent = patient.status;
            renderSubmissionHistory(payload.submissions || [], detail.querySelector('#careforms-patient-submissions'), patient);
        }).catch(function (error) {
            detail.innerHTML = '<div class="careforms-empty-state"><h3>Could not load patient</h3><p></p></div>';
            detail.querySelector('p').textContent = error.message;
        });
    }

    function renderSubmissionHistory(submissions, mount, patient) {
        if (!submissions.length) {
            mount.innerHTML = '<div class="careforms-empty-state"><h3>No form history</h3><p>No CareForms submissions are linked to this patient yet.</p></div>';
            return;
        }
        submissions.forEach(function (submission) {
            var formDefinition = definition(submission.formId);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'careforms-patient-submission-item';
            button.innerHTML = '<span><strong></strong><small></small></span><span class="careforms-status"></span>';
            button.querySelector('strong').textContent = formDefinition ? formDefinition.name : submission.formId;
            button.querySelector('small').textContent = 'Version ' + submission.formVersion + ' · ' + formatDate(submission.submittedAt || submission.updatedAt);
            var status = button.querySelector('.careforms-status');
            status.classList.add('careforms-status-' + submission.status);
            status.textContent = submission.status === 'submitted' ? 'Submitted' : 'Draft';
            button.addEventListener('click', function () { renderPatientSubmission(submission, formDefinition, patient); });
            mount.appendChild(button);
        });
    }

    function renderPatientSubmission(submission, formDefinition, patient) {
        var detail = document.getElementById('careforms-patient-detail');
        if (!formDefinition || !window.CareForms.FormRenderer) {
            detail.innerHTML = '<button type="button" class="careforms-secondary-button" id="careforms-back-patient">Back to Patient</button><div class="careforms-empty-state"><h3>Form definition unavailable</h3><p>The stored submission is linked to a form definition that is not available in this build.</p></div>';
        } else {
            window.CareForms.FormRenderer.render(formDefinition, detail, {values:submission.data || {}, patient:patient, readOnly:true, backLabel:'Back to Patient'});
            var back = detail.querySelector('[data-action="back-to-forms"]');
            if (back) {
                back.removeAttribute('data-action');
                back.addEventListener('click', function () { showPatient(patient.id); });
            }
        }
        var fallback = document.getElementById('careforms-back-patient');
        if (fallback) fallback.addEventListener('click', function () { showPatient(patient.id); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tab = document.querySelector('.careforms-tab[data-view="patients"]');
        if (!tab) return;
        request('/api/access', {method:'GET'}).then(function (access) {
            accessState = access;
            tab.hidden = !access.canViewPatients;
        }).catch(function () { tab.hidden = true; });
        tab.addEventListener('click', renderPatients);
    });
}());