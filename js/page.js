(function () {
    'use strict';

    window.CareForms = window.CareForms || {};
    var activeSubmission = null;
    var activePatient = null;
    var accessState = null;
    var patientCache = [];

    function apiUrl(path) { return OC.generateUrl('/apps/careforms' + path); }
    function notify(message) { if (OC.Notification && OC.Notification.showTemporary) OC.Notification.showTemporary(message); else window.alert(message); }
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
    function definitions() {
        var list = Object.keys(window.CareForms.formDefinitions || {}).map(function (key) { return window.CareForms.formDefinitions[key]; });
        if (accessState && accessState.formVersions) {
            list.forEach(function (definition) {
                if (accessState.formVersions[definition.id]) definition.version = accessState.formVersions[definition.id];
            });
        }
        return list;
    }
    function getDefinition(formId) { return definitions().find(function (d) { return d.id === formId; }); }
    function canUseForm(formId) { return accessState && Array.isArray(accessState.forms) && accessState.forms.indexOf(formId) !== -1; }
    function findPatient(id) { return patientCache.find(function (patient) { return Number(patient.id) === Number(id); }) || null; }
    function statusLabel(status) {
        if (status === 'submitted') return 'Pending approval';
        if (status === 'returned') return 'Returned for correction';
        if (status === 'approved') return 'Approved';
        return 'Draft';
    }
    function isSubmissionReadOnly(status) { return status === 'submitted' || status === 'approved'; }
    function signatureInfo(submission) {
        if (!submission) return null;
        return {signatureData:submission.signatureData,signedBy:submission.signedBy,signerName:submission.signerName,signedAt:submission.signedAt,integrityHash:submission.integrityHash};
    }

    function loadPatients() {
        if (patientCache.length) return Promise.resolve(patientCache);
        return request('/api/patients', {method:'GET'}).then(function (patients) { patientCache = patients; return patients; });
    }

    function applyAccessToNavigation() {
        var formsTab = document.querySelector('.careforms-tab[data-view="forms"]');
        var reportsTab = document.querySelector('.careforms-tab[data-view="reports"]');
        if (formsTab) formsTab.hidden = !accessState || !accessState.forms || accessState.forms.length === 0;
        if (reportsTab) reportsTab.hidden = !accessState || !accessState.canViewReports;
    }

    function showView(viewName) {
        if (viewName === 'forms' && (!accessState || !accessState.forms.length)) viewName = 'work';
        if (viewName === 'reports' && (!accessState || !accessState.canViewReports)) viewName = 'work';
        document.querySelectorAll('.careforms-tab').forEach(function (tab) { tab.classList.toggle('active', tab.dataset.view === viewName); });
        document.querySelectorAll('[data-view-panel]').forEach(function (panel) {
            var active = panel.dataset.viewPanel === viewName;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });
        if (viewName === 'forms') renderFormsBrowser();
        if (viewName === 'work') renderMyWork();
    }

    function renderFormsBrowser() {
        activeSubmission = null;
        activePatient = null;
        var mount = document.getElementById('careforms-forms-browser');
        if (!mount) return;
        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Forms</h2><p class="careforms-muted">Select a form, then choose the patient for this submission.</p></div></div>';
        var allowed = definitions().filter(function (d) { return canUseForm(d.id); });
        if (!allowed.length) { mount.innerHTML += '<div class="careforms-empty-state"><h3>No forms assigned</h3><p>Your CareForms role does not currently provide access to a form.</p></div>'; return; }
        var categories = {};
        allowed.forEach(function (d) { (categories[d.category] = categories[d.category] || []).push(d); });
        Object.keys(categories).sort().forEach(function (categoryName) {
            var section = document.createElement('section'); section.className = 'careforms-form-category';
            var title = document.createElement('h3'); title.textContent = categoryName; section.appendChild(title);
            var grid = document.createElement('div'); grid.className = 'careforms-form-card-grid';
            categories[categoryName].forEach(function (definition) {
                var card = document.createElement('button'); card.type='button'; card.className='careforms-form-card';
                var icon = document.createElement('span'); icon.className='careforms-form-card-icon'; icon.textContent='▤'; icon.setAttribute('aria-hidden','true');
                var body = document.createElement('span'); body.className='careforms-form-card-body';
                var strong=document.createElement('strong'); strong.textContent=definition.name;
                var desc=document.createElement('span'); desc.textContent=definition.description;
                body.appendChild(strong); body.appendChild(desc); card.appendChild(icon); card.appendChild(body);
                card.addEventListener('click', function () { choosePatientForForm(definition, mount); });
                grid.appendChild(card);
            });
            section.appendChild(grid); mount.appendChild(section);
        });
    }

    function choosePatientForForm(definition, mount) {
        if (!canUseForm(definition.id)) { notify('You do not have permission to use this form.'); return; }
        mount.innerHTML = '<div class="careforms-form-header"><button type="button" class="careforms-secondary-button" data-action="back-to-forms">Back to Forms</button><div><h2>Select Patient</h2><p class="careforms-muted"></p></div></div><div class="careforms-patient-picker"><input type="search" placeholder="Search by patient name or MR#" aria-label="Search patients"><div class="careforms-patient-picker-list"><p class="careforms-muted">Loading patients…</p></div></div>';
        mount.querySelector('.careforms-muted').textContent = 'Starting ' + definition.name + ' · Version ' + definition.version;
        var input = mount.querySelector('input[type="search"]'); var list = mount.querySelector('.careforms-patient-picker-list');
        loadPatients().then(function (patients) {
            function renderList() {
                var term = input.value.trim().toLowerCase();
                var filtered = patients.filter(function (patient) { return !term || patient.displayName.toLowerCase().indexOf(term) !== -1 || patient.medicalRecordNumber.toLowerCase().indexOf(term) !== -1; });
                list.innerHTML = '';
                if (!filtered.length) { list.innerHTML = '<div class="careforms-empty-state"><h3>No patients found</h3><p>Try another name or MR#.</p></div>'; return; }
                filtered.forEach(function (patient) {
                    var button = document.createElement('button'); button.type = 'button'; button.className = 'careforms-patient-choice'; button.innerHTML = '<strong></strong><span></span>';
                    button.querySelector('strong').textContent = patient.displayName;
                    button.querySelector('span').textContent = 'MR# ' + patient.medicalRecordNumber + (patient.dateOfBirth ? ' · DOB ' + patient.dateOfBirth : '');
                    button.addEventListener('click', function () { openNewForm(definition, mount, patient); }); list.appendChild(button);
                });
            }
            input.addEventListener('input', renderList); renderList();
        }).catch(function (error) { list.innerHTML = '<div class="careforms-empty-state"><h3>Could not load patients</h3><p></p></div>'; list.querySelector('p').textContent = error.message; });
    }

    function patientValues(patient) {
        if (!patient) return {};
        return {patient_name:patient.displayName,patientName:patient.displayName,mr_number:patient.medicalRecordNumber,mrn:patient.medicalRecordNumber,medical_record_number:patient.medicalRecordNumber,date_of_birth:patient.dateOfBirth || ''};
    }

    function saveDraft(definition, data, button, mount) {
        button.disabled=true; button.textContent='Saving…';
        var promise = activeSubmission && activeSubmission.id
            ? request('/api/submissions/' + activeSubmission.id, {method:'PUT', body:JSON.stringify({data:data})})
            : request('/api/submissions', {method:'POST', body:JSON.stringify({formId:definition.id, formVersion:definition.version, patientId:activePatient && activePatient.id, data:data})});
        promise.then(function (s) { activeSubmission=s; notify('Draft saved.'); renderSubmission(definition,s,mount); })
            .catch(function (e) { notify(e.message); button.disabled=false; button.textContent='Save Draft'; });
    }

    function submitForm(definition, data, button, mount, signatureData) {
        button.disabled=true; button.textContent='Signing…';
        var ensureDraft = activeSubmission && activeSubmission.id ? Promise.resolve(activeSubmission)
            : request('/api/submissions', {method:'POST', body:JSON.stringify({formId:definition.id, formVersion:definition.version, patientId:activePatient && activePatient.id, data:data})});
        ensureDraft.then(function (s) { activeSubmission=s; return request('/api/submissions/' + s.id + '/submit', {method:'POST', body:JSON.stringify({data:data,signatureData:signatureData})}); })
            .then(function (s) { activeSubmission=s; notify('Form electronically signed and submitted for approval.'); renderSubmission(definition,s,mount); })
            .catch(function (e) { notify(e.message); button.disabled=false; button.textContent='Sign & Submit'; });
    }

    function openNewForm(definition, mount, patient) {
        activeSubmission = null; activePatient = patient;
        window.CareForms.FormRenderer.render(definition,mount,{values:patientValues(patient),patient:patient,onSaveDraft:function(data,b){saveDraft(definition,data,b,mount);},onSubmit:function(data,b,sig){submitForm(definition,data,b,mount,sig);}});
    }

    function renderSubmission(definition, submission, mount) {
        if (!canUseForm(definition.id)) { notify('You no longer have permission to access this form.'); showView('work'); return; }
        activeSubmission=submission; activePatient=findPatient(submission.patientId);
        var renderDefinition = Object.assign({}, definition, {version: Number(submission.formVersion) || definition.version});
        window.CareForms.FormRenderer.render(renderDefinition,mount,{values:submission.data||{},patient:activePatient,signature:signatureInfo(submission),readOnly:isSubmissionReadOnly(submission.status),backLabel:'Back to My Work',onSaveDraft:function(data,b){saveDraft(renderDefinition,data,b,mount);},onSubmit:function(data,b,sig){submitForm(renderDefinition,data,b,mount,sig);}});
    }

    function renderMyWork() {
        activeSubmission=null; activePatient=null;
        var mount=document.getElementById('careforms-work-browser'); if(!mount) return;
        mount.innerHTML='<div class="careforms-section-heading"><div><h2>My Work</h2><p class="careforms-muted">Drafts and your recent submissions.</p></div></div><p class="careforms-muted">Loading…</p>';
        Promise.all([request('/api/submissions',{method:'GET'}), loadPatients().catch(function(){ return []; })]).then(function(results){
            var submissions=results[0]; patientCache=results[1] || patientCache;
            mount.innerHTML='<div class="careforms-section-heading"><div><h2>My Work</h2><p class="careforms-muted">Drafts and your recent submissions.</p></div></div>';
            if(!accessState || (!accessState.forms.length && !accessState.canViewReports)){ mount.innerHTML+='<div class="careforms-empty-state"><h3>No CareForms role assigned</h3><p>Ask an administrator to assign you to an appropriate CareForms group.</p></div>'; return; }
            if(!submissions.length){ mount.innerHTML+='<div class="careforms-empty-state"><h3>No assigned work yet</h3><p>Start a permitted form and save it as a draft. It will appear here.</p></div>'; return; }
            var list=document.createElement('div'); list.className='careforms-submission-list';
            submissions.forEach(function(s){
                var d=getDefinition(s.formId); if(!d || !canUseForm(s.formId)) return;
                var item=document.createElement('button'); item.type='button'; item.className='careforms-submission-item';
                var main=document.createElement('span'); main.className='careforms-submission-main';
                var patient=findPatient(s.patientId); var patientName=patient ? patient.displayName : (s.data && s.data.patient_name ? s.data.patient_name : 'Legacy / unassigned patient');
                main.innerHTML='<strong></strong><span></span>'; main.querySelector('strong').textContent=patientName;
                main.querySelector('span').textContent=d.name+' v'+s.formVersion+' · Updated '+(s.updatedAt ? new Date(s.updatedAt*1000).toLocaleString() : '—');
                var status=document.createElement('span'); status.className='careforms-status careforms-status-'+s.status; status.textContent=statusLabel(s.status);
                item.appendChild(main); item.appendChild(status); item.addEventListener('click',function(){renderSubmission(d,s,mount);}); list.appendChild(item);
            });
            mount.appendChild(list);
        }).catch(function(e){ mount.innerHTML='<div class="careforms-empty-state"><h3>Could not load My Work</h3><p></p></div>'; mount.querySelector('p').textContent=e.message; });
    }

    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.careforms-tab').forEach(function(tab){tab.addEventListener('click',function(){showView(tab.dataset.view);});});
        document.addEventListener('click',function(event){var target=event.target.closest('[data-action="back-to-forms"]'); if(!target)return; if(activeSubmission)showView('work');else renderFormsBrowser();});
        request('/api/access',{method:'GET'}).then(function(access){accessState=access;definitions();applyAccessToNavigation();renderMyWork();}).catch(function(e){notify(e.message);});
    });
}());