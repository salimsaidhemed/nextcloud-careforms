(function () {
    'use strict';

    window.CareForms = window.CareForms || {};
    var activeSubmission = null;
    var accessState = null;

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
    function definitions() { return Object.keys(window.CareForms.formDefinitions || {}).map(function (key) { return window.CareForms.formDefinitions[key]; }); }
    function getDefinition(formId) { return definitions().find(function (d) { return d.id === formId; }); }
    function canUseForm(formId) { return accessState && Array.isArray(accessState.forms) && accessState.forms.indexOf(formId) !== -1; }

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
        var mount = document.getElementById('careforms-forms-browser');
        if (!mount) return;
        mount.innerHTML = '<div class="careforms-section-heading"><div><h2>Forms</h2><p class="careforms-muted">Select a form to begin a new entry.</p></div></div>';
        var allowed = definitions().filter(function (d) { return canUseForm(d.id); });
        if (!allowed.length) {
            mount.innerHTML += '<div class="careforms-empty-state"><h3>No forms assigned</h3><p>Your CareForms role does not currently provide access to a form.</p></div>';
            return;
        }
        var categories = {};
        allowed.forEach(function (d) { (categories[d.category] = categories[d.category] || []).push(d); });
        Object.keys(categories).sort().forEach(function (categoryName) {
            var section = document.createElement('section');
            section.className = 'careforms-form-category';
            var title = document.createElement('h3'); title.textContent = categoryName; section.appendChild(title);
            var grid = document.createElement('div'); grid.className = 'careforms-form-card-grid';
            categories[categoryName].forEach(function (definition) {
                var card = document.createElement('button'); card.type='button'; card.className='careforms-form-card';
                var icon = document.createElement('span'); icon.className='careforms-form-card-icon'; icon.textContent='▤'; icon.setAttribute('aria-hidden','true');
                var body = document.createElement('span'); body.className='careforms-form-card-body';
                var strong=document.createElement('strong'); strong.textContent=definition.name;
                var desc=document.createElement('span'); desc.textContent=definition.description;
                body.appendChild(strong); body.appendChild(desc); card.appendChild(icon); card.appendChild(body);
                card.addEventListener('click', function () { openNewForm(definition, mount); });
                grid.appendChild(card);
            });
            section.appendChild(grid); mount.appendChild(section);
        });
    }

    function saveDraft(definition, data, button, mount) {
        button.disabled=true; button.textContent='Saving…';
        var promise = activeSubmission && activeSubmission.id
            ? request('/api/submissions/' + activeSubmission.id, {method:'PUT', body:JSON.stringify({data:data})})
            : request('/api/submissions', {method:'POST', body:JSON.stringify({formId:definition.id, formVersion:definition.version, data:data})});
        promise.then(function (s) { activeSubmission=s; notify('Draft saved.'); renderSubmission(definition,s,mount); })
            .catch(function (e) { notify(e.message); button.disabled=false; button.textContent='Save Draft'; });
    }

    function submitForm(definition, data, button, mount) {
        button.disabled=true; button.textContent='Submitting…';
        var ensureDraft = activeSubmission && activeSubmission.id ? Promise.resolve(activeSubmission)
            : request('/api/submissions', {method:'POST', body:JSON.stringify({formId:definition.id, formVersion:definition.version, data:data})});
        ensureDraft.then(function (s) { activeSubmission=s; return request('/api/submissions/' + s.id + '/submit', {method:'POST', body:JSON.stringify({data:data})}); })
            .then(function (s) { activeSubmission=s; notify('Form submitted.'); renderSubmission(definition,s,mount); })
            .catch(function (e) { notify(e.message); button.disabled=false; button.textContent='Submit'; });
    }

    function openNewForm(definition, mount) {
        if (!canUseForm(definition.id)) { notify('You do not have permission to use this form.'); return; }
        activeSubmission=null;
        window.CareForms.FormRenderer.render(definition,mount,{onSaveDraft:function(data,b){saveDraft(definition,data,b,mount);},onSubmit:function(data,b){submitForm(definition,data,b,mount);}});
    }

    function renderSubmission(definition, submission, mount) {
        if (!canUseForm(definition.id)) { notify('You no longer have permission to access this form.'); showView('work'); return; }
        activeSubmission=submission;
        window.CareForms.FormRenderer.render(definition,mount,{values:submission.data||{},readOnly:submission.status==='submitted',backLabel:'Back to My Work',onSaveDraft:function(data,b){saveDraft(definition,data,b,mount);},onSubmit:function(data,b){submitForm(definition,data,b,mount);}});
    }

    function renderMyWork() {
        activeSubmission=null;
        var mount=document.getElementById('careforms-work-browser'); if(!mount) return;
        mount.innerHTML='<div class="careforms-section-heading"><div><h2>My Work</h2><p class="careforms-muted">Drafts and your recent submissions.</p></div></div><p class="careforms-muted">Loading…</p>';
        request('/api/submissions',{method:'GET'}).then(function(submissions){
            mount.innerHTML='<div class="careforms-section-heading"><div><h2>My Work</h2><p class="careforms-muted">Drafts and your recent submissions.</p></div></div>';
            if(!accessState || (!accessState.forms.length && !accessState.canViewReports)){ mount.innerHTML+='<div class="careforms-empty-state"><h3>No CareForms role assigned</h3><p>Ask an administrator to assign you to an appropriate CareForms group.</p></div>'; return; }
            if(!submissions.length){ mount.innerHTML+='<div class="careforms-empty-state"><h3>No assigned work yet</h3><p>Start a permitted form and save it as a draft. It will appear here.</p></div>'; return; }
            var list=document.createElement('div'); list.className='careforms-submission-list';
            submissions.forEach(function(s){
                var d=getDefinition(s.formId); if(!d || !canUseForm(s.formId)) return;
                var item=document.createElement('button'); item.type='button'; item.className='careforms-submission-item';
                var main=document.createElement('span'); main.className='careforms-submission-main';
                var patient=s.data && s.data.patient_name ? s.data.patient_name : 'Untitled entry';
                main.innerHTML='<strong></strong><span></span>'; main.querySelector('strong').textContent=patient;
                main.querySelector('span').textContent=d.name+' · Updated '+(s.updatedAt ? new Date(s.updatedAt*1000).toLocaleString() : '—');
                var status=document.createElement('span'); status.className='careforms-status careforms-status-'+s.status; status.textContent=s.status==='submitted'?'Submitted':'Draft';
                item.appendChild(main); item.appendChild(status); item.addEventListener('click',function(){renderSubmission(d,s,mount);}); list.appendChild(item);
            });
            mount.appendChild(list);
        }).catch(function(e){ mount.innerHTML='<div class="careforms-empty-state"><h3>Could not load My Work</h3><p></p></div>'; mount.querySelector('p').textContent=e.message; });
    }

    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.careforms-tab').forEach(function(tab){tab.addEventListener('click',function(){showView(tab.dataset.view);});});
        document.addEventListener('click',function(event){var target=event.target.closest('[data-action="back-to-forms"]'); if(!target)return; if(activeSubmission)showView('work');else renderFormsBrowser();});
        request('/api/access',{method:'GET'}).then(function(access){accessState=access;applyAccessToNavigation();renderMyWork();}).catch(function(e){notify(e.message);});
    });
}());