/**
 * DigitalNest app shell: theme switch, coupon copy with toast and haptics.
 */
const root = document.documentElement;

const storedTheme = () => {
    try {
        return localStorage.getItem('nest-theme');
    } catch {
        return null;
    }
};

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) {
        return;
    }
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    document.querySelector('meta[name=theme-color]')?.setAttribute('content', next === 'dark' ? '#09090d' : '#f4f4f7');
    try {
        localStorage.setItem('nest-theme', next);
    } catch {
        // Storage unavailable: the choice lasts for this page only.
    }
});

let toastTimer;
const toast = (message) => {
    const element = document.querySelector('[data-toast]');
    if (!element) {
        return;
    }
    element.textContent = message;
    element.dataset.visible = 'true';
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        element.dataset.visible = 'false';
    }, 2200);
};

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-code]');
    if (!button) {
        return;
    }
    const code = button.dataset.copyCode;
    try {
        await navigator.clipboard.writeText(code);
        navigator.vibrate?.(12);
        toast(`Código ${code} copiado`);
    } catch {
        toast(`Tu código: ${code}`);
    }
});

if (!storedTheme() && !root.dataset.theme) {
    root.dataset.theme = 'dark';
}

/**
 * Saved deals: slugs kept in localStorage (no account needed). Cards mark their state; /guardados renders them.
 */
const SAVED_KEY = 'nest-saved-deals';
const MAX_SAVED = 50;

const readSaved = () => {
    try {
        const value = JSON.parse(localStorage.getItem(SAVED_KEY) ?? '[]');
        return Array.isArray(value) ? value.filter((slug) => typeof slug === 'string' && /^[a-z0-9-]+$/.test(slug)).slice(0, MAX_SAVED) : [];
    } catch {
        return [];
    }
};

const writeSaved = (slugs) => {
    try {
        localStorage.setItem(SAVED_KEY, JSON.stringify(slugs.slice(0, MAX_SAVED)));
        return true;
    } catch {
        return false;
    }
};

const markSaved = () => {
    const saved = new Set(readSaved());
    document.querySelectorAll('[data-save-deal]').forEach((button) => {
        const isSaved = saved.has(button.dataset.saveDeal);
        button.setAttribute('aria-pressed', String(isSaved));
        button.setAttribute('aria-label', isSaved ? 'Quitar de guardados' : 'Guardar oferta');
    });
};

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-save-deal]');
    if (!button) {
        return;
    }
    const slug = button.dataset.saveDeal;
    const saved = readSaved();
    const isSaved = saved.includes(slug);
    const next = isSaved ? saved.filter((item) => item !== slug) : [slug, ...saved];
    if (!writeSaved(next)) {
        toast('No se pudo guardar en este navegador');
        return;
    }
    navigator.vibrate?.(8);
    toast(isSaved ? 'Quitada de guardados' : 'Guardada');
    markSaved();
    if (isSaved) {
        button.closest('[data-saved-list] article')?.remove();
    }
});

const loadSavedList = async () => {
    const container = document.querySelector('[data-saved-list]');
    if (!container) {
        return;
    }
    const slugs = readSaved();
    if (slugs.length === 0) {
        container.dataset.state = 'empty';
        return;
    }
    const params = new URLSearchParams();
    slugs.forEach((slug) => params.append('slugs[]', slug));
    try {
        const response = await fetch(`${container.dataset.source}?${params}`, { headers: { Accept: 'text/html' } });
        if (!response.ok) {
            throw new Error(String(response.status));
        }
        container.innerHTML = await response.text();
        container.dataset.state = container.querySelector('article') ? 'ready' : 'empty';
        markSaved();
    } catch {
        container.dataset.state = 'error';
    }
};

markSaved();
loadSavedList();

/**
 * PWA: service worker registration and a discreet install prompt from the second visit on.
 */
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

const storage = {
    get: (key) => {
        try {
            return localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set: (key, value) => {
        try {
            localStorage.setItem(key, value);
        } catch {
            // Ignore: storage unavailable.
        }
    },
};

const installPrompt = document.querySelector('[data-install-prompt]');
const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const visits = Number(storage.get('nest-visits') ?? 0) + 1;
storage.set('nest-visits', String(visits));
const canPrompt = installPrompt && !isStandalone && visits >= 2 && storage.get('nest-install-dismissed') === null;
let deferredInstall = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstall = event;
    if (canPrompt) {
        installPrompt.hidden = false;
    }
});

const isIosSafari = /iphone|ipad|ipod/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent);
if (canPrompt && isIosSafari) {
    installPrompt.querySelector('[data-install-hint]').textContent = 'Toca Compartir y luego «Añadir a pantalla de inicio».';
    installPrompt.querySelector('[data-install-accept]').hidden = true;
    installPrompt.hidden = false;
}

installPrompt?.addEventListener('click', async (event) => {
    if (event.target.closest('[data-install-dismiss]')) {
        storage.set('nest-install-dismissed', '1');
        installPrompt.hidden = true;
    }
    if (event.target.closest('[data-install-accept]') && deferredInstall) {
        deferredInstall.prompt();
        await deferredInstall.userChoice;
        deferredInstall = null;
        installPrompt.hidden = true;
    }
});
