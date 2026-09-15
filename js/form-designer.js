(function () {
    'use strict';

    function apiUrl(path) { return OC.generateUrl('/apps/careforms' + path); }
    function request(path, options) {
        options=options || {};
        return fetch(apiUrl(path), {
            method:options.method || 'GET',
            credentials:'same-origin',
            headers:{'Accept':'application/json','Content-Type':'application/json','requesttoken':OC.requestToken},
            body:options.body ? JSON.stringify(options.body) : undefined
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

    function slug(value) {
        var base=(value || 'section').toLowerCase().trim().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'') || 'section';
        var id=base, n=2;
        while (stateIds.indexOf(id) !== -1) { id=base+'-'+n++; }
        return id;
    }
    var stateIds=[];

    var FIELD_PALETTE=[
        {type:'text',label:'Short text',description:'Names, identifiers and short answers'},
        {type:'textarea',label:'Long text',description:'Notes and longer narrative answers',defaults:{rows:4}},
        {type:'number',label:'Number',description:'Numeric measurements or scores'},
        {type:'date',label:'Date',description:'Calendar date'},
        {type:'time',label:'Time',description:'Time of day'},
        {type:'checkbox',label:'Yes / No',description:'Single yes/no checkbox'},
        {type:'choice-group',label:'Select one',description:'Choose one option',defaults:{options:['Option 1','Option 2']}},
        {type:'checkbox-group',label:'Multiple choice',description:'Choose one or more options',defaults:{options:['Option 1','Option 2']}},
        {type:'signature',label:'Signature',description:'Electronic signature'}
    ];

    function allFieldIds(state) {
        var ids=[];
        state.sections.forEach(function(section){ (section.fields || []).forEach(function(field){ ids.push(field.id); }); });
        return ids;
    }
    function fieldId(state,label) {
        var base=(label || 'field').toLowerCase().trim().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').replace(/^[^a-z]+/,'') || 'field';
        var ids=allFieldIds(state), id=base, n=2;
        while(ids.indexOf(id)!==-1){ id=base+'_'+n++; }
        return id;
    }
    function chooseFieldType(callback) {
        var overlay=document.createElement('div'); overlay.className='careforms-designer-modal-overlay';
        var modal=document.createElement('div'); modal.className='careforms-designer-modal';
        modal.innerHTML='<div class="careforms-designer-modal-head"><div><h3>Add field</h3><p class="careforms-muted">Choose what kind of information this field collects.</p></div><button type="button" aria-label="Close">×</button></div>';
        modal.querySelector('button').addEventListener('click',function(){ overlay.remove(); });
        var grid=document.createElement('div'); grid.className='careforms-field-palette';
        FIELD_PALETTE.forEach(function(item){
            var b=document.createElement('button'); b.type='button'; b.className='careforms-field-type';
            b.innerHTML='<strong></strong><span></span>'; b.querySelector('strong').textContent=item.label; b.querySelector('span').textContent=item.description;
            b.addEventListener('click',function(){ overlay.remove(); callback(item); }); grid.appendChild(b);
        });
        modal.appendChild(grid); overlay.appendChild(modal); document.body.appendChild(overlay);
    }

    function renderDesign(state, body) {
        body.innerHTML='';
        stateIds=state.sections.map(function(section){ return section.id; });
        function rerender(){ renderDesign(state,body); }
        function sectionButton(label, handler, disabled) {
            var b=document.createElement('button'); b.type='button'; b.className='careforms-secondary-button'; b.textContent=label; b.disabled=!!disabled;
            b.addEventListener('click',handler); return b;
        }
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
        meta.querySelector('[data-name]').addEventListener('input',function(){ state.name=this.value; });
        meta.querySelector('[data-category]').addEventListener('input',function(){ state.category=this.value; });
        meta.querySelector('[data-description]').addEventListener('input',function(){ state.description=this.value; });
        body.appendChild(meta);

        var heading=document.createElement('div');
        heading.className='careforms-section-heading';
        heading.innerHTML='<div><h3>Form structure</h3><p class="careforms-muted">Add and arrange sections. Fields remain in their section when it moves.</p></div>';
        var add=sectionButton('+ Add section',function(){
            var label=window.prompt('Section name','New section');
            if (!label) return;
            var id=slug(label);
            state.sections.push({
                id:id,
                label:label,
                description:'',
                fields:[{
                    id:'field_'+id.replace(/-/g,'_'),
                    type:'text',
                    label:'New field'
                }]
            });
            rerender();
        });
        heading.appendChild(add);
        body.appendChild(heading);

        if (!state.sections.length) {
            var empty=document.createElement('div');
            empty.className='careforms-empty-state';
            empty.innerHTML='<h3>No sections yet</h3><p>Use “Add section” to create the first section.</p>';
            body.appendChild(empty);
            return;
        }

        state.sections.forEach(function(section, index) {
            var card=document.createElement('div');
            card.className='careforms-designer-section';
            var h=document.createElement('div');
            h.className='careforms-designer-section-title';
            h.innerHTML='<div><strong></strong><p class="careforms-muted"></p></div><span></span>';
            h.querySelector('strong').textContent=section.label || section.id || ('Section ' + (index + 1));
            h.querySelector('p').textContent=section.description || '';
            h.querySelector('span').textContent=(section.fields || []).length + ' field' + ((section.fields || []).length === 1 ? '' : 's');
            card.appendChild(h);
            var actions=document.createElement('div'); actions.className='careforms-designer-section-actions';
            actions.appendChild(sectionButton('+ Add field',function(){
                chooseFieldType(function(choice){
                    var label=window.prompt('Field label',choice.label);
                    if(!label)return;
                    var field={id:fieldId(state,label),type:choice.type,label:label};
                    if(choice.defaults){ Object.keys(choice.defaults).forEach(function(key){ field[key]=clone(choice.defaults[key]); }); }
                    section.fields.push(field); rerender();
                });
            }));
            actions.appendChild(sectionButton('Edit',function(){
                var label=window.prompt('Section name',section.label || section.id); if (!label) return;
                var description=window.prompt('Section description (optional)',section.description || '');
                section.label=label; section.description=description === null ? (section.description || '') : description; rerender();
            }));
            actions.appendChild(sectionButton('↑ Move up',function(){ if(index<1)return; var item=state.sections.splice(index,1)[0]; state.sections.splice(index-1,0,item); rerender(); },index===0));
            actions.appendChild(sectionButton('↓ Move down',function(){ if(index>=state.sections.length-1)return; var item=state.sections.splice(index,1)[0]; state.sections.splice(index+1,0,item); rerender(); },index===state.sections.length-1));
            actions.appendChild(sectionButton('Duplicate',function(){
                var copy=clone(section); copy.id=slug((section.id || 'section')+'-copy'); copy.label=(section.label || 'Section')+' copy'; state.sections.splice(index+1,0,copy); rerender();
            }));
            actions.appendChild(sectionButton('Delete',function(){
                var count=(section.fields || []).length;
                var message='Delete “'+(section.label || section.id)+'”?' + (count ? ' This will also remove '+count+' field'+(count===1?'':'s')+'.' : '');
                if(!window.confirm(message))return; state.sections.splice(index,1); rerender();
            }));
            card.appendChild(actions);
            (section.fields || []).forEach(function(field, fieldIndex) {
                var row=document.createElement('div');
                row.className='careforms-designer-field';
                var label=document.createElement('span');
                label.textContent=field.label || field.id;
                var type=document.createElement('small');
                type.textContent=field.type + (field.required ? ' · Required' : '');
                var fieldActions=document.createElement('div'); fieldActions.className='careforms-designer-field-actions';
                fieldActions.appendChild(sectionButton('↑',function(){ if(fieldIndex<1)return; var item=section.fields.splice(fieldIndex,1)[0]; section.fields.splice(fieldIndex-1,0,item); rerender(); },fieldIndex===0));
                fieldActions.appendChild(sectionButton('↓',function(){ if(fieldIndex>=section.fields.length-1)return; var item=section.fields.splice(fieldIndex,1)[0]; section.fields.splice(fieldIndex+1,0,item); rerender(); },fieldIndex===section.fields.length-1));
                fieldActions.appendChild(sectionButton('Delete',function(){
                    if(section.fields.length===1){ window.alert('A section must contain at least one field. Add another field before deleting this one.'); return; }
                    if(window.confirm('Delete “'+(field.label || field.id)+'”?')){ section.fields.splice(fieldIndex,1); rerender(); }
                }));
                var summary=document.createElement('div'); summary.className='careforms-designer-field-summary'; summary.appendChild(label); summary.appendChild(type);
                row.appendChild(summary); row.appendChild(fieldActions); card.appendChild(row);
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
                var headerActions=document.createElement('div');
                headerActions.className='careforms-designer-header-actions';
                var status=document.createElement('span');
                status.className='careforms-form-state-badge';
                status.textContent='Draft v' + version;
                var save=document.createElement('button');
                save.type='button'; save.className='primary'; save.textContent='Save draft';
                var saveState=document.createElement('span'); saveState.className='careforms-muted'; saveState.textContent='';
                save.addEventListener('click',function(){
                    save.disabled=true; saveState.textContent='Saving…';
                    request('/api/forms/admin/' + encodeURIComponent(formId) + '/versions/' + encodeURIComponent(version) + '/designer',{
                        method:'PUT', body:{definition:state}
                    }).then(function(payload){
                        state=designerState(payload.definition);
                        saveState.textContent='Saved';
                        setTimeout(function(){ saveState.textContent=''; },2500);
                    }).catch(function(error){
                        saveState.textContent='Not saved: '+error.message;
                    }).finally(function(){ save.disabled=false; });
                });
                headerActions.appendChild(status); headerActions.appendChild(save); headerActions.appendChild(saveState);
                header.appendChild(headerActions);
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
