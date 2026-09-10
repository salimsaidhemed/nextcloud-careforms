(function () {
    'use strict';

    window.CareForms = window.CareForms || {};

    function valueForField(field, values) {
        if (!values || values[field.id] === undefined || values[field.id] === null) {
            return field.type === 'checkbox-group' ? [] : '';
        }
        return values[field.id];
    }

    function createSignatureField(field, readOnly, signature) {
        var wrapper = document.createElement('div');
        wrapper.className = 'careforms-field careforms-signature-field';
        var label = document.createElement('div');
        label.className = 'careforms-field-label';
        label.textContent = field.label + ' *';
        wrapper.appendChild(label);

        if (readOnly) {
            if (signature && signature.signatureData) {
                var img = document.createElement('img');
                img.src = signature.signatureData;
                img.alt = 'Electronic signature';
                img.style.maxWidth = '420px';
                img.style.width = '100%';
                img.style.height = 'auto';
                img.style.border = '1px solid var(--color-border)';
                img.style.borderRadius = '8px';
                img.style.background = 'white';
                wrapper.appendChild(img);
                var meta = document.createElement('p');
                meta.className = 'careforms-muted';
                meta.textContent = 'Electronically signed by ' + (signature.signerName || signature.signedBy || 'unknown user') +
                    (signature.signedAt ? ' · ' + new Date(signature.signedAt * 1000).toLocaleString() : '');
                wrapper.appendChild(meta);
            } else {
                var legacy = document.createElement('p');
                legacy.className = 'careforms-muted';
                legacy.textContent = 'No electronic signature recorded for this submission.';
                wrapper.appendChild(legacy);
            }
            return wrapper;
        }

        var hint = document.createElement('p');
        hint.className = 'careforms-muted';
        hint.textContent = 'Sign with a mouse, finger, or stylus. Your authenticated Nextcloud identity will be recorded when you submit.';
        wrapper.appendChild(hint);

        var canvas = document.createElement('canvas');
        canvas.width = 700;
        canvas.height = 180;
        canvas.dataset.signatureCanvas = 'true';
        canvas.dataset.signed = 'false';
        canvas.style.width = '100%';
        canvas.style.maxWidth = '700px';
        canvas.style.height = '180px';
        canvas.style.border = '1px solid var(--color-border)';
        canvas.style.borderRadius = '8px';
        canvas.style.background = 'white';
        canvas.style.touchAction = 'none';
        wrapper.appendChild(canvas);

        var clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'careforms-secondary-button';
        clear.textContent = 'Clear signature';
        clear.style.marginTop = '8px';
        wrapper.appendChild(clear);

        var ctx = canvas.getContext('2d');
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111';
        var drawing = false;

        function position(event) {
            var rect = canvas.getBoundingClientRect();
            return {
                x: (event.clientX - rect.left) * (canvas.width / rect.width),
                y: (event.clientY - rect.top) * (canvas.height / rect.height)
            };
        }
        canvas.addEventListener('pointerdown', function (event) {
            drawing = true;
            canvas.setPointerCapture(event.pointerId);
            var p = position(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        });
        canvas.addEventListener('pointermove', function (event) {
            if (!drawing) return;
            var p = position(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            canvas.dataset.signed = 'true';
        });
        function stop(event) {
            drawing = false;
            if (event && canvas.hasPointerCapture && canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
        }
        canvas.addEventListener('pointerup', stop);
        canvas.addEventListener('pointercancel', stop);
        clear.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.dataset.signed = 'false';
        });
        return wrapper;
    }

    function createInput(field, values, readOnly, patientLinked, signature) {
        if (field.type === 'signature-placeholder') return createSignatureField(field, readOnly, signature);

        var wrapper = document.createElement('div');
        wrapper.className = 'careforms-field';
        var savedValue = valueForField(field, values);
        var patientIdentityField = patientLinked && ['patient_name', 'mr_number', 'medical_record_number', 'mrn'].indexOf(field.id) !== -1;

        if (field.type === 'checkbox-group' || field.type === 'choice-group') {
            var groupLabel = document.createElement('div');
            groupLabel.className = 'careforms-field-label';
            groupLabel.textContent = field.label + (field.required ? ' *' : '');
            wrapper.appendChild(groupLabel);
            var grid = document.createElement('div');
            grid.className = 'careforms-checkbox-grid';
            field.options.forEach(function (option, index) {
                var optionLabel = document.createElement('label');
                optionLabel.className = 'careforms-checkbox-option';
                var input = document.createElement('input');
                input.type = field.type === 'choice-group' ? 'radio' : 'checkbox';
                input.name = field.type === 'choice-group' ? field.id : field.id + '[]';
                input.value = option;
                input.id = field.id + '-' + index;
                input.checked = field.type === 'choice-group' ? savedValue === option : Array.isArray(savedValue) && savedValue.indexOf(option) !== -1;
                input.disabled = readOnly;
                if (field.required && index === 0) input.required = true;
                var text = document.createElement('span'); text.textContent = option;
                optionLabel.appendChild(input); optionLabel.appendChild(text); grid.appendChild(optionLabel);
            });
            wrapper.appendChild(grid);
            return wrapper;
        }

        if (field.type === 'checkbox') {
            var checkboxLabel = document.createElement('label');
            checkboxLabel.className = 'careforms-checkbox-option careforms-single-checkbox';
            var singleCheckbox = document.createElement('input');
            singleCheckbox.type = 'checkbox'; singleCheckbox.id = field.id; singleCheckbox.name = field.id;
            singleCheckbox.checked = savedValue === true; singleCheckbox.disabled = readOnly;
            var checkboxText = document.createElement('span'); checkboxText.textContent = field.label;
            checkboxLabel.appendChild(singleCheckbox); checkboxLabel.appendChild(checkboxText); wrapper.appendChild(checkboxLabel); return wrapper;
        }

        var label = document.createElement('label');
        label.className = 'careforms-field-label'; label.htmlFor = field.id;
        label.textContent = field.label + (field.required ? ' *' : ''); wrapper.appendChild(label);
        var input;
        if (field.type === 'textarea') { input = document.createElement('textarea'); input.rows = field.rows || 4; input.value = savedValue; }
        else { input = document.createElement('input'); input.type = field.type || 'text'; input.value = savedValue; }
        input.id = field.id; input.name = field.id; input.required = Boolean(field.required);
        input.disabled = readOnly || patientIdentityField;
        if (patientIdentityField) input.dataset.patientIdentity = 'true';
        if (field.min !== undefined) input.min = field.min;
        if (field.max !== undefined) input.max = field.max;
        wrapper.appendChild(input); return wrapper;
    }

    function collectData(form, definition) {
        var data = {};
        definition.fields.forEach(function (section) {
            section.fields.forEach(function (field) {
                if (field.type === 'signature-placeholder') return;
                if (field.type === 'checkbox-group') {
                    data[field.id] = Array.from(form.querySelectorAll('input[name="' + field.id + '[]"]:checked')).map(function (input) { return input.value; }); return;
                }
                if (field.type === 'choice-group') {
                    var selected = form.querySelector('input[name="' + field.id + '"]:checked'); data[field.id] = selected ? selected.value : ''; return;
                }
                var input = form.elements[field.id]; if (!input) return;
                if (field.type === 'checkbox') data[field.id] = input.checked;
                else data[field.id] = input.value;
            });
        });
        return data;
    }

    function collectSignature(form) {
        var canvas = form.querySelector('canvas[data-signature-canvas="true"]');
        if (!canvas || canvas.dataset.signed !== 'true') return '';
        return canvas.toDataURL('image/png');
    }

    function renderForm(definition, mountNode, options) {
        options = options || {};
        var values = options.values || {};
        var readOnly = Boolean(options.readOnly);
        var patient = options.patient || null;
        var signature = options.signature || null;
        mountNode.innerHTML = '';

        var header = document.createElement('div'); header.className = 'careforms-form-header';
        var backButton = document.createElement('button'); backButton.type = 'button'; backButton.className = 'careforms-secondary-button'; backButton.dataset.action = 'back-to-forms'; backButton.textContent = options.backLabel || 'Back to Forms';
        var titleBlock = document.createElement('div');
        var title = document.createElement('h2'); title.textContent = definition.name;
        var meta = document.createElement('p'); meta.className = 'careforms-muted'; meta.textContent = definition.category + ' · Version ' + definition.version;
        var description = document.createElement('p'); description.textContent = definition.description;
        titleBlock.appendChild(title); titleBlock.appendChild(meta); titleBlock.appendChild(description); header.appendChild(backButton); header.appendChild(titleBlock); mountNode.appendChild(header);

        if (patient) {
            var patientBanner = document.createElement('div'); patientBanner.className = 'careforms-info-banner';
            patientBanner.textContent = 'Patient: ' + patient.displayName + ' · MR# ' + patient.medicalRecordNumber;
            mountNode.appendChild(patientBanner);
        }

        var notice = document.createElement('div'); notice.className = 'careforms-info-banner';
        notice.textContent = readOnly ? 'Submitted record. This form is read-only.' : 'Drafts are saved securely to CareForms. Sign and submit when the entry is complete.';
        mountNode.appendChild(notice);

        var layout = document.createElement('div'); layout.className = 'careforms-form-layout';
        if (definition.fields.length > 8) {
            var sectionNav = document.createElement('nav'); sectionNav.className = 'careforms-section-nav'; sectionNav.setAttribute('aria-label', 'Form sections');
            var navTitle = document.createElement('strong'); navTitle.textContent = 'Sections'; sectionNav.appendChild(navTitle);
            definition.fields.forEach(function (section) { var link = document.createElement('a'); link.href = '#' + section.id; link.textContent = section.label; sectionNav.appendChild(link); });
            layout.appendChild(sectionNav);
        }

        var form = document.createElement('form'); form.className = 'careforms-rendered-form'; form.dataset.formId = definition.id;
        definition.fields.forEach(function (section) {
            var sectionElement = document.createElement('section'); sectionElement.className = 'careforms-form-section'; sectionElement.id = section.id;
            var heading = document.createElement('h3'); heading.textContent = section.label; sectionElement.appendChild(heading);
            var fieldGrid = document.createElement('div'); fieldGrid.className = 'careforms-field-grid';
            section.fields.forEach(function (field) { fieldGrid.appendChild(createInput(field, values, readOnly, Boolean(patient), signature)); });
            sectionElement.appendChild(fieldGrid); form.appendChild(sectionElement);
        });

        if (!readOnly) {
            var actions = document.createElement('div'); actions.className = 'careforms-form-actions';
            var clearButton = document.createElement('button'); clearButton.type = 'reset'; clearButton.className = 'careforms-secondary-button'; clearButton.textContent = 'Clear';
            var draftButton = document.createElement('button'); draftButton.type = 'button'; draftButton.className = 'careforms-secondary-button'; draftButton.textContent = 'Save Draft';
            var submitButton = document.createElement('button'); submitButton.type = 'button'; submitButton.textContent = 'Sign & Submit';
            draftButton.addEventListener('click', function () { if (options.onSaveDraft) options.onSaveDraft(collectData(form, definition), draftButton); });
            submitButton.addEventListener('click', function () {
                if (!form.reportValidity()) return;
                var signatureData = collectSignature(form);
                if (!signatureData) {
                    if (OC.Notification && OC.Notification.showTemporary) OC.Notification.showTemporary('Draw your signature before submitting.');
                    else window.alert('Draw your signature before submitting.');
                    return;
                }
                if (options.onSubmit) options.onSubmit(collectData(form, definition), submitButton, signatureData);
            });
            actions.appendChild(clearButton); actions.appendChild(draftButton); actions.appendChild(submitButton); form.appendChild(actions);
        }
        layout.appendChild(form); mountNode.appendChild(layout);
    }

    window.CareForms.FormRenderer = { render: renderForm, collectData: collectData, collectSignature: collectSignature };
}());