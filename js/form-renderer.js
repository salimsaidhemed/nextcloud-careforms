(function () {
    'use strict';

    window.CareForms = window.CareForms || {};

    function valueForField(field, values) {
        if (!values || values[field.id] === undefined || values[field.id] === null) {
            return field.type === 'checkbox-group' ? [] : '';
        }
        return values[field.id];
    }

    function createInput(field, values, readOnly) {
        var wrapper = document.createElement('div');
        wrapper.className = 'careforms-field';
        var savedValue = valueForField(field, values);

        if (field.type === 'checkbox-group') {
            var groupLabel = document.createElement('div');
            groupLabel.className = 'careforms-field-label';
            groupLabel.textContent = field.label;
            wrapper.appendChild(groupLabel);

            var grid = document.createElement('div');
            grid.className = 'careforms-checkbox-grid';

            field.options.forEach(function (option, index) {
                var optionLabel = document.createElement('label');
                optionLabel.className = 'careforms-checkbox-option';

                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = field.id + '[]';
                checkbox.value = option;
                checkbox.id = field.id + '-' + index;
                checkbox.checked = Array.isArray(savedValue) && savedValue.indexOf(option) !== -1;
                checkbox.disabled = readOnly;

                var text = document.createElement('span');
                text.textContent = option;

                optionLabel.appendChild(checkbox);
                optionLabel.appendChild(text);
                grid.appendChild(optionLabel);
            });

            wrapper.appendChild(grid);
            return wrapper;
        }

        if (field.type === 'signature-placeholder') {
            var signatureLabel = document.createElement('div');
            signatureLabel.className = 'careforms-field-label';
            signatureLabel.textContent = field.label;

            var placeholder = document.createElement('div');
            placeholder.className = 'careforms-signature-placeholder';
            placeholder.textContent = 'Electronic signature capture will be added in a later milestone.';

            wrapper.appendChild(signatureLabel);
            wrapper.appendChild(placeholder);
            return wrapper;
        }

        if (field.type === 'checkbox') {
            var checkboxLabel = document.createElement('label');
            checkboxLabel.className = 'careforms-checkbox-option careforms-single-checkbox';

            var singleCheckbox = document.createElement('input');
            singleCheckbox.type = 'checkbox';
            singleCheckbox.id = field.id;
            singleCheckbox.name = field.id;
            singleCheckbox.checked = savedValue === true;
            singleCheckbox.disabled = readOnly;

            var checkboxText = document.createElement('span');
            checkboxText.textContent = field.label;

            checkboxLabel.appendChild(singleCheckbox);
            checkboxLabel.appendChild(checkboxText);
            wrapper.appendChild(checkboxLabel);
            return wrapper;
        }

        var label = document.createElement('label');
        label.className = 'careforms-field-label';
        label.htmlFor = field.id;
        label.textContent = field.label + (field.required ? ' *' : '');
        wrapper.appendChild(label);

        var input;
        if (field.type === 'textarea') {
            input = document.createElement('textarea');
            input.rows = field.rows || 4;
            input.value = savedValue;
        } else {
            input = document.createElement('input');
            input.type = field.type || 'text';
            input.value = savedValue;
        }

        input.id = field.id;
        input.name = field.id;
        input.required = Boolean(field.required);
        input.disabled = readOnly;

        if (field.min !== undefined) {
            input.min = field.min;
        }
        if (field.max !== undefined) {
            input.max = field.max;
        }

        wrapper.appendChild(input);
        return wrapper;
    }

    function collectData(form, definition) {
        var data = {};

        definition.fields.forEach(function (section) {
            section.fields.forEach(function (field) {
                if (field.type === 'signature-placeholder') {
                    return;
                }

                if (field.type === 'checkbox-group') {
                    data[field.id] = Array.from(form.querySelectorAll('input[name="' + field.id + '[]"]:checked'))
                        .map(function (input) { return input.value; });
                    return;
                }

                var input = form.elements[field.id];
                if (!input) {
                    return;
                }

                if (field.type === 'checkbox') {
                    data[field.id] = input.checked;
                } else {
                    data[field.id] = input.value;
                }
            });
        });

        return data;
    }

    function renderForm(definition, mountNode, options) {
        options = options || {};
        var values = options.values || {};
        var readOnly = Boolean(options.readOnly);

        mountNode.innerHTML = '';

        var header = document.createElement('div');
        header.className = 'careforms-form-header';

        var backButton = document.createElement('button');
        backButton.type = 'button';
        backButton.className = 'careforms-secondary-button';
        backButton.dataset.action = 'back-to-forms';
        backButton.textContent = options.backLabel || 'Back to Forms';

        var titleBlock = document.createElement('div');
        var title = document.createElement('h2');
        title.textContent = definition.name;
        var meta = document.createElement('p');
        meta.className = 'careforms-muted';
        meta.textContent = definition.category + ' · Version ' + definition.version;
        var description = document.createElement('p');
        description.textContent = definition.description;

        titleBlock.appendChild(title);
        titleBlock.appendChild(meta);
        titleBlock.appendChild(description);
        header.appendChild(backButton);
        header.appendChild(titleBlock);
        mountNode.appendChild(header);

        var notice = document.createElement('div');
        notice.className = 'careforms-info-banner';
        notice.textContent = readOnly
            ? 'Submitted record. This form is read-only.'
            : 'Drafts are saved securely to CareForms. Submit when the entry is complete.';
        mountNode.appendChild(notice);

        var form = document.createElement('form');
        form.className = 'careforms-rendered-form';
        form.dataset.formId = definition.id;

        definition.fields.forEach(function (section) {
            var sectionElement = document.createElement('section');
            sectionElement.className = 'careforms-form-section';
            sectionElement.id = section.id;

            var heading = document.createElement('h3');
            heading.textContent = section.label;
            sectionElement.appendChild(heading);

            var fieldGrid = document.createElement('div');
            fieldGrid.className = 'careforms-field-grid';

            section.fields.forEach(function (field) {
                fieldGrid.appendChild(createInput(field, values, readOnly));
            });

            sectionElement.appendChild(fieldGrid);
            form.appendChild(sectionElement);
        });

        if (!readOnly) {
            var actions = document.createElement('div');
            actions.className = 'careforms-form-actions';

            var clearButton = document.createElement('button');
            clearButton.type = 'reset';
            clearButton.className = 'careforms-secondary-button';
            clearButton.textContent = 'Clear';

            var draftButton = document.createElement('button');
            draftButton.type = 'button';
            draftButton.className = 'careforms-secondary-button';
            draftButton.textContent = 'Save Draft';

            var submitButton = document.createElement('button');
            submitButton.type = 'button';
            submitButton.textContent = 'Submit';

            draftButton.addEventListener('click', function () {
                if (options.onSaveDraft) {
                    options.onSaveDraft(collectData(form, definition), draftButton);
                }
            });

            submitButton.addEventListener('click', function () {
                if (!form.reportValidity()) {
                    return;
                }
                if (options.onSubmit) {
                    options.onSubmit(collectData(form, definition), submitButton);
                }
            });

            actions.appendChild(clearButton);
            actions.appendChild(draftButton);
            actions.appendChild(submitButton);
            form.appendChild(actions);
        }

        mountNode.appendChild(form);
    }

    window.CareForms.FormRenderer = {
        render: renderForm,
        collectData: collectData
    };
}());