(function () {
    'use strict';

    function apiUrl(path) { return OC.generateUrl('/apps/careforms' + path); }
    function request(path) {
        return fetch(apiUrl(path), {
            method:'GET',
            credentials:'same-origin',
            headers:{'Accept':'application/json','requesttoken':OC.requestToken}
        }).then(function (response) {
            return response.json().catch(function(){ return {}; }).then(function(body) {
                if (!response.ok) throw new Error(body.message || 'CareForms request failed.');
                return body;
            });
        });
    }
    function clone(value) { return JSON.parse(JSON.stringify(value)); }

    function designerState(definition) {
        var state = clone(definition);
        state.sections = Array.isArray(state.sections) ? state.sections : [];
        state.sections.forEach(function(section) {
            section.fields = Array.isArray(section.fields) ? section.fields : [];
        });
        return state;
    }

    function button(label, active, handler) {
        var b=document.createElement('button');
        b.type='button';
        b.className='careforms-designer-mode' + (active ? ' active' : '');
        b.textContent=label;
        b.addEventListener('click',handler);
        return b;
    }

    function renderDesign(state, body) {
        body.innerHTML='';
        var intro=document.createElement('div');
        intro.className='careforms-info-banner';
        intro.textContent='Simple structured layout: sections and fields flow automatically. No pixels or X/Y positioning are required.';
        body.appendChild(intro);

        var meta=document.createElement('div');
        meta.className='careforms-designer-meta';
        meta.innerHTML='<label>Form name<input type="text" data-name></label><label>Category<input type="text" data-category></label><label>Description<textarea rows="2" data-description></textarea></label>';
        meta.querySelector('[data-name]').value=state.name || '';
        meta.querySelector('[data-category]').value=state.category || '';
        meta.querySelector('[data-description]').value=state.description || '';
        body.appendChild(meta);

        var heading=document.createElement('div');
        heading.className='careforms-section-heading';
        heading.innerHTML='<div><h3>Form structure</h3><p class="careforms-muted">This milestone loads the shared editable model. Section and field editing comes next.</p></div>';
        body.appendChild(heading);

        if (!state.sections.length) {
            var empty=document.createElement('div');
            empty.className='careforms-empty-state';
            empty.innerHTML='<h3>No sections yet</h3><p>The next designer item will add section and field controls.</p>';
            body.appendChild(empty);
            return;
        }

        state.sections.forEach(function(section, index) {
            var card=document.createElement('div');
            card.className='careforms-designer-section';
            var h=document.createElement('div');
            h.className='careforms-designer-section-title';
            h.innerHTML='<strong></strong><span></span>';
            h.querySelector('strong').textContent=section.label || section.id || ('Section ' + (index + 1));
            h.querySelector('span').textContent=(section.fields || []).length + ' field' + ((section.fields || []).length === 1 ? '' : 's');
            card.appendChild(h);
            (section.fields || []).forEach(function(field) {
                var row=document.createElement('div');
                row.className='careforms-designer-field';
                var label=document.createElement('span');
                label.textContent=field.label || field.id;
                var type=document.createElement('small');
                type.textContent=field.type + (field.required ? ' · Required' : '');
                row.appendChild(label); row.appendChild(type); card.appendChild(row);
            });
            body.appendChild(card);
        });
    }

    function renderMarkup(state, body) {
        body.innerHTML='<div class="careforms-info-banner">CareFormML editing will be enabled in its dedicated milestone. For now this view confirms that Markup and Design share the same form model.</div>';
        var pre=document.createElement('pre');
        pre.className='careforms-designer-code';
        pre.textContent=JSON.stringify(state,null,2);
        body.appendChild(pre);
    }

    function renderPreview(state, body) {
        body.innerHTML='';
        if (!window.CareForms || !window.CareForms.FormRenderer) {
            body.innerHTML='<div class="careforms-empty-state"><h3>Preview unavailable</h3><p>The CareForms renderer could not be loaded.</p></div>';
            return;
        }
        var mount=document.createElement('div');
        mount.className='careforms-designer-preview';
        body.appendChild(mount);
        window.CareForms.FormRenderer.render(state,mount,{values:{},readOnly:false,backLabel:'Back to designer'});
    }

    function open(formId, version) {
        var mount=document.getElementById('careforms-form-admin-browser');
        if (!mount) return;
        mount.innerHTML='<p class="careforms-muted">Loading designer…</p>';
        request('/api/forms/admin/' + encodeURIComponent(formId) + '/versions/' + encodeURIComponent(version) + '/designer')
            .then(function(payload) {
                var state=designerState(payload.definition);
                var mode='design';
                mount.innerHTML='';

                var header=document.createElement('div');
                header.className='careforms-designer-header';
                var title=document.createElement('div');
                title.innerHTML='<button type="button" class="careforms-secondary-button" data-back>‹ Back to Form Admin</button><h2></h2><p class="careforms-muted"></p>';
                title.querySelector('h2').textContent=state.name || formId;
                title.querySelector('p').textContent='Draft v' + version + ' · Visual Form Designer';
                header.appendChild(title);
                var status=document.createElement('span');
                status.className='careforms-form-state-badge';
                status.textContent='Draft v' + version;
                header.appendChild(status);
                mount.appendChild(header);

                var modes=document.createElement('div');
                modes.className='careforms-designer-modes';
                var body=document.createElement('div');
                body.className='careforms-designer-body';

                function switchMode(next) {
                    mode=next;
                    Array.prototype.forEach.call(modes.children,function(child){ child.classList.toggle('active',child.dataset.mode===mode); });
                    if (mode==='design') renderDesign(state,body);
                    if (mode==='markup') renderMarkup(state,body);
                    if (mode==='preview') renderPreview(state,body);
                }
                [['design','Design'],['markup','Markup'],['preview','Preview']].forEach(function(item) {
                    var b=button(item[1],item[0]===mode,function(){ switchMode(item[0]); });
                    b.dataset.mode=item[0];
                    modes.appendChild(b);
                });
                mount.appendChild(modes);
                mount.appendChild(body);
                title.querySelector('[data-back]').addEventListener('click',function(){
                    document.dispatchEvent(new CustomEvent('careforms:form-admin-render'));
                });
                switchMode('design');
            }).catch(function(error) {
                mount.innerHTML='<div class="careforms-empty-state"><h3>Could not open form designer</h3><p></p></div>';
                mount.querySelector('p').textContent=error.message;
            });
    }

    window.CareForms=window.CareForms || {};
    window.CareForms.FormDesigner={open:open,designerState:designerState};
}());
