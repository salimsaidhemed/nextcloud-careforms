(function () {
    'use strict';

    function apiUrl(path){return OC.generateUrl('/apps/careforms'+path);}
    function request(path,options){
        options=options||{};options.credentials='same-origin';
        options.headers=Object.assign({'Accept':'application/json','Content-Type':'application/json','requesttoken':OC.requestToken},options.headers||{});
        return fetch(apiUrl(path),options).then(function(response){return response.json().catch(function(){return {};}).then(function(body){if(!response.ok){var error=new Error(body.message||'CareForms request failed.');error.details=body.errors||[];throw error;}return body;});});
    }
    function notify(message){if(OC.Notification&&OC.Notification.showTemporary)OC.Notification.showTemporary(message);else window.alert(message);}
    function fields(definition){var result=[];(definition.sections||[]).forEach(function(section){(section.fields||[]).forEach(function(field){if(['signature','signature-placeholder'].indexOf(field.type)===-1&&!field.readOnly&&!field.calculation)result.push(field);});});return result;}
    function valueText(value){if(Array.isArray(value))return value.join(', ');if(value===true)return 'Yes';if(value===false)return 'No';if(value===null||value===undefined||value==='')return '—';return String(value);}
    function parseValue(field,value){if(!field)return value;if(field.type==='number')return value===''?'':Number(value);if(field.type==='checkbox')return value==='true';if(field.type==='checkbox-group')return value.split(',').map(function(item){return item.trim();}).filter(Boolean);return value;}
    function timestamp(value){return value?new Date(value*1000).toLocaleString():'—';}

    function render(submission,definition,host,options){
        options=options||{};if(!submission||!submission.id||submission.status==='draft')return;
        var panel=document.createElement('section');panel.className='careforms-addenda-panel careforms-record-history';
        panel.innerHTML='<div class="careforms-section-heading"><div><h3>Record history</h3><p class="careforms-muted">The original signed record is immutable. Requests, responses, and decisions are shown separately in chronological order.</p></div></div><div class="careforms-history-list" data-history-list><p class="careforms-muted">Loading record history…</p></div>';
        host.appendChild(panel);
        var list=panel.querySelector('[data-history-list]'),availableFields=fields(definition),byId={};availableFields.forEach(function(field){byId[field.id]=field;});
        var requestSelect=null;

        if(options.canCreate){
            var addendumForm=document.createElement('form');addendumForm.className='careforms-addendum-form';
            addendumForm.innerHTML='<h4>Create addendum</h4><p class="careforms-muted">This creates a separate response; it never edits the signed submission.</p><label>Responding to<select data-request><option value="">General addendum</option></select></label><label>Reason<input type="text" data-reason required placeholder="Why is this addendum needed?"></label><label>Additional information<textarea rows="3" data-note placeholder="Supplemental clinical note"></textarea></label><div class="careforms-addendum-change"><label>Correct a field (optional)<select data-field><option value="">No field correction</option></select></label><label data-value-label hidden>Replacement value<input type="text" data-value></label></div><button type="submit" class="careforms-primary-button">Create and submit addendum</button>';
            requestSelect=addendumForm.querySelector('[data-request]');
            availableFields.forEach(function(field){var option=document.createElement('option');option.value=field.id;option.textContent=field.label||field.id;addendumForm.querySelector('[data-field]').appendChild(option);});
            var fieldSelect=addendumForm.querySelector('[data-field]'),valueLabel=addendumForm.querySelector('[data-value-label]');
            fieldSelect.addEventListener('change',function(){
                var field=byId[this.value];valueLabel.hidden=!field;valueLabel.innerHTML='Replacement value';if(!field)return;
                var input;if(field.type==='choice-group'||field.type==='checkbox'){input=document.createElement('select');var choices=field.type==='checkbox'?[{value:'true',label:'Yes'},{value:'false',label:'No'}]:(field.options||[]).map(function(item){return{value:item,label:item};});choices.forEach(function(item){var option=document.createElement('option');option.value=item.value;option.textContent=item.label;input.appendChild(option);});}
                else{input=document.createElement('input');input.type=['number','date','time'].indexOf(field.type)!==-1?field.type:'text';if(field.type==='checkbox-group')input.placeholder='Comma-separated choices';}
                input.dataset.value='true';valueLabel.appendChild(input);
            });
            addendumForm.addEventListener('submit',function(event){
                event.preventDefault();var button=addendumForm.querySelector('button');button.disabled=true;button.textContent='Submitting…';
                var selected=fieldSelect.value,changes={},valueInput=addendumForm.querySelector('[data-value]');if(selected&&valueInput)changes[selected]=parseValue(byId[selected],valueInput.value);
                var requestId=requestSelect.value?Number(requestSelect.value):null;
                request('/api/submissions/'+submission.id+'/addenda',{method:'POST',body:JSON.stringify({reason:addendumForm.querySelector('[data-reason]').value,note:addendumForm.querySelector('[data-note]').value,changes:changes,changeRequestId:requestId})})
                    .then(function(item){return request('/api/addenda/'+item.id+'/submit',{method:'POST'});})
                    .then(function(){notify('Addendum submitted for review.');addendumForm.reset();valueLabel.hidden=true;load();})
                    .catch(function(error){notify(error.message+(error.details&&error.details.length?' '+error.details.join(' '):''));})
                    .finally(function(){button.disabled=false;button.textContent='Create and submit addendum';});
            });panel.appendChild(addendumForm);
        }

        if(options.canReview){
            var requestForm=document.createElement('form');requestForm.className='careforms-change-request-form';
            requestForm.innerHTML='<h4>Request clarification or correction</h4><label>Instructions<textarea rows="3" required data-instructions placeholder="Describe exactly what must be clarified or corrected."></textarea></label><button type="submit" class="careforms-secondary-button">Create change request</button>';
            requestForm.addEventListener('submit',function(event){event.preventDefault();var button=requestForm.querySelector('button');button.disabled=true;request('/api/submissions/'+submission.id+'/change-requests',{method:'POST',body:JSON.stringify({instructions:requestForm.querySelector('[data-instructions]').value})}).then(function(){notify('Change request created.');requestForm.reset();load();}).catch(function(error){notify(error.message);}).finally(function(){button.disabled=false;});});
            panel.appendChild(requestForm);
        }

        function eventCard(event){
            var card=document.createElement('article');card.className='careforms-history-event careforms-history-'+event.type;
            var head=document.createElement('div');head.className='careforms-addendum-head';var title=document.createElement('strong');title.textContent=event.title;var status=document.createElement('span');status.className='careforms-status careforms-status-'+event.status;status.textContent=event.status;head.appendChild(title);head.appendChild(status);card.appendChild(head);
            var meta=document.createElement('small');meta.className='careforms-muted';meta.textContent='By '+(event.actorId||'Unknown')+' · '+timestamp(event.timestamp);card.appendChild(meta);
            if(event.type==='original'){var original=document.createElement('p');original.textContent='Original signed payload — unchanged';card.appendChild(original);}
            if(event.request){var instructions=document.createElement('p');instructions.textContent=event.request.instructions;card.appendChild(instructions);}
            if(event.addendum){
                var reason=document.createElement('p');reason.textContent='Reason: '+event.addendum.reason;card.appendChild(reason);
                if(event.addendum.changeRequestId){var response=document.createElement('p');response.className='careforms-history-link';response.textContent='Response to change request #'+(event.changeRequestSequence||event.addendum.changeRequestId);card.appendChild(response);}
                if(event.addendum.data&&event.addendum.data.note){var note=document.createElement('p');note.textContent=event.addendum.data.note;card.appendChild(note);}
                (event.comparisons||[]).forEach(function(change){var row=document.createElement('div');row.className='careforms-history-change is-'+change.changeType;row.innerHTML='<div><strong></strong><span class="careforms-change-kind"></span></div><div class="careforms-change-values"><span class="careforms-before"></span><span aria-hidden="true">→</span><span class="careforms-after"></span></div>';row.querySelector('strong').textContent=change.label;row.querySelector('.careforms-change-kind').textContent=change.changeType;row.querySelector('.careforms-before').textContent='Before: '+valueText(change.before);row.querySelector('.careforms-after').textContent='After: '+valueText(change.after);card.appendChild(row);});
                if(options.canReview&&event.addendum.status==='submitted'){
                    if(options.currentUserId&&event.addendum.authorId===options.currentUserId){var blocked=document.createElement('p');blocked.className='careforms-info-banner careforms-self-review-notice';blocked.textContent='You submitted this addendum. A different reviewer must approve or return it.';card.appendChild(blocked);}
                    else{var actions=document.createElement('div');actions.className='careforms-addendum-actions';['return','approve'].forEach(function(action){var button=document.createElement('button');button.type='button';button.className=action==='approve'?'careforms-primary-button':'careforms-secondary-button';button.textContent=action==='approve'?'Approve addendum':'Return addendum';button.addEventListener('click',function(){var note=action==='return'?window.prompt('Why is this addendum being returned?',''):'';if(action==='return'&&!note)return;request('/api/addenda/'+event.addendum.id+'/review',{method:'POST',body:JSON.stringify({action:action,note:note})}).then(function(){notify(action==='approve'?'Addendum approved.':'Addendum returned.');load();}).catch(function(error){notify(error.message);});});actions.appendChild(button);});card.appendChild(actions);}
                }
            }
            if(event.note){var decision=document.createElement('p');decision.textContent='Review note: '+event.note;card.appendChild(decision);}
            return card;
        }

        function load(){request('/api/submissions/'+submission.id+'/history',{method:'GET'}).then(function(history){list.innerHTML='';(history.events||[]).forEach(function(event){list.appendChild(eventCard(event));});if(requestSelect){requestSelect.innerHTML='<option value="">General addendum</option>';(history.openRequests||[]).forEach(function(item){var option=document.createElement('option');option.value=item.id;option.textContent='Change request #'+item.sequenceNumber+' — '+item.instructions;requestSelect.appendChild(option);});}}).catch(function(error){list.innerHTML='<p class="careforms-muted"></p>';list.querySelector('p').textContent=error.message;});}
        load();
    }

    window.CareForms=window.CareForms||{};window.CareForms.Addenda={render:render};
}());
