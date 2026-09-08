(function () {
    'use strict';

    window.CareForms = window.CareForms || {};

    function createInput(field) {
        var wrapper = document.createElement('div');
        wrapper.className = 'careforms-field';

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
        } else {
            input = document.createElement('input');
            input.type = field.type || 'text';
        }

        input.id = field.id;
        input.name = field.id;
        input.required = Boolean(field.required);

        if (field.min !== undefined) {
            input.min = field.min;
        }
        if (field.max !== undefined) {
            input.max = field.max;
        }

        wrapper.appendChild(input);
        return wrapper;
    }

    function renderForm(definition, mountNode) {
        mountNode.innerHTML = '';

        var header = document.createElement('div');
        header.className = 'careforms-form-header';

        var backButton = document.createElement('button');
        backButton.type = 'button';
        backButton.className = 'careforms-secondary-button';
        backButton.dataset.action = 'back-to-forms';
        backButton.textContent = 'Back to Forms';

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
        notice.textContent = 'Prototype renderer only. Data entered here is not saved yet.';
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
                fieldGrid.appendChild(createInput(field));
            });

            sectionElement.appendChild(fieldGrid);
            form.appendChild(sectionElement);
        });

        var actions = document.createElement('div');
        actions.className = 'careforms-form-actions';

        var clearButton = document.createElement('button');
        clearButton.type = 'reset';
        clearButton.className = 'careforms-secondary-button';
        clearButton.textContent = 'Clear';

        var draftButton = document.createElement('button');
        draftButton.type = 'button';
        draftButton.disabled = true;
        draftButton.title = 'Submission persistence will be added in the next milestone.';
        draftButton.textContent = 'Save Draft';

        var submitButton = document.createElement('button');
        submitButton.type = 'button';
        submitButton.disabled = true;
        submitButton.title = 'Submission workflow will be added in the next milestone.';
        submitButton.textContent = 'Submit';

        actions.appendChild(clearButton);
        actions.appendChild(draftButton);
        actions.appendChild(submitButton);
        form.appendChild(actions);
        mountNode.appendChild(form);
    }

    window.CareForms.FormRenderer = {
        render: renderForm
    };
}());