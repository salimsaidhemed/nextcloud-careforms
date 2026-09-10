(function () {
    'use strict';

    var ICONS = {
        work: '<path d="M4 5h16v14H4z"/><path d="M8 5V3h8v2"/><path d="M4 10h16"/>',
        forms: '<rect x="5" y="4" width="14" height="16" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        review: '<path d="M5 12l4 4L19 6"/>',
        patients: '<circle cx="12" cy="8" r="3"/><path d="M5 20c.8-4 3.1-6 7-6s6.2 2 7 6"/>',
        reports: '<path d="M5 20V10M12 20V4M19 20v-7"/>',
        admin: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4v-.2a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1A1.7 1.7 0 0 0 4.6 15 1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/>',
        audit: '<path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="8"/>',
        back: '<path d="M15 18l-6-6 6-6"/>',
        save: '<path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8V4M8 20v-6h8v6"/>',
        submit: '<path d="M5 12l4 4L19 6"/>',
        print: '<path d="M7 9V4h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="6"/>',
        clear: '<path d="M6 6l12 12M18 6L6 18"/>',
        approve: '<path d="M5 12l4 4L19 6"/>',
        return: '<path d="M9 7l-5 5 5 5"/><path d="M4 12h9a7 7 0 0 1 7 7"/>',
        add: '<path d="M12 5v14M5 12h14"/>'
    };

    function svg(name) {
        if (!ICONS[name]) return null;
        var span = document.createElement('span');
        span.className = 'careforms-action-icon';
        span.setAttribute('aria-hidden', 'true');
        span.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONS[name] + '</svg>';
        return span;
    }

    function iconForButton(button) {
        if (button.dataset.iconified === 'true') return;
        var label = button.textContent.trim().toLowerCase();
        var name = null;
        if (button.classList.contains('careforms-tab')) {
            name = {
                'my work': 'work', 'forms': 'forms', 'review queue': 'review', 'patients': 'patients',
                'reports': 'reports', 'form admin': 'admin', 'audit log': 'audit'
            }[label] || null;
        } else if (label.indexOf('back') === 0) name = 'back';
        else if (label.indexOf('save draft') === 0 || label === 'saving…') name = 'save';
        else if (label.indexOf('sign & submit') === 0 || label.indexOf('submit') === 0) name = 'submit';
        else if (label.indexOf('print') === 0) name = 'print';
        else if (label.indexOf('clear') === 0) name = 'clear';
        else if (label.indexOf('approve') === 0) name = 'approve';
        else if (label.indexOf('return') === 0) name = 'return';
        else if (label.indexOf('add ') === 0 || label.indexOf('create ') === 0) name = 'add';
        if (!name) return;
        var icon = svg(name);
        if (!icon) return;
        button.prepend(icon);
        button.dataset.iconified = 'true';
    }

    function decorate(root) {
        (root || document).querySelectorAll('button').forEach(iconForButton);
    }

    document.addEventListener('DOMContentLoaded', function () {
        decorate(document);
        var app = document.getElementById('careforms-app');
        if (!app) return;
        new MutationObserver(function () { decorate(app); }).observe(app, {childList:true, subtree:true});
    });
}());
