(function () {
    'use strict';
    function apiUrl(path){ return OC.generateUrl('/apps/careforms'+path); }
    function request(path,options){
        options=options||{}; options.credentials='same-origin';
        options.headers=Object.assign({'Accept':'application/json','Content-Type':'application/json','requesttoken':OC.requestToken},options.headers||{});
        return fetch(apiUrl(path),options).then(function(response){return response.json().catch(function(){return {};}).then(function(body){if(!response.ok){var error=new Error(body.message||'CareForms request failed.');error.details=body.errors||[];throw error;}return body;});});
    }
    function notify(message){if(OC.Notification&&OC.Notification.showTemporary)OC.Notification.showTemporary(message);else window.alert(message);}
    function fields(definition){var result=[];(definition.sections||[]).forEach(function(section){(section.fields||[]).forEach(function(field){if(['signature','signature-placeholder'].indexOf(field.type)===-1&&!field.readOnly&&!field.calculation)result.push(field);});});return result;}
    function valueText(value){if(Array.isArray(value))return value.join(', ');if(value===true)return 'Yes';if(value===false)return 'No';if(value===null||value===undefined||value==='')return '—';return String(value);}
    function parseValue(field,value){
        if(!field)return value;
        if(field.type==='number')return value===''?'':Number(value);
        if(field.type==='checkbox')return value==='true';
        if(field.type==='checkbox-group')return value.split(',').map(function(item){return item.trim();}).filter(Boolean);
        return value;
    }
    function render(submission,definition,host,options){
        options=options||{}; if(!submission||!submission.id||submission.status==='draft')return;
        var panel=document.createElement('section');panel.className='careforms-addenda-panel';
        panel.innerHTML='<div class="careforms-section-heading"><div><h3>Addenda</h3><p class="careforms-muted">Append-only corrections and supplemental notes. The original signed record above is never changed.</p></div></div><div data-addendum-list><p class="careforms-muted">Loading addenda…</p></div>';
        host.appendChild(panel);
        var list=panel.querySelector('[data-addendum-list]'),availableFields=fields(definition),byId={};availableFields.forEach(function(field){byId[field.id]=field;});
        if(options.canCreate){
            var form=document.createElement('form');form.className='careforms-addendum-form';
            form.innerHTML='<h4>Create addendum</h4><label>Reason<input type="text" data-reason required placeholder="Why is this addendum needed?"></label><label>Additional information<textarea rows="3" data-note placeholder="Supplemental clinical note"></textarea></label><div class="careforms-addendum-change"><label>Correct a field (optional)<select data-field><option value="">No field correction</option></select></label><label data-value-label hidden>Replacement value<input type="text" data-value></label></div><button type="submit" class="careforms-primary-button">Create and submit addendum</button>';
            availableFields.forEach(function(field){var option=document.createElement('option');option.value=field.id;option.textContent=field.label||field.id;form.querySelector('[data-field]').appendChild(option);});
            var fieldSelect=form.querySelector('[data-field]'),valueLabel=form.querySelector('[data-value-label]');
            fieldSelect.addEventListener('change',function(){
                var field=byId[this.value];valueLabel.hidden=!field;valueLabel.innerHTML='Replacement value';if(!field)return;
                var input;
                if(field.type==='choice-group'||field.type==='checkbox'){input=document.createElement('select');var choices=field.type==='checkbox'?[{value:'true',label:'Yes'},{value:'false',label:'No'}]:(field.options||[]).map(function(item){return{value:item,label:item};});choices.forEach(function(item){var option=document.createElement('option');option.value=item.value;option.textContent=item.label;input.appendChild(option);});}
                else{input=document.createElement('input');input.type=['number','date','time'].indexOf(field.type)!==-1?field.type:'text';if(field.type==='checkbox-group')input.placeholder='Comma-separated choices';}
                input.dataset.value='true';valueLabel.appendChild(input);
            });
            form.addEventListener('submit',function(event){
                event.preventDefault();var button=form.querySelector('button');button.disabled=true;button.textContent='Submitting…';
                var selected=fieldSelect.value,changes={},valueInput=form.querySelector('[data-value]');if(selected&&valueInput)changes[selected]=parseValue(byId[selected],valueInput.value);
                request('/api/submissions/'+submission.id+'/addenda',{method:'POST',body:JSON.stringify({reason:form.querySelector('[data-reason]').value,note:form.querySelector('[data-note]').value,changes:changes})})
                    .then(function(item){return request('/api/addenda/'+item.id+'/submit',{method:'POST'});})
                    .then(function(){notify('Addendum submitted for review.');form.reset();valueLabel.hidden=true;load();})
                    .catch(function(error){notify(error.message+(error.details&&error.details.length?' '+error.details.join(' '):''));})
                    .finally(function(){button.disabled=false;button.textContent='Create and submit addendum';});
            });panel.appendChild(form);
        }
        function card(item){
            var el=document.createElement('article');el.className='careforms-addendum-card';
            el.innerHTML='<div class="careforms-addendum-head"><strong></strong><span class="careforms-status"></span></div><p data-reason></p><p data-note></p><p data-review-note></p><dl></dl><small class="careforms-muted"></small>';
            el.querySelector('strong').textContent='Addendum '+item.sequenceNumber;
            var status=el.querySelector('.careforms-status');status.classList.add('careforms-status-'+item.status);status.textContent=item.status;
            el.querySelector('[data-reason]').textContent='Reason: '+item.reason;
            var note=item.data&&item.data.note;el.querySelector('[data-note]').textContent=note||'';el.querySelector('[data-note]').hidden=!note;
            el.querySelector('[data-review-note]').textContent=item.reviewNote?'Review note: '+item.reviewNote:'';el.querySelector('[data-review-note]').hidden=!item.reviewNote;
            Object.keys(item.data&&item.data.changes||{}).forEach(function(id){var dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=(byId[id]&&(byId[id].label||id))||id;dd.textContent=valueText(item.data.changes[id]);el.querySelector('dl').appendChild(dt);el.querySelector('dl').appendChild(dd);});
            el.querySelector('small').textContent='By '+item.authorId+' · '+new Date((item.submittedAt||item.createdAt)*1000).toLocaleString()+(item.integrityValid===false?' · Integrity failed':'');
            if(options.canReview&&item.status==='submitted'){
                var actions=document.createElement('div');actions.className='careforms-addendum-actions';
                ['return','approve'].forEach(function(action){var button=document.createElement('button');button.type='button';button.className=action==='approve'?'careforms-primary-button':'careforms-secondary-button';button.textContent=action==='approve'?'Approve addendum':'Return addendum';button.addEventListener('click',function(){var note=action==='return'?window.prompt('Why is this addendum being returned?',''):'';if(action==='return'&&!note)return;request('/api/addenda/'+item.id+'/review',{method:'POST',body:JSON.stringify({action:action,note:note})}).then(function(){notify(action==='approve'?'Addendum approved.':'Addendum returned.');load();}).catch(function(error){notify(error.message);});});actions.appendChild(button);});el.appendChild(actions);
            }
            return el;
        }
        function load(){request('/api/submissions/'+submission.id+'/addenda',{method:'GET'}).then(function(items){list.innerHTML='';if(!items.length){list.innerHTML='<p class="careforms-muted">No addenda have been recorded.</p>';return;}items.forEach(function(item){list.appendChild(card(item));});}).catch(function(error){list.innerHTML='<p class="careforms-muted"></p>';list.querySelector('p').textContent=error.message;});}
        load();
    }
    window.CareForms=window.CareForms||{};window.CareForms.Addenda={render:render};
}());
