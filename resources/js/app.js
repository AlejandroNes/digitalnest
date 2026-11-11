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
