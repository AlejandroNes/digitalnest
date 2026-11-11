/**
 * Copy buttons: <button data-copy="element-id">. Announces the result through the button's own status region.
 */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) {
        return;
    }
    const source = document.getElementById(button.dataset.copy);
    const status = button.querySelector('[data-copy-status]');
    if (!source || !status) {
        return;
    }
    const original = status.dataset.label || status.textContent;
    status.dataset.label = original;
    try {
        await navigator.clipboard.writeText(source.textContent);
        status.textContent = 'Copiado';
    } catch {
        const range = document.createRange();
        range.selectNodeContents(source);
        window.getSelection()?.removeAllRanges();
        window.getSelection()?.addRange(range);
        status.textContent = 'Selecciona y copia';
    }
    window.setTimeout(() => {
        status.textContent = original;
    }, 2000);
});

/**
 * Mobile menu: toggles the header navigation and closes it with Escape or a click outside.
 */
const header = document.querySelector('[data-header]');
const toggle = header?.querySelector('[data-menu-toggle]');
if (header && toggle) {
    const setOpen = (open) => {
        header.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(!header.classList.contains('is-open')));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && header.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (header.classList.contains('is-open') && !header.contains(event.target)) {
            setOpen(false);
        }
    });
}

/**
 * Filters marked with data-autosubmit apply as soon as they change.
 */
document.addEventListener('change', (event) => {
    if (event.target.matches?.('[data-autosubmit]')) {
        event.target.form?.requestSubmit();
    }
});
