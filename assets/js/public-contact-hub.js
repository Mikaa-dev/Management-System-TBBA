(() => {
    'use strict';

    const hub = document.getElementById('tbbaContactHub');
    if (!hub) return;

    const launcher = hub.querySelector('[data-contact-launcher]');
    const panel = hub.querySelector('[data-contact-panel]');
    const closeButton = hub.querySelector('[data-contact-close]');
    const pageTargets = hub.querySelectorAll('[data-contact-target]');

    const setOpen = (open) => {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
        if (open) window.setTimeout(() => closeButton.focus(), 60);
    };

    launcher.addEventListener('click', () => setOpen(panel.hidden));
    closeButton.addEventListener('click', () => setOpen(false));

    pageTargets.forEach((link) => {
        link.addEventListener('click', () => {
            setOpen(false);
            if (link.dataset.contactTarget === 'inquiry') {
                window.setTimeout(() => document.getElementById('inqName')?.focus(), 650);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            launcher.focus();
        }
    });
})();
