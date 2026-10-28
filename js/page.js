(function () {
    'use strict';

    window.CareForms = window.CareForms || {};
    var activeSubmission = null;
    var activePatient = null;
    var accessState = null;
    var patientCache = [];
    var formListState = {page:1,pageSize:25,search:'',pagination:null};
    var formPageRequestSequence = 0;

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

    function loadDefinitionVersion(formId, version) {
        return request('/api/form-definitions/' + encodeURIComponent(formId) + '/' + encodeURIComponent(version), {method:'GET'});
    }
    function canUseForm(formId) { return accessState && Array.isArray(accessState.forms) && accessState.forms.indexOf(formId) !== -1; }
    function findPatient(id) { return patientCache.find(function (patient) { return Number(patient.id) === Number(id); }) || null; }
    function statusLabel(status) {
        if (status === 'submitted') return 'Pending approval';
        if (status === 'returned') return 'Returned for correction';
        if (status === 'approved') return 'Approved';
        return 'Draft';
    }
    function isSubmissionReadOnly(status) { return status !== 'draft'; }
    function integrityLabel(submission){
        var status=submission && submission.integrity && submission.integrity.status;
        if(status==='valid') return 'Integrity verified';
        if(status==='legacy-valid') return 'Legacy integrity verified';
        if(status==='invalid'||status==='unavailable') return 'Integrity failed';
        return '';
    }
    function signatureInfo(submission) {
        if (!submission) return null;
        return {signatureData:submission.signatureData,signedBy:submission.signedBy,signerName:submission.signerName,signedAt:submission.signedAt,integrityHash:submission.integrityHash,integrity:submission.integrity};
    }

    function loadFormDefinitions(page, pageSize, search) {
        var query=window.CareForms.ListPagination.query(page||1,pageSize||25,search||'');
        return request('/api/form-definitions?'+query, {method:'GET'}).then(function (payload) {
            var result=window.CareForms.ListPagination.payload(payload),items=result.items;
            window.CareForms.formDefinitions = window.CareForms.formDefinitions || {};
            items.forEach(function (definition) {
                window.CareForms.formDefinitions[definition.id] = definition;
            });
            return result;
        });
    }

    function loadPatients() {
        if (patientCache.length) return Promise.resolve(patientCache);
        return request('/api/patients?page=1&pageSize=100', {method:'GET'}).then(function (payload) { var result=window.CareForms.ListPagination.payload(payload);patientCache=result.items;return patientCache; });
    }

    function applyAccessToNavigation() {
        var capabilities = accessState && Array.isArray(accessState.capabilities) ? accessState.capabilities : [];
        function has(capability) { return capabilities.indexOf(capability) !== -1; }
        function setVisible(view, visible) {
            var tab = document.querySelector('.careforms-tab[data-view="' + view + '"]');
            if (tab) tab.hidden = !visible;
        }

        setVisible('work', !!accessState);
        setVisible('forms', !!accessState && Array.isArray(accessState.forms) && accessState.forms.length > 0);
        setVisible('review', has('submission.review'));
        setVisible('patients', has('patient.select') || has('patient.view') || has('patient.manage'));
        setVisible('reports', has('report.view'));
        setVisible('form-admin', has('form.manage'));
        setVisible('audit', !!accessState && !!accessState.canViewAudit);
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

        // Dedicated modules listen for this event after the target panel has
        // actually been activated. This avoids relying on click-listener order.
        document.dispatchEvent(new CustomEvent('careforms:view-shown', { detail: { view: viewName } }));
    }

    function renderFormsBrowser() {
        activeSubmission = null;
        activePatient = null;
        var mount = document.getElementById('careforms-forms-browser');
        if (!mount) return;
        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Forms</h2><p class="careforms-muted">Select a form, then choose the patient for this submission.</p></div></div><div class="careforms-list-toolbar"><input type="search" placeholder="Search forms" aria-label="Search forms"><span class="careforms-muted">Search by name, ID or category</span></div><div data-form-page><p class="careforms-muted">Loading forms…</p></div><div data-form-pagination></div>';
        var search=mount.querySelector('input[type="search"]');search.value=formListState.search;search.addEventListener('input',window.CareForms.ListPagination.debounce(function(){formListState.search=search.value.trim();formListState.page=1;loadFormPage(mount);},250));
        loadFormPage(mount);
    }

    function loadFormPage(mount) {
        var content=mount.querySelector('[data-form-page]');content.innerHTML='<p class="careforms-muted">Loading forms…</p>';
        var sequence=++formPageRequestSequence;
        loadFormDefinitions(formListState.page,formListState.pageSize,formListState.search).then(function(result){
        if(sequence!==formPageRequestSequence)return;
        formListState.pagination=result.pagination;content.innerHTML='';var allowed=result.items.filter(function (d) { return canUseForm(d.id); });
        if (!allowed.length) { content.innerHTML = '<div class="careforms-empty-state"><h3>No forms found</h3><p>Try another search or ask an administrator to assign a form.</p></div>'; }
        window.CareForms.FormCategoryGroups.group(allowed).forEach(function (category) {
            var section = document.createElement('details'); section.className = 'careforms-form-category careforms-category-group';
            var storageKey=window.CareForms.FormCategoryGroups.storageKey('forms',accessState&&accessState.userId,category.id);var stored=null;try{stored=window.localStorage.getItem(storageKey);}catch(error){}section.open=stored===null?true:stored==='open';
            var title = document.createElement('summary');title.innerHTML='<span></span><span class="careforms-category-count"></span>';title.querySelector('span').textContent=category.label;title.querySelector('.careforms-category-count').textContent=category.forms.length+' on this page';section.appendChild(title);
            section.addEventListener('toggle',function(){try{window.localStorage.setItem(storageKey,section.open?'open':'closed');}catch(error){}});
            var grid = document.createElement('div'); grid.className = 'careforms-form-card-grid';
            category.forms.forEach(function (definition) {
                var card = document.createElement('button'); card.type='button'; card.className='careforms-form-card';
                var icon = document.createElement('span'); icon.className='careforms-form-card-icon'; icon.textContent='▤'; icon.setAttribute('aria-hidden','true');
                var body = document.createElement('span'); body.className='careforms-form-card-body';
                var strong=document.createElement('strong'); strong.textContent=definition.name;
                var desc=document.createElement('span'); desc.textContent=definition.description;
                body.appendChild(strong); body.appendChild(desc); card.appendChild(icon); card.appendChild(body);
                card.addEventListener('click', function () { choosePatientForForm(definition, mount); });
                grid.appendChild(card);
            });
            section.appendChild(grid); content.appendChild(section);
        });
        window.CareForms.ListPagination.render(mount.querySelector('[data-form-pagination]'),result.pagination,'form',function(page){formListState.page=page;loadFormPage(mount);},function(size){formListState.pageSize=size;formListState.page=1;loadFormPage(mount);});
        }).catch(function(error){if(sequence!==formPageRequestSequence)return;content.innerHTML='<div class="careforms-empty-state"><h3>Could not load forms</h3><p></p></div>';content.querySelector('p').textContent=error.message;});
    }

    function choosePatientForForm(definition, mount) {
        if (!canUseForm(definition.id)) { notify('You do not have permission to use this form.'); return; }
        mount.innerHTML = '<div class="careforms-form-header"><button type="button" class="careforms-secondary-button" data-action="back-to-forms">Back to Forms</button><div><h2>Select Patient</h2><p class="careforms-muted"></p></div></div><div class="careforms-patient-picker"><input type="search" placeholder="Search by patient name or MR#" aria-label="Search patients"><div class="careforms-patient-picker-list"><p class="careforms-muted">Loading patients…</p></div><div data-patient-picker-pagination></div></div>';
        mount.querySelector('.careforms-muted').textContent = 'Starting ' + definition.name + ' · Version ' + definition.version;
        var input = mount.querySelector('input[type="search"]'); var list = mount.querySelector('.careforms-patient-picker-list');
        var pickerState={page:1,pageSize:10,search:'',pagination:null};
        function loadPickerPage() {
            list.innerHTML='<p class="careforms-muted">Loading patients…</p>';
            var query=window.CareForms.ListPagination.query(pickerState.page,pickerState.pageSize,pickerState.search);
            request('/api/patients?'+query,{method:'GET'}).then(function(payload){var result=window.CareForms.ListPagination.payload(payload),patients=result.items;pickerState.pagination=result.pagination;
                list.innerHTML = '';
                if (!patients.length) { list.innerHTML = '<div class="careforms-empty-state"><h3>No patients found</h3><p>Try another name or MR#.</p></div>'; }
                patients.forEach(function (patient) {
                    if(!patientCache.some(function(item){return Number(item.id)===Number(patient.id);}))patientCache.push(patient);
                    var button = document.createElement('button'); button.type = 'button'; button.className = 'careforms-patient-choice'; button.innerHTML = '<strong></strong><span></span>';
                    button.querySelector('strong').textContent = patient.displayName;
                    button.querySelector('span').textContent = 'MR# ' + patient.medicalRecordNumber + (patient.dateOfBirth ? ' · DOB ' + patient.dateOfBirth : '');
                    button.addEventListener('click', function () { openNewForm(definition, mount, patient); }); list.appendChild(button);
                });
                window.CareForms.ListPagination.render(mount.querySelector('[data-patient-picker-pagination]'),result.pagination,'patient',function(page){pickerState.page=page;loadPickerPage();},function(size){pickerState.pageSize=size;pickerState.page=1;loadPickerPage();});
            }).catch(function (error) { list.innerHTML = '<div class="careforms-empty-state"><h3>Could not load patients</h3><p></p></div>'; list.querySelector('p').textContent = error.message; });
        }
        input.addEventListener('input',window.CareForms.ListPagination.debounce(function(){pickerState.search=input.value.trim();pickerState.page=1;loadPickerPage();},250));loadPickerPage();
    }

    function patientValues(patient) {
        if (!patient) return {};
        return {patient_name:patient.displayName,patientName:patient.displayName,mr_number:patient.medicalRecordNumber,mrn:patient.medicalRecordNumber,medical_record_number:patient.medicalRecordNumber,date_of_birth:patient.dateOfBirth || ''};
    }

    function currentUserValues() {
        if (!accessState || !accessState.displayName) return {};
        return {aide_name:accessState.displayName,nurse_name:accessState.displayName};
    }

    function localDateValue() {
        var now = new Date();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');
        return now.getFullYear() + '-' + month + '-' + day;
    }

    function sourceValues(definition, patient) {
        var values = {};
        var sections = definition.sections || definition.fields || [];
        sections.forEach(function (section) {
            (section.fields || []).forEach(function (field) {
                if (!field.source || field.source === 'manual') return;
                if (field.source === 'patient.name' && patient) values[field.id] = patient.displayName || '';
                if (field.source === 'patient.mr_number' && patient) values[field.id] = patient.medicalRecordNumber || '';
                if (field.source === 'patient.date_of_birth' && patient) values[field.id] = patient.dateOfBirth || '';
                if (field.source === 'current_user.display_name') values[field.id] = accessState && accessState.displayName ? accessState.displayName : '';
                if (field.source === 'system.current_date') values[field.id] = localDateValue();
            });
        });
        return values;
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
        window.CareForms.FormRenderer.render(definition,mount,{values:Object.assign({},patientValues(patient),currentUserValues(),sourceValues(definition,patient)),patient:patient,onSaveDraft:function(data,b){saveDraft(definition,data,b,mount);},onSubmit:function(data,b,sig){submitForm(definition,data,b,mount,sig);}});
    }

    function renderSubmission(definition, submission, mount) {
        if (!canUseForm(definition.id)) { notify('You no longer have permission to access this form.'); showView('work'); return; }
        activeSubmission=submission; activePatient=findPatient(submission.patientId);
        var renderDefinition = Object.assign({}, definition, {version: Number(submission.formVersion) || definition.version});
        window.CareForms.FormRenderer.render(renderDefinition,mount,{values:submission.data||{},patient:activePatient,signature:signatureInfo(submission),readOnly:isSubmissionReadOnly(submission.status),backLabel:'Back to My Work',onSaveDraft:function(data,b){saveDraft(renderDefinition,data,b,mount);},onSubmit:function(data,b,sig){submitForm(renderDefinition,data,b,mount,sig);}});
        if(window.CareForms.Addenda)window.CareForms.Addenda.render(submission,renderDefinition,mount,{canCreate:submission.status!=='draft'});
    }

    function renderMyWork() {
        activeSubmission=null; activePatient=null;
        var mount=document.getElementById('careforms-work-browser'); if(!mount) return;
        mount.innerHTML='<div class="careforms-section-heading"><div><h2>My Work</h2><p class="careforms-muted">Drafts and your recent submissions.</p></div></div><p class="careforms-muted">Loading…</p>';
        Promise.all([request('/api/submissions',{method:'GET'}), loadPatients().catch(function(){ return []; })]).then(function(results){
            var submissions=results[0]; patientCache=results[1] || patientCache;
            mount.innerHTML='<div class="careforms-section-heading"><div><h2>My Work</h2><p class="careforms-muted">Drafts and your recent submissions.</p></div></div>';
            if(!accessState || (!accessState.forms.length && !accessState.canViewReports)){ mount.innerHTML+='<div class="careforms-empty-state"><h3>No forms assigned</h3><p>You currently do not have permission to fill any CareForms forms. Ask an administrator to assign your Nextcloud group to a form.</p></div>'; return; }
            if(!submissions.length){ mount.innerHTML+='<div class="careforms-empty-state"><h3>No assigned work yet</h3><p>Start a permitted form and save it as a draft. It will appear here.</p></div>'; return; }
            var list=document.createElement('div'); list.className='careforms-submission-list';
            submissions.forEach(function(s){
                var d=getDefinition(s.formId) || {id:s.formId,name:s.formId}; if(!canUseForm(s.formId)) return;
                var item=document.createElement('button'); item.type='button'; item.className='careforms-submission-item';
                var main=document.createElement('span'); main.className='careforms-submission-main';
                var patient=findPatient(s.patientId); var patientName=patient ? patient.displayName : (s.data && s.data.patient_name ? s.data.patient_name : 'Legacy / unassigned patient');
                main.innerHTML='<strong></strong><span></span>'; main.querySelector('strong').textContent=patientName;
                var integrityText=integrityLabel(s);
                main.querySelector('span').textContent=d.name+' v'+s.formVersion+' · Updated '+(s.updatedAt ? new Date(s.updatedAt*1000).toLocaleString() : '—')+(integrityText?' · '+integrityText:'');
                var status=document.createElement('span'); status.className='careforms-status careforms-status-'+s.status; status.textContent=statusLabel(s.status);
                item.appendChild(main); item.appendChild(status); item.addEventListener('click',function(){
                    loadDefinitionVersion(s.formId, s.formVersion)
                        .then(function(versionDefinition){ renderSubmission(versionDefinition,s,mount); })
                        .catch(function(error){ notify(error.message); });
                }); list.appendChild(item);
            });
            mount.appendChild(list);
        }).catch(function(e){ mount.innerHTML='<div class="careforms-empty-state"><h3>Could not load My Work</h3><p></p></div>'; mount.querySelector('p').textContent=e.message; });
    }

    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.careforms-tab').forEach(function(tab){tab.addEventListener('click',function(){showView(tab.dataset.view);});});
        document.addEventListener('click',function(event){var target=event.target.closest('[data-action="back-to-forms"]'); if(!target)return; if(activeSubmission)showView('work');else renderFormsBrowser();});
        request('/api/access',{method:'GET'}).then(function(access){accessState=access;return loadFormDefinitions().then(function(){definitions();applyAccessToNavigation();var version=document.querySelector('[data-careforms-version]');if(version)version.textContent=access.appVersion||'—';renderMyWork();});}).catch(function(e){notify(e.message);});
    });
}());
