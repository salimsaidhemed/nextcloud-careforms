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
                if (!response.ok) { var error=new Error(body.message || 'CareForms request failed.'); error.details=body.errors || []; throw error; }
                return body;
            });
        });
    }
    function clone(value) { return JSON.parse(JSON.stringify(value)); }

    function validationLocation(error, state) {
        var m=error.match(/^sections\[(\d+)\](?:\.fields\[(\d+)\])?/);
        if(!m) return {kind:'form',label:'Form'};
        var si=Number(m[1]), fi=m[2]===undefined ? null : Number(m[2]), section=(state.sections || [])[si];
        if(fi===null) return {kind:'section',sectionIndex:si,label:section ? (section.label || section.id) : 'Section '+(si+1)};
        var field=section && (section.fields || [])[fi];
        return {kind:'field',sectionIndex:si,fieldIndex:fi,label:field ? (field.label || field.id) : 'Field '+(fi+1)};
    }
    function showValidationErrors(errors,state,body,saveState) {
        body.querySelectorAll('.careforms-validation-error').forEach(function(el){ el.classList.remove('careforms-validation-error'); });
        var old=body.querySelector('.careforms-designer-validation-summary'); if(old) old.remove();
        var summary=document.createElement('div'); summary.className='careforms-designer-validation-summary';
        summary.innerHTML='<div><strong>Draft needs attention</strong><p></p></div><ul></ul>';
        summary.querySelector('p').textContent=errors.length+' validation issue'+(errors.length===1?'':'s')+' must be fixed before this draft can be saved.';
        errors.forEach(function(error){
            var loc=validationLocation(error,state), li=document.createElement('li'), b=document.createElement('button'); b.type='button';
            b.textContent=loc.label+': '+error; li.appendChild(b); summary.querySelector('ul').appendChild(li);
            b.addEventListener('click',function(){
                var target=null;
                if(loc.kind==='section'||loc.kind==='field') target=body.querySelector('[data-section-index="'+loc.sectionIndex+'"]');
                if(loc.kind==='field'&&target) target=target.querySelector('[data-field-index="'+loc.fieldIndex+'"]') || target;
                if(target){ target.classList.add('careforms-validation-error'); target.scrollIntoView({behavior:'smooth',block:'center'}); }
                else body.scrollIntoView({behavior:'smooth',block:'start'});
            });
        });
        body.insertBefore(summary,body.firstChild); saveState.textContent='Not saved — '+errors.length+' issue'+(errors.length===1?'':'s');
    }

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

    function editField(field, rerender) {
        var overlay=document.createElement('div'); overlay.className='careforms-designer-modal-overlay';
        var modal=document.createElement('div'); modal.className='careforms-designer-modal careforms-field-editor';
        modal.innerHTML='<div class="careforms-designer-modal-head"><div><h3>Edit field</h3><p class="careforms-muted">Configure how this field appears and behaves.</p></div><button type="button" data-close aria-label="Close">×</button></div>';
        var form=document.createElement('div'); form.className='careforms-field-editor-form';
        form.innerHTML=
            '<label>Field label<input type="text" data-label></label>'+
            '<label>Help text<input type="text" data-help placeholder="Optional guidance for the person completing the form"></label>'+
            '<label class="careforms-check"><input type="checkbox" data-required> Required field</label>'+
            '<label>Width<select data-width><option value="">Automatic</option><option value="full">Full width</option><option value="half">Half width</option><option value="third">One third</option></select></label>';
        form.querySelector('[data-label]').value=field.label || '';
        form.querySelector('[data-help]').value=field.helpText || '';
        form.querySelector('[data-required]').checked=!!field.required;
        form.querySelector('[data-width]').value=field.width || '';

        if(field.type==='choice-group' || field.type==='checkbox-group'){
            var choices=document.createElement('div'); choices.className='careforms-choice-editor';
            choices.innerHTML='<div class="careforms-choice-editor-head"><div><strong>Choices</strong><p class="careforms-muted">Add, rename and reorder the options shown to users.</p></div><button type="button" class="careforms-secondary-button" data-add-option>+ Add choice</button></div><div data-option-list></div>';
            var optionList=choices.querySelector('[data-option-list]');
            var optionValues=clone(field.options || []);
            function renderOptions(){
                optionList.innerHTML='';
                optionValues.forEach(function(value,index){
                    var row=document.createElement('div'); row.className='careforms-choice-row';
                    row.innerHTML='<span class="careforms-choice-handle">☰</span><input type="text" data-option><div class="careforms-choice-actions"></div>';
                    row.querySelector('[data-option]').value=value;
                    row.querySelector('[data-option]').addEventListener('input',function(){ optionValues[index]=this.value; });
                    var a=row.querySelector('.careforms-choice-actions');
                    var up=document.createElement('button'); up.type='button'; up.textContent='↑'; up.disabled=index===0;
                    up.addEventListener('click',function(){ var v=optionValues.splice(index,1)[0]; optionValues.splice(index-1,0,v); renderOptions(); });
                    var down=document.createElement('button'); down.type='button'; down.textContent='↓'; down.disabled=index===optionValues.length-1;
                    down.addEventListener('click',function(){ var v=optionValues.splice(index,1)[0]; optionValues.splice(index+1,0,v); renderOptions(); });
                    var remove=document.createElement('button'); remove.type='button'; remove.textContent='Remove';
                    remove.addEventListener('click',function(){ if(optionValues.length===1){ window.alert('Choice fields require at least one option.'); return; } optionValues.splice(index,1); renderOptions(); });
                    a.appendChild(up); a.appendChild(down); a.appendChild(remove); optionList.appendChild(row);
                });
            }
            choices.querySelector('[data-add-option]').addEventListener('click',function(){ optionValues.push('New choice'); renderOptions(); });
            choices._optionValues=optionValues; choices._renderOptions=renderOptions; renderOptions(); form.appendChild(choices);
        }
        if(field.type==='number'){
            var numeric=document.createElement('div'); numeric.className='careforms-field-editor-row';
            numeric.innerHTML='<label>Minimum<input type="number" step="any" data-min></label><label>Maximum<input type="number" step="any" data-max></label><label>Unit<input type="text" data-unit placeholder="e.g. kg, bpm"></label>';
            if(field.min!==undefined) numeric.querySelector('[data-min]').value=field.min;
            if(field.max!==undefined) numeric.querySelector('[data-max]').value=field.max;
            numeric.querySelector('[data-unit]').value=field.unit || ''; form.appendChild(numeric);
        }
        if(field.type==='textarea'){
            var rows=document.createElement('label'); rows.innerHTML='Visible lines<input type="number" min="1" max="50" data-rows>';
            rows.querySelector('input').value=field.rows || 4; form.appendChild(rows);
        }

        var advanced=document.createElement('details'); advanced.className='careforms-field-editor-advanced';
        advanced.innerHTML='<summary>Advanced</summary><div><label>Internal field ID<input type="text" data-id readonly></label><label>Data source<select data-source><option value="">Manual entry</option><option value="patient.name">Patient name</option><option value="patient.mr_number">Patient MR number</option><option value="patient.date_of_birth">Patient date of birth</option><option value="current_user.display_name">Current user display name</option><option value="system.current_date">Current date</option></select></label><label class="careforms-check"><input type="checkbox" data-readonly> Read only</label></div>';
        advanced.querySelector('[data-id]').value=field.id || '';
        advanced.querySelector('[data-source]').value=field.source || '';
        advanced.querySelector('[data-readonly]').checked=!!field.readOnly;
        form.appendChild(advanced);

        var actions=document.createElement('div'); actions.className='careforms-field-editor-footer';
        var cancel=document.createElement('button'); cancel.type='button'; cancel.textContent='Cancel';
        var apply=document.createElement('button'); apply.type='button'; apply.className='primary'; apply.textContent='Apply changes';
        actions.appendChild(cancel); actions.appendChild(apply); form.appendChild(actions); modal.appendChild(form); overlay.appendChild(modal); document.body.appendChild(overlay);
        function close(){ overlay.remove(); }
        modal.querySelector('[data-close]').addEventListener('click',close); cancel.addEventListener('click',close);
        apply.addEventListener('click',function(){
            var label=form.querySelector('[data-label]').value.trim();
            if(!label){ window.alert('Field label is required.'); return; }
            field.label=label;
            var help=form.querySelector('[data-help]').value.trim(); if(help) field.helpText=help; else delete field.helpText;
            if(form.querySelector('[data-required]').checked) field.required=true; else delete field.required;
            var width=form.querySelector('[data-width]').value; if(width) field.width=width; else delete field.width;
            var source=advanced.querySelector('[data-source]').value; if(source) field.source=source; else delete field.source;
            if(advanced.querySelector('[data-readonly]').checked) field.readOnly=true; else delete field.readOnly;
            var choiceEditor=form.querySelector('.careforms-choice-editor');
            if(choiceEditor){
                var values=choiceEditor._optionValues.map(function(v){return v.trim();}).filter(Boolean);
                var unique=values.filter(function(v,i){return values.indexOf(v)===i;});
                if(!unique.length){ window.alert('Choice fields require at least one option.'); return; }
                if(unique.length!==values.length){ window.alert('Each choice must be unique. Please rename duplicate choices.'); return; }
                field.options=unique;
            }
            var min=form.querySelector('[data-min]'), max=form.querySelector('[data-max]'), unit=form.querySelector('[data-unit]');
            if(min){ if(min.value!=='') field.min=Number(min.value); else delete field.min; }
            if(max){ if(max.value!=='') field.max=Number(max.value); else delete field.max; }
            if(min && max && min.value!=='' && max.value!=='' && Number(min.value)>Number(max.value)){ window.alert('Minimum cannot be greater than maximum.'); return; }
            if(unit){ var u=unit.value.trim(); if(u) field.unit=u; else delete field.unit; }
            var rows=form.querySelector('[data-rows]'); if(rows) field.rows=Math.max(1,Math.min(50,parseInt(rows.value,10)||4));
            close(); rerender();
        });
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
            card.className='careforms-designer-section'; card.dataset.sectionIndex=String(index);
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
                row.className='careforms-designer-field'; row.dataset.fieldIndex=String(fieldIndex);
                var label=document.createElement('span');
                label.textContent=field.label || field.id;
                var type=document.createElement('small');
                type.textContent=field.type + (field.required ? ' · Required' : '');
                var fieldActions=document.createElement('div'); fieldActions.className='careforms-designer-field-actions';
                fieldActions.appendChild(sectionButton('Edit',function(){ editField(field,rerender); }));
                fieldActions.appendChild(sectionButton('Duplicate',function(){
                    var copy=clone(field);
                    copy.id=fieldId(state,(field.label || field.id || 'field') + ' copy');
                    copy.label=(field.label || 'Field') + ' copy';
                    section.fields.splice(fieldIndex+1,0,copy);
                    rerender();
                }));
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

    function careFormMlQuote(value) { return JSON.stringify(value === undefined || value === null ? '' : value); }
    function toCareFormML(state) {
        var lines=['form '+careFormMlQuote(state.name || state.id)];
        lines.push('  id '+careFormMlQuote(state.id));
        lines.push('  version '+Number(state.version || 1));
        lines.push('  schema '+Number(state.schemaVersion || 1));
        if(state.category) lines.push('  category '+careFormMlQuote(state.category));
        if(state.description) lines.push('  description '+careFormMlQuote(state.description));
        (state.sections || []).forEach(function(section){
            lines.push('');
            lines.push('  section '+careFormMlQuote(section.label || section.id)+' id='+careFormMlQuote(section.id));
            if(section.description) lines.push('    description '+careFormMlQuote(section.description));
            (section.fields || []).forEach(function(field){
                var attrs=['id='+careFormMlQuote(field.id),'type='+field.type];
                if(field.required) attrs.push('required');
                if(field.readOnly) attrs.push('readonly');
                if(field.width) attrs.push('width='+field.width);
                if(field.source) attrs.push('source='+careFormMlQuote(field.source));
                if(field.rows) attrs.push('rows='+field.rows);
                if(field.min!==undefined) attrs.push('min='+field.min);
                if(field.max!==undefined) attrs.push('max='+field.max);
                if(field.unit) attrs.push('unit='+careFormMlQuote(field.unit));
                lines.push('    field '+careFormMlQuote(field.label || field.id)+' '+attrs.join(' '));
                if(field.helpText) lines.push('      help '+careFormMlQuote(field.helpText));
                (field.options || []).forEach(function(option){ lines.push('      option '+careFormMlQuote(option)); });
            });
        });
        return lines.join('\n');
    }
    function careFormMlTokens(line) {
        var out=[], re=/"(?:\\.|[^"\\])*"|[^\s]+/g, match;
        while((match=re.exec(line))!==null) out.push(match[0]);
        return out;
    }
    function careFormMlValue(token) {
        if(token===undefined) return '';
        if(token.charAt(0)==='"') { try { return JSON.parse(token); } catch(e) { throw new Error('Invalid quoted text.'); } }
        return token;
    }
    function fromCareFormML(text, original) {
        var result=clone(original), currentSection=null, currentField=null;
        result.sections=[];
        var lines=text.split(/\r?\n/);
        lines.forEach(function(raw,i){
            var line=raw.trim(); if(!line || line.charAt(0)==='#') return;
            var t=careFormMlTokens(line), cmd=t.shift();
            function fail(message){ throw new Error('Line '+(i+1)+': '+message); }
            if(cmd==='form'){ if(!t.length) fail('form requires a name'); result.name=careFormMlValue(t[0]); return; }
            if(cmd==='id'){ result.id=careFormMlValue(t[0]); return; }
            if(cmd==='version'){ result.version=Number(t[0]); return; }
            if(cmd==='schema'){ result.schemaVersion=Number(t[0]); return; }
            if(cmd==='category'){ result.category=careFormMlValue(t[0]); return; }
            if(cmd==='description' && !currentSection){ result.description=careFormMlValue(t[0]); return; }
            if(cmd==='section'){
                if(!t.length) fail('section requires a label');
                var label=careFormMlValue(t.shift()), id='';
                t.forEach(function(x){ if(x.indexOf('id=')===0) id=careFormMlValue(x.slice(3)); });
                if(!id) fail('section requires id=');
                currentSection={id:id,label:label,description:'',fields:[]}; result.sections.push(currentSection); currentField=null; return;
            }
            if(cmd==='description' && currentSection && !currentField){ currentSection.description=careFormMlValue(t[0]); return; }
            if(cmd==='field'){
                if(!currentSection) fail('field must be inside a section');
                if(!t.length) fail('field requires a label');
                var flabel=careFormMlValue(t.shift()), f={label:flabel}, attrs=t;
                attrs.forEach(function(x){
                    if(x==='required') f.required=true; else if(x==='readonly') f.readOnly=true;
                    else { var p=x.indexOf('='); if(p<1) fail('invalid field attribute '+x); var k=x.slice(0,p),v=careFormMlValue(x.slice(p+1));
                        if(k==='id') f.id=v; else if(k==='type') f.type=v; else if(k==='width') f.width=v; else if(k==='source') f.source=v;
                        else if(k==='rows'||k==='min'||k==='max') f[k]=Number(v); else if(k==='unit') f.unit=v; else fail('unknown field attribute '+k);
                    }
                });
                if(!f.id || !f.type) fail('field requires id= and type=');
                currentSection.fields.push(f); currentField=f; return;
            }
            if(cmd==='help'){ if(!currentField) fail('help must follow a field'); currentField.helpText=careFormMlValue(t[0]); return; }
            if(cmd==='option'){ if(!currentField) fail('option must follow a field'); currentField.options=currentField.options || []; currentField.options.push(careFormMlValue(t[0])); return; }
            fail('unknown statement '+cmd);
        });
        if(!result.sections.length) throw new Error('CareFormML must contain at least one section.');
        return designerState(result);
    }

    function renderMarkup(state, body, applyState) {
        body.innerHTML='';
        var info=document.createElement('div'); info.className='careforms-info-banner';
        info.textContent='CareFormML is a simpler text view of the same form model. Apply markup to update Design and Preview; Save draft persists it.';
        body.appendChild(info);
        var textarea=document.createElement('textarea'); textarea.className='careforms-designer-markup'; textarea.spellcheck=false; textarea.value=toCareFormML(state);
        body.appendChild(textarea);
        var footer=document.createElement('div'); footer.className='careforms-designer-markup-footer';
        var feedback=document.createElement('span'); feedback.className='careforms-muted';
        var apply=document.createElement('button'); apply.type='button'; apply.className='primary'; apply.textContent='Apply markup';
        apply.addEventListener('click',function(){
            try { var next=fromCareFormML(textarea.value,state); applyState(next); feedback.textContent='Markup applied'; }
            catch(error){ feedback.textContent=error.message; }
        });
        footer.appendChild(feedback); footer.appendChild(apply); body.appendChild(footer);
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
                        if(error.details && error.details.length){ showValidationErrors(error.details,state,body,saveState); }
                        else saveState.textContent='Not saved: '+error.message;
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
                    if (mode==='markup') renderMarkup(state,body,function(next){ state=next; });
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
