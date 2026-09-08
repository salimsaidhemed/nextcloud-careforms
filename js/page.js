(function () {
    'use strict';

    window.CareForms = window.CareForms || {};

    function showView(viewName) {
        document.querySelectorAll('.careforms-tab').forEach(function (tab) {
            tab.classList.toggle('active', tab.dataset.view === viewName);
        });

        document.querySelectorAll('[data-view-panel]').forEach(function (panel) {
            var active = panel.dataset.viewPanel === viewName;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });

        if (viewName === 'forms') {
            renderFormsBrowser();
        }
    }

    function renderFormsBrowser() {
        var mountNode = document.getElementById('careforms-forms-browser');
        if (!mountNode) {
            return;
        }

        mountNode.innerHTML = '';

        var heading = document.createElement('div');
        heading.className = 'careforms-section-heading';
        heading.innerHTML = '<div><h2>Forms</h2><p class="careforms-muted">Select a form to begin a new entry.</p></div>';
        mountNode.appendChild(heading);

        var definition = window.CareForms.formDefinitions.homeHealthAide;

        var category = document.createElement('section');
        category.className = 'careforms-form-category';

        var categoryTitle = document.createElement('h3');
        categoryTitle.textContent = definition.category;
        category.appendChild(categoryTitle);

        var grid = document.createElement('div');
        grid.className = 'careforms-form-card-grid';

        var card = document.createElement('button');
        card.type = 'button';
        card.className = 'careforms-form-card';
        card.dataset.formId = definition.id;

        var icon = document.createElement('span');
        icon.className = 'careforms-form-card-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '▤';

        var cardBody = document.createElement('span');
        cardBody.className = 'careforms-form-card-body';

        var cardTitle = document.createElement('strong');
        cardTitle.textContent = definition.name;

        var cardDescription = document.createElement('span');
        cardDescription.textContent = definition.description;

        cardBody.appendChild(cardTitle);
        cardBody.appendChild(cardDescription);
        card.appendChild(icon);
        card.appendChild(cardBody);
        grid.appendChild(card);
        category.appendChild(grid);
        mountNode.appendChild(category);

        card.addEventListener('click', function () {
            window.CareForms.FormRenderer.render(definition, mountNode);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.careforms-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                showView(tab.dataset.view);
            });
        });

        document.addEventListener('click', function (event) {
            var target = event.target.closest('[data-action="back-to-forms"]');
            if (target) {
                renderFormsBrowser();
            }
        });
    });
}());