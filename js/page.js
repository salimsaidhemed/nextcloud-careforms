(function () {
    'use strict';

    window.CareForms = window.CareForms || {};

    var activeSubmission = null;

    function apiUrl(path) {
        return OC.generateUrl('/apps/careforms' + path);
    }

    function notify(message) {
        if (OC.Notification && OC.Notification.showTemporary) {
            OC.Notification.showTemporary(message);
        } else {
            window.alert(message);
        }
    }

    function request(path, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = Object.assign({
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'requesttoken': OC.requestToken
        }, options.headers || {});

        return fetch(apiUrl(path), options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) {
                    throw new Error(body.message || 'CareForms request failed.');
                }
                return body;
            });
        });
    }

    function getDefinition(formId) {
        var definitions = window.CareForms.formDefinitions || {};
        return Object.keys(definitions)
            .map(function (key) { return definitions[key]; })
            .find(function (definition) { return definition.id === formId; });
    }

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
        } else if (viewName === 'work') {
            renderMyWork();
        }
    }

    function renderFormsBrowser() {
        activeSubmission = null;
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
            openNewForm(definition, mountNode);
        });
    }

    function saveDraft(definition, data, button, mountNode) {
        button.disabled = true;
        button.textContent = 'Saving…';

        var promise;
        if (activeSubmission && activeSubmission.id) {
            promise = request('/api/submissions/' + activeSubmission.id, {
                method: 'PUT',
                body: JSON.stringify({ data: data })
            });
        } else {
            promise = request('/api/submissions', {
                method: 'POST',
                body: JSON.stringify({
                    formId: definition.id,
                    formVersion: definition.version,
                    data: data
                })
            });
        }

        promise.then(function (submission) {
            activeSubmission = submission;
            notify('Draft saved.');
            renderSubmission(definition, submission, mountNode);
        }).catch(function (error) {
            notify(error.message);
            button.disabled = false;
            button.textContent = 'Save Draft';
        });
    }

    function submitForm(definition, data, button, mountNode) {
        button.disabled = true;
        button.textContent = 'Submitting…';

        var ensureDraft = activeSubmission && activeSubmission.id
            ? Promise.resolve(activeSubmission)
            : request('/api/submissions', {
                method: 'POST',
                body: JSON.stringify({
                    formId: definition.id,
                    formVersion: definition.version,
                    data: data
                })
            });

        ensureDraft.then(function (submission) {
            activeSubmission = submission;
            return request('/api/submissions/' + submission.id + '/submit', {
                method: 'POST',
                body: JSON.stringify({ data: data })
            });
        }).then(function (submission) {
            activeSubmission = submission;
            notify('Form submitted.');
            renderSubmission(definition, submission, mountNode);
        }).catch(function (error) {
            notify(error.message);
            button.disabled = false;
            button.textContent = 'Submit';
        });
    }

    function openNewForm(definition, mountNode) {
        activeSubmission = null;
        window.CareForms.FormRenderer.render(definition, mountNode, {
            onSaveDraft: function (data, button) {
                saveDraft(definition, data, button, mountNode);
            },
            onSubmit: function (data, button) {
                submitForm(definition, data, button, mountNode);
            }
        });
    }

    function renderSubmission(definition, submission, mountNode) {
        activeSubmission = submission;
        window.CareForms.FormRenderer.render(definition, mountNode, {
            values: submission.data || {},
            readOnly: submission.status === 'submitted',
            backLabel: 'Back to My Work',
            onSaveDraft: function (data, button) {
                saveDraft(definition, data, button, mountNode);
            },
            onSubmit: function (data, button) {
                submitForm(definition, data, button, mountNode);
            }
        });
    }

    function displayDate(timestamp) {
        if (!timestamp) {
            return '—';
        }
        return new Date(timestamp * 1000).toLocaleString();
    }

    function submissionLabel(submission) {
        var patient = submission.data && submission.data.patient_name;
        return patient ? patient : 'Untitled entry';
    }

    function renderMyWork() {
        activeSubmission = null;
        var mountNode = document.getElementById('careforms-work-browser');
        if (!mountNode) {
            return;
        }

        mountNode.innerHTML = '<div class="careforms-section-heading"><div><h2>My Work</h2><p class="careforms-muted">Drafts and your recent submissions.</p></div></div><p class="careforms-muted">Loading…</p>';

        request('/api/submissions', { method: 'GET' }).then(function (submissions) {
            mountNode.innerHTML = '';

            var heading = document.createElement('div');
            heading.className = 'careforms-section-heading';
            heading.innerHTML = '<div><h2>My Work</h2><p class="careforms-muted">Drafts and your recent submissions.</p></div>';
            mountNode.appendChild(heading);

            if (!submissions.length) {
                var empty = document.createElement('div');
                empty.className = 'careforms-empty-state';
                empty.innerHTML = '<h3>No assigned work yet</h3><p>Start a form and save it as a draft. It will appear here.</p>';
                mountNode.appendChild(empty);
                return;
            }

            var list = document.createElement('div');
            list.className = 'careforms-submission-list';

            submissions.forEach(function (submission) {
                var definition = getDefinition(submission.formId);
                if (!definition) {
                    return;
                }

                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'careforms-submission-item';

                var main = document.createElement('span');
                main.className = 'careforms-submission-main';
                main.innerHTML = '<strong></strong><span></span>';
                main.querySelector('strong').textContent = submissionLabel(submission);
                main.querySelector('span').textContent = definition.name + ' · Updated ' + displayDate(submission.updatedAt);

                var status = document.createElement('span');
                status.className = 'careforms-status careforms-status-' + submission.status;
                status.textContent = submission.status === 'submitted' ? 'Submitted' : 'Draft';

                item.appendChild(main);
                item.appendChild(status);
                item.addEventListener('click', function () {
                    renderSubmission(definition, submission, mountNode);
                });
                list.appendChild(item);
            });

            mountNode.appendChild(list);
        }).catch(function (error) {
            mountNode.innerHTML = '<div class="careforms-empty-state"><h3>Could not load My Work</h3><p></p></div>';
            mountNode.querySelector('p').textContent = error.message;
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
            if (!target) {
                return;
            }

            if (activeSubmission) {
                showView('work');
            } else {
                renderFormsBrowser();
            }
        });

        renderMyWork();
    });
}());