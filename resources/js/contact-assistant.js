export function internationalPhone(value) {
    const trimmed = String(value).trim();
    if (trimmed.length > 40 || !/^(?:\+|00)[0-9 ().-]+$/.test(trimmed)) return null;
    const number = trimmed.replace(/[ ().-]/g, '').replace(/^00/, '+');
    return /^\+[1-9][0-9]{7,14}$/.test(number) ? number : null;
}

export function suggestedCountry(languages, countries, fallback) {
    for (const language of languages || []) {
        try {
            const region = new Intl.Locale(language).region;
            if (countries.some(country => country.code === region)) return region;
        } catch { /* Invalid browser language tags use the configured fallback. */ }
    }
    return countries.some(country => country.code === fallback) ? fallback : countries[0]?.code || '';
}

export function nationalPhone(value, code, countries) {
    const country = countries.find(item => item.code === code);
    const text = String(value ?? '').trim();
    if (!country || text.length > 40 || !/^(?:\+|00)?[0-9 ().-]+$/.test(text)) return null;
    let digits = text.replace(/[ ().-]/g, '');
    if (/^(?:\+|00)/.test(digits)) {
        const international = internationalPhone(text);
        if (!international?.startsWith(`+${country.dial_code}`)) return null;
        digits = international.slice(country.dial_code.length + 1);
    }
    if (!country.lengths.includes(digits.length) && country.national_prefix && digits.startsWith(country.national_prefix)) {
        digits = digits.slice(country.national_prefix.length);
    }
    return country.lengths.includes(digits.length) ? internationalPhone(`+${country.dial_code}${digits}`) : null;
}

export function countryForPhone(value, countries, preferred) {
    const number = internationalPhone(value);
    if (!number) return null;
    const matches = countries.filter(country => number.startsWith(`+${country.dial_code}`) && nationalPhone(number, country.code, countries));
    matches.sort((first, second) => second.dial_code.length - first.dial_code.length
        || Number(second.code === preferred) - Number(first.code === preferred) || Number(second.main) - Number(first.main));
    return matches[0] || null;
}

export function matchingCountries(query, countries) {
    const normalized = text => String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    const term = normalized(query).replace(/^\+/, '');
    return countries.filter(country => normalized(`${country.name} ${country.code} ${country.dial_code}`).includes(term));
}

export function fieldError(field, value, configuration) {
    const text = String(value ?? '').trim();
    switch (field) {
        case 'name':
            return [...text].length >= 2 && [...text].length <= 150 && /^[\p{L}\p{M}][\p{L}\p{M} .'’\-]*[\p{L}\p{M}]$/u.test(text)
                ? '' : 'Introduce tu nombre completo, entre 2 y 150 caracteres.';
        case 'whatsapp':
            return (configuration.phone_countries ? nationalPhone(text, configuration.phone_country, configuration.phone_countries) : internationalPhone(text))
                ? '' : 'Revisa el número y el país seleccionado. Escribe el número nacional completo.';
        case 'email':
            if (!text) return configuration.email_required ? 'Introduce tu correo electrónico.' : '';
            return text.length <= 255 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text) ? '' : 'Revisa el formato de tu correo electrónico.';
        case 'message':
            return [...text].length <= 1000 && !/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u.test(text) ? '' : 'Usa texto plano, hasta 1.000 caracteres.';
        case 'consent':
            return value === true ? '' : 'Acepta el consentimiento para continuar.';
        case 'service_id':
            return !text || configuration.services.some(service => String(service.id) === text) ? '' : 'Elige un servicio disponible.';
        default:
            return '';
    }
}

export function configuredFieldError(field, value, configuration) {
    if (!field) return '';
    const required = field.id === 'email' ? configuration.email_required : field.required;
    const empty = value === '' || value === null || value === undefined || (Array.isArray(value) && !value.length);
    if (empty) return required ? 'Completa esta respuesta para continuar.' : '';
    const text = String(value).trim();
    if (field.type === 'multiple') {
        return Array.isArray(value) && value.length <= 20 && new Set(value).size === value.length
            && value.every(selected => field.options.some(option => option.value === selected)) ? '' : 'Selecciona opciones disponibles, sin repetirlas.';
    }
    if (field.type === 'choice') return field.options.some(option => option.value === value) ? '' : 'Selecciona una opción disponible.';
    if (field.type === 'number') {
        const number = Number(text);
        return /^-?\d{1,12}(?:\.\d{1,4})?$/.test(text) && Number.isFinite(number)
            && (field.min_value === null || number >= Number(field.min_value)) && (field.max_value === null || number <= Number(field.max_value))
            ? '' : 'Introduce un número dentro de los límites indicados, con hasta cuatro decimales.';
    }
    if (field.type === 'date') {
        const date = new Date(`${text}T00:00:00Z`);
        return /^\d{4}-\d{2}-\d{2}$/.test(text) && !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === text
            && (!field.min_date || text >= field.min_date) && (!field.max_date || text <= field.max_date) ? '' : 'Elige una fecha válida dentro del intervalo indicado.';
    }
    if (field.type === 'phone') return field.id === 'whatsapp' || internationalPhone(text) ? '' : 'Incluye + y el código de país.';
    if (field.type === 'service') return '';
    if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text)) return 'Revisa el formato del correo electrónico.';
    return !/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u.test(text)
        && [...text].length >= field.min_length && [...text].length <= field.max_length
        ? '' : `Usa texto plano, entre ${field.min_length} y ${field.max_length} caracteres.`;
}

export function officialWhatsappUrl(value) {
    try {
        const url = new URL(value);
        return url.protocol === 'https:' && url.hostname === 'wa.me' && !url.port && !url.username && !url.password
            && /^\/[1-9][0-9]{7,14}$/.test(url.pathname) && !url.hash
            && [...url.searchParams.keys()].length === 1 && url.searchParams.has('text') ? url.href : null;
    } catch {
        return null;
    }
}

export async function loadAssistantConfiguration(url, request = (endpoint, options) => globalThis.fetch(endpoint, options), timeoutMilliseconds = 20000) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMilliseconds);
    try {
        const response = await request(url, {
            redirect: 'error', headers: {'Accept': 'application/json'}, credentials: 'same-origin', signal: controller.signal,
        });
        const configuration = await response.json();
        if (!response.ok || !configuration.enabled) throw new Error('unavailable');
        return configuration;
    } finally {
        clearTimeout(timeout);
    }
}

export class AssistantSubmission {
    constructor(endpoint, request = (url, options) => globalThis.fetch(url, options), timeoutMilliseconds = 20000) {
        this.endpoint = endpoint;
        this.request = request;
        this.timeoutMilliseconds = timeoutMilliseconds;
        this.pending = null;
        this.saved = null;
    }

    submit(payload, csrfToken) {
        if (this.saved) return Promise.resolve(this.saved);
        if (this.pending) return this.pending;
        this.pending = this.perform(payload, csrfToken).finally(() => { this.pending = null; });
        return this.pending;
    }

    async perform(payload, csrfToken) {
        let response;
        let result;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeoutMilliseconds);
        try {
            response = await this.request(this.endpoint, {
                method: 'POST', credentials: 'same-origin', redirect: 'error', signal: controller.signal,
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: JSON.stringify(payload),
            });
            result = await response.json().catch(error => { if (controller.signal.aborted) throw error; return {}; });
        } catch {
            throw controller.signal.aborted
                ? {kind: 'timeout', message: 'La solicitud tardó demasiado. Conservamos tus respuestas; reintenta sin duplicar la consulta.'}
                : {kind: 'network', message: 'No pudimos conectar. Revisa tu conexión y reintenta; conservamos tus respuestas y evitamos duplicados.'};
        } finally {
            clearTimeout(timeout);
        }
        if (!response.ok) {
            if (response.status === 422) throw {message: 'Revisa los datos indicados.', errors: result.errors || {}};
            if (response.status === 429) {
                const seconds = Number(response.headers?.get('Retry-After'));
                const minutes = Number.isFinite(seconds) && seconds > 0 ? Math.ceil(seconds / 60) : null;
                throw {kind: 'rate_limit', message: minutes
                    ? `Has realizado varios intentos. Espera ${minutes} minuto${minutes === 1 ? '' : 's'} antes de reintentar.`
                    : 'Has realizado varios intentos. Espera antes de reintentar.'};
            }
            if (response.status === 419) throw {message: 'La sesión ha caducado. Recarga la página para continuar.'};
            if (response.status === 404) throw {message: 'El asistente ya no está disponible. Utiliza el formulario de contacto.', unavailable: true};
            throw {message: 'No pudimos guardar tu consulta. Reintenta en unos momentos.'};
        }
        const url = officialWhatsappUrl(result.whatsapp_url);
        if (!url) throw {message: 'No pudimos preparar el enlace de WhatsApp. Reintenta para recuperar tu consulta sin duplicarla.'};
        this.saved = {...result, whatsapp_url: url};
        return this.saved;
    }
}

export class ChatTyping {
    constructor(schedule = (callback, delay) => globalThis.setTimeout(callback, delay), cancel = timer => globalThis.clearTimeout(timer)) {
        this.schedule = schedule;
        this.cancel = cancel;
        this.timer = null;
        this.finish = null;
    }

    stop() {
        this.cancel(this.timer);
        this.timer = null;
        this.finish = null;
    }

    complete() {
        const finish = this.finish;
        this.stop();
        finish?.();
    }

    start(text, render, finish, reducedMotion = false) {
        this.stop();
        const characters = [...text];
        let position = 0;
        this.finish = () => { render(text); finish(); };
        if (reducedMotion) { this.complete(); return; }
        render('');
        const tick = () => {
            position = Math.min(characters.length, position + Math.max(1, Math.ceil(characters.length / 18)));
            render(characters.slice(0, position).join(''));
            if (position === characters.length) this.complete();
            else this.timer = this.schedule(tick, 24);
        };
        this.timer = this.schedule(tick, 220);
    }
}

export function initializeAssistant(root) {
    if (root.dataset.initialized) return;
    const configuration = JSON.parse(root.dataset.config);
    const dialog = root.querySelector('dialog');
    if (!configuration.enabled || !dialog.showModal) return;
    const preview = configuration.preview === true;
    const endpoint = preview ? new URL('/', document.baseURI || location.href) : new URL(root.dataset.storeUrl, location.href);
    if (!preview && endpoint.origin !== location.origin) return;
    root.dataset.initialized = 'true';
    const form = root.querySelector('[data-assistant-form]');
    const responseArea = root.querySelector('[data-assistant-response]');
    const actions = root.querySelector('[data-assistant-actions]');
    let steps = [...form.querySelectorAll('[data-step]')];
    const launcher = root.querySelector('[data-assistant-launch]');
    const next = root.querySelector('[data-assistant-next]');
    const back = root.querySelector('[data-assistant-back]');
    const error = root.querySelector('[data-assistant-error]');
    const submission = new AssistantSubmission(endpoint.pathname);
    let index = 0;
    let busy = false;
    let unavailable = false;
    const history = root.querySelector('[data-assistant-history]');
    const typingIndicator = root.querySelector('[data-assistant-typing]');
    const announcement = root.querySelector('[data-assistant-announcement]');
    const skip = root.querySelector('[data-assistant-skip]');
    let serviceButtons = root.querySelector('[data-assistant-services]');
    const typing = new ChatTyping();
    const motion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    let pageScrollState = null;
    let lastPrompt = null;
    let countrySelect = form.elements.namedItem('phone_country');
    let countryPicker = root.querySelector('[data-assistant-country-picker]');
    let countryLabel = root.querySelector('[data-assistant-country-label]');
    let countrySearch = root.querySelector('[data-assistant-country-search]');
    let countryEmpty = root.querySelector('[data-assistant-country-empty]');
    let phonePreview = root.querySelector('[data-assistant-phone-preview]');
    const countries = configuration.phone_countries;
    configuration.phone_country = suggestedCountry(globalThis.navigator?.languages, countries, configuration.default_phone_country);
    countrySelect.value = configuration.phone_country;

    function normalizedPhone() {
        return nationalPhone(form.elements.whatsapp.value, configuration.phone_country, countries);
    }

    function lockPageScroll() {
        if (pageScrollState) return;

        const body = document.body;
        const documentElement = document.documentElement;
        const scrollY = window.scrollY || documentElement.scrollTop || 0;
        pageScrollState = {
            bodyStyle: body.getAttribute('style'),
            documentStyle: documentElement.getAttribute('style'),
            scrollY,
        };
        const scrollbarWidth = Math.max(0, window.innerWidth - documentElement.clientWidth);

        documentElement.style.overflow = 'hidden';
        body.style.position = 'fixed';
        body.style.top = `-${scrollY}px`;
        body.style.left = '0';
        body.style.right = '0';
        body.style.width = '100%';
        body.style.overflow = 'hidden';
        if (scrollbarWidth > 0) body.style.paddingRight = `${scrollbarWidth}px`;
    }

    function restorePageScroll() {
        if (!pageScrollState) return;

        const {bodyStyle, documentStyle, scrollY} = pageScrollState;
        if (bodyStyle === null) document.body.removeAttribute('style');
        else document.body.setAttribute('style', bodyStyle);
        if (documentStyle === null) document.documentElement.removeAttribute('style');
        else document.documentElement.setAttribute('style', documentStyle);
        pageScrollState = null;
        window.scrollTo(0, scrollY);
    }

    function openDialog() {
        if (dialog.open) return;
        lockPageScroll();
        dialog.showModal();
    }

    function updatePhone() {
        const country = countries.find(item => item.code === configuration.phone_country);
        countryLabel.textContent = `${country?.flag || ''} ${country?.name || 'Elegir país'} +${country?.dial_code || ''}`;
        form.elements.whatsapp.placeholder = country?.example || 'Número nacional';
        const normalized = normalizedPhone();
        phonePreview.textContent = normalized ? `Se usará ${normalized}` : 'Escribe tu número nacional. También puedes pegarlo con + y el prefijo.';
    }

    function syncInternationalPhone() {
        const country = countryForPhone(form.elements.whatsapp.value, countries, configuration.phone_country);
        if (!country) return;
        configuration.phone_country = country.code;
        countrySelect.value = country.code;
        form.elements.whatsapp.value = nationalPhone(form.elements.whatsapp.value, country.code, countries).slice(country.dial_code.length + 1);
        updatePhone();
    }

    function filterCountries() {
        const matches = matchingCountries(countrySearch.value, countries);
        countrySelect.replaceChildren();
        for (const country of matches) countrySelect.add(new Option(`${country.flag} ${country.name} +${country.dial_code}`, country.code));
        countrySelect.value = configuration.phone_country;
        countryEmpty.hidden = matches.length > 0;
    }

    function initializePhoneControls() {
        countrySelect = form.elements.namedItem('phone_country');
        countryPicker = root.querySelector('[data-assistant-country-picker]');
        countryLabel = root.querySelector('[data-assistant-country-label]');
        countrySearch = root.querySelector('[data-assistant-country-search]');
        countryEmpty = root.querySelector('[data-assistant-country-empty]');
        phonePreview = root.querySelector('[data-assistant-phone-preview]');
        countrySelect.value = configuration.phone_country;
        countrySearch.addEventListener('input', filterCountries);
        countrySearch.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
        countrySelect.addEventListener('change', () => {
            const selected = countrySelect.value;
            if (!selected) return;
            const original = countryForPhone(form.elements.whatsapp.value, countries, configuration.phone_country);
            if (original) form.elements.whatsapp.value = nationalPhone(form.elements.whatsapp.value, original.code, countries).slice(original.dial_code.length + 1);
            configuration.phone_country = selected;
            countrySearch.value = '';
            filterCountries();
            countryPicker.open = false;
            updatePhone();
            form.elements.whatsapp.focus();
        });
        countryPicker.addEventListener('toggle', () => {
            if (countryPicker.open) countrySearch.focus();
            else {
                countrySearch.value = '';
                filterCountries();
            }
        });
        form.elements.whatsapp.addEventListener('input', updatePhone);
        form.elements.whatsapp.addEventListener('change', () => { syncInternationalPhone(); updatePhone(); });
        updatePhone();
    }
    initializePhoneControls();

    function definition(id) {
        return configuration.field_schema?.find(field => field.id === id);
    }

    function input(id) {
        const field = definition(id);
        if (field?.type === 'multiple' || field?.type === 'choice') return steps.find(step => step.dataset.step === id)?.querySelector('input[type="checkbox"], button');
        return form.elements.namedItem(id);
    }

    function scrollConversation() {
        const scroller = root.querySelector('[data-assistant-scroll]');
        scroller.scrollTop = scroller.scrollHeight;
    }

    function answer(field) {
        if (field === 'service_id') {
            return configuration.services.find(service => String(service.id) === value(field))?.name || 'Necesito orientación';
        }
        if (field === 'consent') return 'Acepto el consentimiento';
        if (field === 'whatsapp') return normalizedPhone() || value(field);
        const schema = definition(field);
        const selected = value(field);
        if (schema?.type === 'choice') return schema.options.find(option => option.value === selected)?.label || 'Omitido';
        if (schema?.type === 'multiple') return selected.map(item => schema.options.find(option => option.value === item)?.label).join(', ') || 'Omitido';
        return selected || 'Omitido';
    }

    function bubble(text, visitor = false) {
        const element = document.createElement('div');
        element.className = visitor ? 'ca-message ca-message-user' : 'ca-message ca-message-assistant';
        const content = document.createElement('p');
        content.textContent = text;
        element.append(content);
        history.append(element);
        return {element, content};
    }

    function focusStep() {
        const step = steps[index];
        const field = step.dataset.step;
        const target = field === 'service_id' ? serviceButtons?.querySelector('button') : input(field);
        (target || step.querySelector('h3')).focus();
    }

    function updateServiceButtons() {
        serviceButtons?.querySelectorAll('button').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.serviceValue === value('service_id')));
            button.disabled = busy || unavailable;
        });
        for (const step of steps) {
            if (definition(step.dataset.step)?.type !== 'choice') continue;
            step.querySelectorAll('[data-option-value]').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.optionValue === value(step.dataset.step)));
                button.disabled = busy || unavailable || Boolean(submission.saved);
            });
        }
    }

    function editResponse(position, label, action = 'Cambiar') {
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'ca-edit';
        edit.textContent = action;
        edit.setAttribute('aria-label', `${action}: ${label}`);
        edit.disabled = busy || unavailable || Boolean(submission.saved);
        edit.addEventListener('click', () => {
            if (busy || unavailable || submission.saved) return;
            index = position;
            displayStep();
        });
        return edit;
    }

    function renderConversation(animate = true) {
        typing.stop();
        typingIndicator.hidden = true;
        announcement.textContent = '';
        history.replaceChildren();
        bubble(configuration.welcome_message);
        if (configuration.form_intro) bubble(configuration.form_intro);
        for (let position = 0; position < index; position++) {
            const step = steps[position];
            bubble(step.querySelector('h3').textContent);
            const reply = bubble(answer(step.dataset.step), true);
            reply.element.append(editResponse(position, step.querySelector('h3').textContent));
        }
        const question = steps[index].querySelector('h3').textContent;
        const promptKey = `${index}:${question}`;
        const prompt = bubble(question);
        prompt.element.setAttribute('aria-hidden', 'true');
        const finish = () => {
            typingIndicator.hidden = true;
            prompt.element.removeAttribute('aria-hidden');
            announcement.textContent = question;
            scrollConversation();
        };
        if (dialog.open && animate && promptKey !== lastPrompt) {
            typingIndicator.hidden = !motion || motion.matches;
            typing.start(question, text => { prompt.content.textContent = text; scrollConversation(); }, finish, !motion || motion.matches);
        } else finish();
        if (dialog.open) lastPrompt = promptKey;
        scrollConversation();
    }

    motion?.addEventListener('change', () => { if (motion.matches) typing.complete(); });

    function clearError() {
        error.hidden = true;
        error.textContent = '';
        form.querySelectorAll('[aria-invalid]').forEach(field => {
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-errormessage');
        });
    }

    function showError(message, field) {
        error.textContent = message;
        error.hidden = false;
        if (field) {
            if (field.name === 'service_id' && serviceButtons) field = serviceButtons;
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-errormessage', error.id);
            if (field === serviceButtons) serviceButtons.querySelector('button')?.focus();
            else field.focus();
        } else error.focus();
    }

    function value(field) {
        const input = form.elements.namedItem(field);
        if (definition(field)?.type === 'multiple') return [...steps.find(step => step.dataset.step === field).querySelectorAll('input[type="checkbox"]:checked')].map(option => option.value);
        return field === 'consent' ? input.checked : input?.value.trim() || '';
    }

    function summary() {
        const list = root.querySelector('[data-assistant-summary]');
        list.replaceChildren();
        const fields = configuration.field_schema || [];
        for (const field of fields) {
            const selected = value(field.id);
            const omitted = selected === '' || (Array.isArray(selected) && !selected.length);
            const item = document.createElement('div');
            item.className = 'ca-summary-item';
            const copy = document.createElement('div');
            copy.className = 'ca-summary-copy';
            const term = document.createElement('dt');
            const description = document.createElement('dd');
            const response = document.createElement('span');
            term.textContent = field.label;
            if (!field.required) {
                const optional = document.createElement('span');
                optional.className = 'ca-summary-optional';
                optional.textContent = 'Opcional';
                term.append(optional);
            }
            const unanswered = omitted && field.id !== 'service_id';
            response.className = unanswered ? 'ca-summary-omitted' : 'ca-summary-value';
            response.textContent = unanswered ? 'No compartido' : answer(field.id);
            description.append(response, editResponse(steps.findIndex(step => step.dataset.step === field.id), field.label, unanswered ? 'Completar' : 'Cambiar'));
            copy.append(term, description);
            item.append(copy);
            list.append(item);
        }
    }

    function displayStep(focus = true) {
        clearError();
        countryPicker.open = false;
        responseArea.scrollTop = 0;
        steps.forEach((step, position) => { step.hidden = position !== index; });
        back.hidden = index === 0;
        next.textContent = index === steps.length - 1 ? (preview ? 'Completar simulación' : 'Continuar por WhatsApp') : 'Responder';
        next.disabled = busy || unavailable;
        back.disabled = busy;
        const progress = root.querySelector('[data-assistant-progress]');
        progress.max = steps.length;
        progress.value = index + 1;
        root.querySelector('[data-assistant-counter]').textContent = `${index + 1} de ${steps.length}`;
        if (steps[index].dataset.step === 'summary') summary();
        const field = steps[index].dataset.step;
        skip.hidden = !definition(field) || definition(field).type === 'service' || (field === 'email' ? configuration.email_required : definition(field).required);
        skip.disabled = busy || unavailable;
        updateServiceButtons();
        renderConversation(focus);
        if (focus) focusStep();
    }

    function validateStep(position) {
        const field = steps[position].dataset.step;
        if (field === 'whatsapp') { syncInternationalPhone(); updatePhone(); }
        const message = fieldError(field, value(field), configuration) || configuredFieldError(definition(field), value(field), configuration);
        if (message) {
            index = position;
            displayStep(false);
            showError(message, input(field));
            return false;
        }
        return true;
    }

    async function refreshConsent() {
        const configUrl = new URL(root.dataset.configUrl, location.href);
        if (configUrl.origin !== location.origin) throw new Error('unavailable');
        const updated = await loadAssistantConfiguration(`${configUrl.pathname}?fields=1`);
        const changed = updated.schema_revision !== configuration.schema_revision;
        const previous = (configuration.field_schema || []).map(field => ({field, value: value(field.id)}));
        if (updated.fields_html) {
            const parsed = new DOMParser().parseFromString(updated.fields_html, 'text/html');
            root.querySelector('[data-assistant-fields]').replaceChildren(...[...parsed.body.children].map(node => document.importNode(node, true)));
            configuration.field_schema = updated.field_schema;
            steps = [...form.querySelectorAll('[data-step]')];
            for (const item of previous) {
                const current = definition(item.field.id);
                if (!current || current.type !== item.field.type) continue;
                if (current.type === 'multiple') {
                    const step = steps.find(step => step.dataset.step === current.id);
                    step.querySelectorAll('input[type="checkbox"]').forEach(option => { option.checked = item.value.includes(option.value); });
                } else if (form.elements.namedItem(current.id)) form.elements.namedItem(current.id).value = item.value;
            }
            serviceButtons = root.querySelector('[data-assistant-services]');
            initializePhoneControls();
        }
        configuration.email_required = updated.email_required;
        configuration.schema_revision = updated.schema_revision;
        if (form.elements.email) {
            form.elements.email.required = updated.email_required;
            const emailLabel = definition('email')?.label || 'Correo electrónico';
            root.querySelector('[data-assistant-email-label]').textContent = emailLabel + (updated.email_required ? '' : ' (opcional)');
            form.elements.email.setAttribute('aria-required', String(updated.email_required));
        }
        root.querySelector('[data-assistant-consent-text]').textContent = updated.consent_text;
        const privacy = root.querySelector('[data-assistant-privacy]');
        const privacyUrl = updated.privacy_url ? new URL(updated.privacy_url, location.href) : null;
        privacy.hidden = !privacyUrl || privacyUrl.origin !== location.origin;
        privacy.href = privacy.hidden ? '' : privacyUrl.href;
        form.elements.consent.checked = false;
        form.elements.submission_token.value = updated.submission_token;
        const selector = form.elements.namedItem('service_id');
        if (selector) {
            const selected = selector.value;
            selector.replaceChildren(new Option('Quiero orientación general', ''));
            updated.services.forEach(service => selector.add(new Option(service.name, String(service.id))));
            selector.value = updated.services.some(service => String(service.id) === selected) ? selected : '';
            selector.disabled = !updated.services_enabled;
            selector.closest('[data-step]').hidden = !updated.services_enabled;
        }
        if (serviceButtons) {
            serviceButtons.replaceChildren();
            for (const service of [...updated.services, {id: '', name: 'Necesito orientación'}]) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ca-service';
                button.dataset.serviceValue = String(service.id);
                button.textContent = service.name;
                serviceButtons.append(button);
            }
        }
        configuration.services = updated.services;
        configuration.services_enabled = updated.services_enabled;
        steps = [...form.querySelectorAll('[data-step]')].filter(step => step.dataset.step !== 'service_id' || updated.services_enabled);
        index = changed ? 0 : steps.findIndex(step => step.dataset.step === 'consent');
        displayStep();
        showError('El formulario cambió o caducó. Actualizamos los servicios disponibles; revisa y vuelve a aceptar el consentimiento.');
    }

    launcher.addEventListener('click', () => {
        openDialog();
        launcher.setAttribute('aria-expanded', 'true');
        if (submission.saved) root.querySelector('#ca-success-title').focus();
        else displayStep();
    });
    function resetAssistant() {
        busy = false;
        submission.saved = null;
        if (typeof form.reset === 'function') form.reset();
        for (const element of Object.values(form.elements || {})) {
            if (element.type === 'checkbox' || element.type === 'radio') element.checked = false;
            else if (element.name && element.name !== '_token' && element.name !== 'submission_token') element.value = '';
        }
        index = 0;
        history.replaceChildren();
        const success = root.querySelector('[data-assistant-success]');
        if (success) success.hidden = true;
        form.hidden = false;
        actions.hidden = false;
        configuration.phone_country = suggestedCountry(globalThis.navigator?.languages, countries, configuration.default_phone_country);
        if (countrySelect) countrySelect.value = configuration.phone_country;
        syncInternationalPhone();
        updatePhone();
        if (serviceButtons) {
            serviceButtons.querySelectorAll('button').forEach(b => {
                b.setAttribute('aria-pressed', String(b.dataset.serviceValue === ''));
            });
        }
        form.querySelectorAll('[data-option-value]').forEach(b => {
            b.setAttribute('aria-pressed', 'false');
        });
        clearError();
        steps.forEach((step, position) => { step.hidden = position !== 0; });
    }

    const minimizeBtn = root.querySelector('[data-assistant-minimize]');
    if (minimizeBtn) {
        minimizeBtn.addEventListener('click', () => { dialog.close(); });
    }
    const closeBtn = root.querySelector('[data-assistant-close]');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            resetAssistant();
            dialog.close();
        });
    }
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => {
        typing.complete();
        restorePageScroll();
        launcher.setAttribute('aria-expanded', 'false');
        launcher.focus();
    });
    back.addEventListener('click', () => { if (!busy && index > 0) { index--; displayStep(); } });
    function submitStep() {
        if (!preview && typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
    }
    if (preview) {
        next.addEventListener('click', submitStep);
        form.addEventListener('keydown', event => {
            if (event.defaultPrevented || event.key !== 'Enter' || event.target.tagName !== 'INPUT') return;
            event.preventDefault();
            submitStep();
        });
    }
    form.addEventListener('click', event => {
        const option = event.target.closest('[data-option-value]');
        if (option) {
            const field = option.dataset.optionField;
            if (busy || unavailable || submission.saved || steps[index].dataset.step !== field) return;
            form.elements.namedItem(field).value = option.dataset.optionValue;
            steps[index].querySelectorAll('[data-option-value]').forEach(button => button.setAttribute('aria-pressed', String(button === option)));
            submitStep();
            return;
        }
        const button = event.target.closest('[data-service-value]');
        if (!button || busy || unavailable || submission.saved || steps[index].dataset.step !== 'service_id') return;
        form.elements.namedItem('service_id').value = button.dataset.serviceValue;
        updateServiceButtons();
        submitStep();
    });
    skip.addEventListener('click', () => {
        const field = steps[index].dataset.step;
        if (busy || unavailable || submission.saved || !definition(field) || definition(field).required || (field === 'email' && configuration.email_required)) return;
        if (definition(field).type === 'multiple') steps[index].querySelectorAll('input[type="checkbox"]').forEach(option => { option.checked = false; });
        else form.elements.namedItem(field).value = '';
        submitStep();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || unavailable || submission.saved) return;
        if (!validateStep(index)) return;
        if (index < steps.length - 1) { index++; displayStep(); return; }
        for (let position = 0; position < steps.length - 1; position++) {
            if (!validateStep(position)) return;
        }
        const length = (configuration.field_schema || []).reduce((total, field) => {
            const selected = value(field.id);
            if (field.type !== 'service' && (selected === '' || (Array.isArray(selected) && !selected.length))) return total;
            return total + [...answer(field.id)].length;
        }, 0);
        if (length > 4000) { showError('La consulta es demasiado larga. Reduce las respuestas a un total de 4.000 caracteres.'); return; }
        if (preview) {
            typing.complete();
            submission.saved = {simulated: true};
            form.hidden = true;
            actions.hidden = true;
            root.querySelector('[data-assistant-success]').hidden = false;
            root.querySelector('#ca-success-title').textContent = 'Simulación completada';
            root.querySelector('[data-assistant-final-message]').textContent = 'Así terminará la conversación. No se guardó ninguna consulta ni se abrió WhatsApp.';
            root.querySelector('#ca-success-title').focus();
            return;
        }
        busy = true;
        typing.complete();
        updateServiceButtons();
        history.querySelectorAll('button').forEach(button => { button.disabled = true; });
        root.querySelector('[data-assistant-summary]').querySelectorAll('button').forEach(button => { button.disabled = true; });
        skip.disabled = true;
        next.disabled = true;
        back.disabled = true;
        next.textContent = 'Guardando consulta…';
        form.setAttribute('aria-busy', 'true');
        clearError();
        try {
            const payload = Object.fromEntries(new FormData(form));
            delete payload._token;
            payload.consent = form.elements.consent.checked;
            payload.whatsapp = normalizedPhone();
            payload.phone_country = configuration.phone_country;
            payload.answers = {};
            for (const field of configuration.field_schema || []) {
                if (field.id.startsWith('f_')) {
                    payload.answers[field.id] = value(field.id);
                    delete payload[field.id];
                }
            }
            if (!Object.keys(payload.answers).length) delete payload.answers;
            const saved = await submission.submit(payload, form.elements._token.value);
            form.hidden = true;
            actions.hidden = true;
            root.querySelector('[data-assistant-success]').hidden = false;
            root.querySelector('[data-assistant-final-message]').textContent = saved.message;
            root.querySelector('[data-assistant-whatsapp]').href = saved.whatsapp_url;
            if (dialog.open) root.querySelector('#ca-success-title').focus();
            // Opening a prefilled link is only an attempt; the visible link remains available.
            if (dialog.open) window.open(saved.whatsapp_url, '_blank', 'noopener,noreferrer');
        } catch (failure) {
            if (failure.errors?.submission_token) {
                try { await refreshConsent(); }
                catch { showError('No pudimos actualizar el formulario. Conservamos tus respuestas; recarga para continuar.'); }
            } else {
                const failedField = Object.keys(failure.errors || {}).find(name => name === 'phone_country' || steps.some(step => step.dataset.step === (name.startsWith('answers.') ? name.split('.')[1] : name)));
                const answerField = failedField?.startsWith('answers.') ? failedField.split('.')[1] : failedField;
                const field = answerField === 'phone_country' ? 'whatsapp' : answerField;
                if (field) { index = steps.findIndex(step => step.dataset.step === field); displayStep(false); }
                showError(field ? failure.errors[failedField][0] : (Object.values(failure.errors || {})[0]?.[0] || failure.message), field ? input(field) : null);
                unavailable = Boolean(failure.unavailable);
            }
        } finally {
            busy = false;
            form.removeAttribute('aria-busy');
            next.disabled = unavailable;
            back.disabled = false;
            next.textContent = index === steps.length - 1 ? (preview ? 'Completar simulación' : 'Continuar por WhatsApp') : 'Responder';
            skip.disabled = unavailable;
            updateServiceButtons();
            history.querySelectorAll('button').forEach(button => { button.disabled = unavailable || Boolean(submission.saved); });
            root.querySelector('[data-assistant-summary]').querySelectorAll('button').forEach(button => { button.disabled = unavailable || Boolean(submission.saved); });
        }
    });

    const obstacles = [...document.querySelectorAll('[data-cookie-banner], [data-floating-obstacle]')];
    function reserveSpace() {
        const height = window.visualViewport?.height || window.innerHeight;
        root.style.setProperty('--ca-viewport-height', `${height}px`);
        const launcherRect = launcher.getBoundingClientRect();
        let offset = 0;
        for (const obstacle of obstacles) {
            const rect = obstacle.getBoundingClientRect();
            if (rect.height > 0 && rect.bottom > height / 2 && rect.top < height && rect.right > launcherRect.left && rect.left < launcherRect.right) {
                offset = Math.max(offset, height - Math.max(0, rect.top) + 8);
            }
        }
        root.style.setProperty('--ca-obstacle-space', `${offset}px`);
        launcher.style.visibility = offset > height - 96 ? 'hidden' : 'visible';
        if (dialog.open) scrollConversation();
    }
    launcher.hidden = false;
    displayStep(false);
    reserveSpace();
    if (preview) { openDialog(); launcher.setAttribute('aria-expanded', 'true'); displayStep(); }
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(reserveSpace);
        obstacles.forEach(obstacle => observer.observe(obstacle));
    }
    window.addEventListener('resize', reserveSpace, {passive: true});
    window.addEventListener('scroll', reserveSpace, {passive: true});
    window.visualViewport?.addEventListener('resize', reserveSpace, {passive: true});
    document.addEventListener('toggle', reserveSpace, true);
    document.addEventListener('contact-assistant:layout', reserveSpace);
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-contact-assistant]').forEach(initializeAssistant);
}
