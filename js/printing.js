(function () {
    'use strict';

    function printTarget(target, title) {
        if (!target) return;

        var clone = target.cloneNode(true);
        clone.querySelectorAll('.careforms-print-button,[data-action="back-to-forms"],.careforms-form-actions,.careforms-section-nav,.careforms-review-actions').forEach(function (node) {
            node.remove();
        });

        clone.querySelectorAll('input, textarea, select').forEach(function (control) {
            var replacement = document.createElement('div');
            replacement.className = 'careforms-print-value';

            if (control.tagName === 'SELECT') {
                replacement.textContent = control.options[control.selectedIndex] ? control.options[control.selectedIndex].text : '';
            } else if (control.type === 'checkbox' || control.type === 'radio') {
                replacement.textContent = control.checked ? '✓' : '○';
            } else {
                replacement.textContent = control.value || '';
            }
            control.replaceWith(replacement);
        });

        var oldFrame = document.getElementById('careforms-print-frame');
        if (oldFrame) oldFrame.remove();

        var frame = document.createElement('iframe');
        frame.id = 'careforms-print-frame';
        frame.setAttribute('aria-hidden', 'true');
        frame.style.position = 'fixed';
        frame.style.right = '0';
        frame.style.bottom = '0';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.style.visibility = 'hidden';
        document.body.appendChild(frame);

        var printDocument = frame.contentDocument || frame.contentWindow.document;
        var styles = '\n' +
            '@page{size:auto;margin:14mm;}\n' +
            'html,body{margin:0;padding:0;background:#fff;color:#000;font:12px/1.4 Arial,sans-serif;height:auto!important;overflow:visible!important;}\n' +
            '.careforms-view,.careforms-report-submission-preview{display:block!important;position:static!important;height:auto!important;overflow:visible!important;padding:0!important;border:0!important;background:#fff!important;}\n' +
            '.careforms-form-header,.careforms-section-heading{display:flex;align-items:flex-start;gap:16px;margin:0 0 16px;}\n' +
            '.careforms-form-header h2,.careforms-section-heading h2{margin:0;font-size:22px;}\n' +
            '.careforms-muted{color:#555;}\n' +
            '.careforms-info-banner,.careforms-report-preview-meta{padding:10px 12px;margin:0 0 14px;border-left:3px solid #666;background:#f5f5f5;}\n' +
            '.careforms-form-layout,.careforms-rendered-form{display:block!important;position:static!important;height:auto!important;max-height:none!important;overflow:visible!important;}\n' +
            '.careforms-form-section{display:block;padding:14px;margin:0 0 14px;border:1px solid #999;border-radius:6px;break-inside:auto;page-break-inside:auto;}\n' +
            '.careforms-form-section h3{margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #ddd;font-size:17px;}\n' +
            '.careforms-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 18px;}\n' +
            '.careforms-field{display:flex;flex-direction:column;gap:5px;break-inside:avoid;page-break-inside:avoid;}\n' +
            '.careforms-field-label{font-weight:700;}\n' +
            '.careforms-checkbox-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5px 12px;}\n' +
            '.careforms-checkbox-option{display:flex;align-items:center;gap:6px;min-height:22px;}\n' +
            '.careforms-print-value{min-height:20px;padding:5px 7px;border:1px solid #ccc;border-radius:4px;white-space:pre-wrap;overflow-wrap:anywhere;}\n' +
            '.careforms-checkbox-option .careforms-print-value{border:0;padding:0;min-height:0;}\n' +
            '.careforms-signature-field img{max-width:360px!important;width:auto!important;max-height:140px!important;border:1px solid #999;}\n' +
            '.careforms-report-card-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px;}\n' +
            '.careforms-report-card,.careforms-report-panel,.careforms-report-group{border:1px solid #aaa;border-radius:6px;padding:12px;margin-bottom:12px;break-inside:avoid;page-break-inside:avoid;}\n' +
            '.careforms-report-columns{display:block;}\n' +
            '.careforms-report-pairs>div{display:flex;justify-content:space-between;gap:16px;padding:4px 0;border-bottom:1px solid #eee;}\n' +
            'img{max-width:100%;height:auto;}\n';

        printDocument.open();
        printDocument.write('<!doctype html><html><head><meta charset="utf-8"><title></title><style>' + styles + '</style></head><body></body></html>');
        printDocument.close();
        printDocument.title = title || 'CareForms';
        printDocument.body.appendChild(printDocument.importNode(clone, true));

        window.setTimeout(function () {
            frame.style.visibility = 'visible';
            frame.contentWindow.focus();
            frame.contentWindow.print();
            window.setTimeout(function () { frame.remove(); }, 1000);
        }, 100);
    }

    function addPrintButton(container, label, targetProvider, titleProvider) {
        if (!container || container.querySelector('[data-careforms-print]')) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'careforms-secondary-button careforms-print-button';
        button.dataset.careformsPrint = 'true';
        button.textContent = label || 'Print';
        button.addEventListener('click', function () {
            printTarget(targetProvider(), titleProvider ? titleProvider() : 'CareForms');
        });
        container.appendChild(button);
    }

    function decorateSubmission() {
        document.querySelectorAll('.careforms-rendered-form').forEach(function (form) {
            var panel = form.closest('[data-view-panel]');
            if (!panel || panel.hidden) return;
            var preview = form.closest('.careforms-report-submission-preview');
            var scope = preview || panel;
            var header = scope.querySelector('.careforms-form-header');
            if (!header) return;
            addPrintButton(header, 'Print submission', function () { return scope; }, function () {
                var heading = scope.querySelector('.careforms-form-header h2');
                return heading ? heading.textContent + ' - CareForms' : 'CareForms submission';
            });
        });
    }

    function decorateReports() {
        var panel = document.querySelector('[data-view-panel="reports"]');
        if (!panel || panel.hidden) return;
        var heading = panel.querySelector('.careforms-section-heading');
        if (!heading) return;
        addPrintButton(heading, 'Print report', function () { return panel; }, function () { return 'CareForms report'; });
    }

    function decorate() {
        decorateSubmission();
        decorateReports();
    }

    var observer = new MutationObserver(function () { window.requestAnimationFrame(decorate); });
    document.addEventListener('DOMContentLoaded', function () {
        var app = document.getElementById('careforms-app');
        if (app) observer.observe(app, {childList:true,subtree:true,attributes:true,attributeFilter:['hidden']});
        document.addEventListener('click', function () { window.setTimeout(decorate, 0); });
        decorate();
    });
}());
