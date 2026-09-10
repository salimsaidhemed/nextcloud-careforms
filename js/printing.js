(function () {
    'use strict';

    function addPrintButton(container, label) {
        if (!container || container.querySelector('[data-careforms-print]')) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'careforms-secondary-button careforms-print-button';
        button.dataset.careformsPrint = 'true';
        button.textContent = label || 'Print';
        button.addEventListener('click', function () { window.print(); });
        container.appendChild(button);
    }

    function decorateSubmission() {
        document.querySelectorAll('.careforms-rendered-form').forEach(function (form) {
            var panel = form.closest('[data-view-panel]');
            if (!panel || panel.hidden) return;
            var header = panel.querySelector('.careforms-form-header');
            if (!header) return;
            addPrintButton(header, 'Print submission');
            panel.classList.add('careforms-print-target');
        });
    }

    function decorateReports() {
        var panel = document.querySelector('[data-view-panel="reports"]');
        if (!panel || panel.hidden) return;
        var heading = panel.querySelector('.careforms-section-heading');
        if (!heading) return;
        addPrintButton(heading, 'Print report');
        panel.classList.add('careforms-print-target');
    }

    function decorate() {
        document.querySelectorAll('[data-view-panel]').forEach(function (panel) { panel.classList.remove('careforms-print-target'); });
        decorateSubmission();
        decorateReports();
    }

    var observer = new MutationObserver(function () { window.requestAnimationFrame(decorate); });
    document.addEventListener('DOMContentLoaded', function () {
        var app = document.getElementById('careforms-app');
        if (app) observer.observe(app, {childList:true, subtree:true, attributes:true, attributeFilter:['hidden']});
        document.addEventListener('click', function () { window.setTimeout(decorate, 0); });
        decorate();
    });
}());
