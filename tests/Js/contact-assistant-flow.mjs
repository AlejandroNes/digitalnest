import assert from 'node:assert/strict';
import fs from 'node:fs';
import {initializeAssistant} from '../../resources/js/contact-assistant.js';

const html = fs.readFileSync(0, 'utf8');
const decode = text => text.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const configuration = JSON.parse(decode(html.match(/data-config="([^"]+)"/)[1]));
const refreshToken = decode(html.match(/data-refresh-token value="([^"]*)"/)[1]);
const names = [...html.matchAll(/<section data-step="([^"]+)"/g)].map(match => match[1]);
let focused;
class Element {
    constructor() { this.hidden = false; this.dataset = {}; this.attributes = {}; this.listeners = {}; this.children = []; this.textContent = ''; this.value = ''; this.checked = false; this.disabled = false; this.style = {}; }
    addEventListener(type, listener) { (this.listeners[type] ||= []).push(listener); }
    async emit(type, target = this, details = {}) { await Promise.all((this.listeners[type] || []).map(listener => listener({target, preventDefault() {}, ...details}))); }
    getAttribute(key) { return this.attributes[key] ?? null; }
    setAttribute(key, value) { this.attributes[key] = value; if (key === 'style') this.style = {}; }
    removeAttribute(key) { delete this.attributes[key]; if (key === 'style') this.style = {}; }
    focus() { focused = this; }
    replaceChildren(...children) { this.children = children; }
    append(...children) { this.children.push(...children); }
    querySelectorAll(selector) { return this.children.flatMap(child => [...(selector === 'button' && child.type === 'button' || selector === 'span' && child.tagName === 'SPAN' ? [child] : []), ...child.querySelectorAll(selector)]); }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    closest(selector) { if (selector === '[data-option-value]' && this.dataset.optionValue === undefined) return null; if (selector === '[data-service-value]' && this.dataset.serviceValue === undefined) return null; return this; }
    getBoundingClientRect() { return {left: 700, right: 950, top: 700, bottom: 750, height: 50}; }
}
const nodes = {};
for (const selector of ['[data-assistant-launch]', '[data-assistant-next]', '[data-assistant-back]', '[data-assistant-error]', '[data-assistant-progress]', '[data-assistant-counter]', '[data-assistant-summary]', '[data-assistant-scroll]', '[data-assistant-minimize]', '[data-assistant-close]', '[data-assistant-success]', '[data-assistant-final-message]', '[data-assistant-whatsapp]', '#ca-success-title', '[data-assistant-consent-text]', '[data-assistant-privacy]', '[data-assistant-email-label]', '[data-assistant-history]', '[data-assistant-typing]', '[data-assistant-announcement]', '[data-assistant-skip]', '[data-assistant-services]', '[data-assistant-country-picker]', '[data-assistant-country-label]', '[data-assistant-country-search]', '[data-assistant-country-empty]', '[data-assistant-phone-preview]', '[data-assistant-composer]', '[data-assistant-response]', '[data-assistant-actions]']) nodes[selector] = new Element;
nodes['[data-assistant-error]'].id = 'contact-assistant-error';
const fields = Object.fromEntries([...names.filter(name => name !== 'summary'), '_token', 'submission_token', 'website', 'phone_country'].map(name => [name, new Element]));
const formHtml = html.match(/<form data-assistant-form\b[\s\S]*?<\/form>/)[0];
for (const name of ['_token', 'submission_token']) fields[name].value = decode(formHtml.match(new RegExp(`<input[^>]*name="${name}"[^>]*value="([^"]*)"`))[1]);
fields.namedItem = name => fields[name];
const steps = names.map(name => {
    const step = new Element;
    step.dataset.step = name;
    step.heading = new Element;
    step.heading.textContent = decode(html.match(new RegExp(`<section data-step="${name}"[\\s\\S]*?<h3[^>]*>([^<]+)</h3>`))[1]);
    const definition = configuration.field_schema.find(field => field.id === name);
    step.options = (definition?.options || []).map(option => {
        const element = new Element;
        element.value = option.value;
        element.dataset = {optionValue: option.value, optionField: name};
        return element;
    });
    step.querySelector = selector => selector === 'h3' ? step.heading : step.options[0] || null;
    step.querySelectorAll = selector => selector.includes('checkbox') ? step.options.filter(option => !selector.includes(':checked') || option.checked) : selector === '[data-option-value]' && definition?.type === 'choice' ? step.options : [];
    return step;
});
const form = new Element;
form.elements = fields;
form.querySelectorAll = selector => selector === '[data-step]' ? steps : Object.values(fields).filter(field => field.attributes?.['aria-invalid']);
nodes['[data-assistant-form]'] = form;
form.requestSubmit = () => {
    assert.equal(configuration.preview, false, 'A sandboxed simulator must not invoke a native form submission');
    return form.emit('submit');
};
form.dispatchEvent = event => { assert.equal(event.type, 'submit'); return form.emit('submit'); };
const dialog = new Element;
dialog.open = false;
dialog.showModal = () => { dialog.open = true; };
dialog.close = () => { dialog.open = false; dialog.emit('close'); };
nodes.dialog = dialog;
const root = new Element;
root.dataset = {config: JSON.stringify(configuration), storeUrl: '/asistente-contacto', configUrl: '/asistente-contacto'};
root.style = {setProperty() {}};
root.querySelector = selector => { assert.ok(nodes[selector], `Unexpected selector ${selector}`); return nodes[selector]; };
const storage = {setItem() { throw new Error('Personal information must not be stored'); }};
globalThis.localStorage = storage;
globalThis.sessionStorage = storage;
globalThis.location = new URL('https://corporate.test/contacto');
const pageBody = new Element;
const documentElement = Object.assign(new Element, {clientWidth: 1007, scrollTop: 0});
globalThis.document = {body: pageBody, documentElement, querySelectorAll: () => [], createElement: tag => Object.assign(new Element, {tagName: tag.toUpperCase()}), addEventListener() {}};
let openingAttempts = 0;
const restoredScrollPositions = [];
globalThis.window = {matchMedia: () => ({matches: true, addEventListener() {}}), innerHeight: 800, innerWidth: 1024, scrollY: 320, scrollTo(x, y) { restoredScrollPositions.push([x, y]); }, addEventListener() {}, open(url) { assert.match(url, /^https:\/\/wa\.me\//); openingAttempts++; return null; }};
globalThis.FormData = class {
    constructor(target) { this.values = Object.entries(target.elements).filter(([name, field]) => typeof field !== 'function' && name !== 'summary' && (name !== 'consent' || field.checked)).map(([name, field]) => [name, field.value]); }
    [Symbol.iterator]() { return this.values[Symbol.iterator](); }
};
let calls = [];
let resolveRequest;
fields.phone_country.add = child => fields.phone_country.children.push(child);
globalThis.Option = class extends Element { constructor(text, value) { super(); this.textContent = text; this.value = value; } };
if (fields.service_id) {
    fields.service_id.add = child => fields.service_id.children.push(child);
    fields.service_id.closest = () => steps.find(step => step.dataset.step === 'service_id');
}
globalThis.fetch = (url, options) => {
    if (!options.method) return Promise.resolve({ok: true, json: async () => ({...configuration, email_required: true, submission_token: refreshToken})});
    calls.push({url, options});
    return new Promise(resolve => { resolveRequest = resolve; });
};
initializeAssistant(root);
const visible = () => steps.filter(step => !step.hidden).map(step => step.dataset.step);
const submit = () => configuration.preview ? nodes['[data-assistant-next]'].emit('click') : form.emit('submit');
if (configuration.preview) assert.match(html, /data-assistant-next type="button"/);
async function advanceCustomFields() {
    while (visible()[0]?.startsWith('f_')) {
        const id = visible()[0];
        const definition = configuration.field_schema.find(field => field.id === id);
        const step = steps.find(item => item.dataset.step === id);
        if (!fields[id].value && !step.options.some(option => option.checked)) {
            await submit();
            assert.equal(visible()[0], id, 'A required custom question must reject an empty answer');
        }
        if (definition.type === 'choice') {
            await form.emit('click', step.options[0]);
            continue;
        }
        if (definition.type === 'multiple') step.options.forEach(option => { option.checked = true; });
        else fields[id].value = ({text: 'Respuesta adicional', textarea: 'Respuesta con detalle', email: 'extra@example.test', phone: '+447700900123', number: '25.5', date: '2026-10-15'})[definition.type];
        await submit();
    }
}

await nodes['[data-assistant-launch]'].emit('click');
assert.equal(dialog.open, true);
assert.equal(documentElement.style.overflow, 'hidden');
assert.equal(pageBody.style.position, 'fixed');
assert.equal(pageBody.style.top, '-320px');
assert.equal(pageBody.style.paddingRight, '17px');
assert.equal(focused, fields.name);
assert.deepEqual(visible(), ['name']);
await dialog.emit('click', form);
assert.equal(dialog.open, true, 'Clicks inside the chat must not close it');
await dialog.emit('click', dialog);
assert.equal(dialog.open, false, 'Clicking the backdrop must close the chat');
assert.deepEqual(restoredScrollPositions, [[0, 320]], 'Closing restores the previous page scroll position');
assert.deepEqual(pageBody.style, {});
assert.deepEqual(documentElement.style, {});
await nodes['[data-assistant-launch]'].emit('click');
assert.equal(dialog.open, true);
fields.name.value = '<script>bad</script>';
await submit();
assert.deepEqual(visible(), ['name']);
assert.equal(focused, fields.name);
assert.equal(nodes['[data-assistant-history]'].children.some(child => child.children[0].textContent === '<script>bad</script>'), false);
assert.equal(calls.length, 0);
fields.name.value = 'Ana Ejemplo';
if (configuration.preview) {
    await form.emit('keydown', Object.assign(fields.name, {tagName: 'INPUT', type: 'text'}), {key: 'Enter'});
} else await submit();
assert.ok(nodes['[data-assistant-history]'].children.some(child => child.children[0].textContent === 'Ana Ejemplo'));
if (fields.service_id) {
    const serviceButton = new Element;
    serviceButton.dataset.serviceValue = String(configuration.services[0]?.id || '');
    const requestSubmit = form.requestSubmit;
    delete form.requestSubmit;
    await form.emit('click', serviceButton);
    form.requestSubmit = requestSubmit;
    assert.equal(fields.service_id.value, String(configuration.services[0]?.id || ''));
}
assert.deepEqual(visible(), ['whatsapp']);
await nodes['[data-assistant-minimize]'].emit('click');
await nodes['[data-assistant-launch]'].emit('click');
assert.deepEqual(visible(), ['whatsapp']);
assert.equal(fields.name.value, 'Ana Ejemplo');
assert.equal(calls.length, 0, 'Closing and reopening an unfinished chat never stores a lead');
nodes['[data-assistant-country-search]'].value = 'Bolivia';
await nodes['[data-assistant-country-search]'].emit('input');
assert.equal(fields.phone_country.children.length, 1);
assert.equal(fields.phone_country.children[0].value, 'BO');
nodes['[data-assistant-country-search]'].value = 'no such country';
await nodes['[data-assistant-country-search]'].emit('input');
assert.equal(nodes['[data-assistant-country-empty]'].hidden, false);
nodes['[data-assistant-country-search]'].value = '';
await nodes['[data-assistant-country-search]'].emit('input');
fields.phone_country.value = 'BO';
await fields.phone_country.emit('change');
fields.whatsapp.value = '7777 7777';
await fields.whatsapp.emit('input');
assert.equal(nodes['[data-assistant-phone-preview]'].textContent, 'Se usará +59177777777');
fields.whatsapp.value = '+34 (611) 123-456';
await fields.whatsapp.emit('change');
assert.equal(fields.phone_country.value, 'ES');
assert.equal(fields.whatsapp.value, '611123456');
await submit();
if (configuration.email_required) {
    assert.equal(nodes['[data-assistant-skip]'].hidden, true);
    fields.email.value = 'bad';
    await nodes['[data-assistant-skip]'].emit('click');
    assert.deepEqual(visible(), ['email']);
    assert.equal(fields.email.value, 'bad');
}
if (!configuration.email_required) {
    fields.email.value = 'discard@example.test';
    await nodes['[data-assistant-skip]'].emit('click');
    assert.equal(fields.email.value, '');
    assert.deepEqual(visible(), ['message']);
    await nodes['[data-assistant-back]'].emit('click');
}
fields.email.value = 'ana@example.test';
await submit();
fields.message.value = 'Precio & plazo? Sí.';
await submit();
await advanceCustomFields();
fields.consent.checked = false;
await submit();
assert.deepEqual(visible(), ['consent']);
assert.equal(focused, fields.consent);
fields.consent.checked = true;
if (configuration.preview) {
    await form.emit('keydown', Object.assign(fields.consent, {tagName: 'INPUT', type: 'checkbox'}), {key: 'Enter'});
} else await submit();
assert.deepEqual(visible(), ['summary']);
assert.ok(nodes['[data-assistant-summary]'].querySelectorAll('span').some(child => child.textContent === '+34611123456'));
if (configuration.preview) {
    await submit();
    assert.equal(calls.length, 0);
    assert.equal(openingAttempts, 0);
    assert.equal(form.hidden, true);
    assert.equal(nodes['#ca-success-title'].textContent, 'Simulación completada');
    process.stdout.write(JSON.stringify({simulated: true, calls: 0}));
    process.exit(0);
}
const editMessage = nodes['[data-assistant-summary]'].querySelectorAll('button').find(button => button.attributes['aria-label'] === 'Cambiar: Mensaje');
await editMessage.emit('click');
assert.deepEqual(visible(), ['message']);
assert.equal(fields.message.value, 'Precio & plazo? Sí.');
fields.message.value = 'Consulta corregida';
await submit();
await advanceCustomFields();
assert.ok(nodes['[data-assistant-history]'].children.some(child => child.children[0].textContent === 'Consulta corregida'));
assert.equal(nodes['[data-assistant-history]'].children.some(child => child.children[0].textContent === 'Precio & plazo? Sí.'), false);
await nodes['[data-assistant-back]'].emit('click');
fields.message.value = 'Precio & plazo? Sí.';
await submit();
await advanceCustomFields();
await submit();
const attempt = submit();
const doubleClick = submit();
assert.equal(calls.length, 1);
assert.equal(nodes['[data-assistant-next]'].disabled, true);
resolveRequest({ok: false, status: 422, json: async () => ({errors: {submission_token: ['Caducado']}})});
await Promise.all([attempt, doubleClick]);
assert.deepEqual(visible(), ['consent']);
assert.equal(fields.consent.checked, false, 'Refreshing consent must never precheck it');
assert.equal(fields.submission_token.value, refreshToken);
assert.equal(fields.email.required, true);
assert.equal(nodes['[data-assistant-email-label]'].textContent, configuration.field_schema?.find(field => field.id === 'email')?.label || 'Correo electrónico');
await submit();
assert.equal(calls.length, 1, 'A refreshed form requires consent again');
fields.consent.checked = true;
await submit();
const storageFailure = submit();
resolveRequest({ok: false, status: 503, json: async () => ({})});
await storageFailure;
assert.equal(form.hidden, false);
assert.equal(fields.name.value, 'Ana Ejemplo');
assert.equal(nodes['[data-assistant-next]'].disabled, false);
const retry = submit();
assert.equal(calls.length, 3);
assert.equal(calls[1].options.body, calls[2].options.body);
assert.equal(JSON.parse(calls[2].options.body).whatsapp, '+34611123456');
assert.equal(JSON.parse(calls[2].options.body).phone_country, 'ES');
resolveRequest({ok: true, status: 202, json: async () => ({message: 'Consulta guardada.', whatsapp_url: 'https://wa.me/34911234567?text=Hola', whatsapp_status: 'link_prepared'})});
await retry;
assert.equal(form.hidden, true);
assert.equal(nodes['[data-assistant-success]'].hidden, false);
assert.equal(nodes['[data-assistant-whatsapp]'].href, 'https://wa.me/34911234567?text=Hola');
assert.equal(openingAttempts, 1);
assert.equal(focused, nodes['#ca-success-title']);
await nodes['[data-assistant-minimize]'].emit('click');
assert.equal(dialog.open, false);
assert.equal(nodes['[data-assistant-typing]'].hidden, true);
assert.equal(focused, nodes['[data-assistant-launch]']);
await nodes['[data-assistant-launch]'].emit('click');
assert.equal(focused, nodes['#ca-success-title']);
await submit();
assert.equal(calls.length, 3, 'Reopening a completed widget must not create another lead');
await nodes['[data-assistant-close]'].emit('click');
assert.equal(dialog.open, false);
assert.equal(form.hidden, false);
process.stdout.write(JSON.stringify({payload: JSON.parse(calls[2].options.body), calls: calls.length, completed: true}));
