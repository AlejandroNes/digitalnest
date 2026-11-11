import assert from 'node:assert/strict';
import {loadAssistantConfiguration, configuredFieldError, AssistantSubmission, ChatTyping, fieldError, internationalPhone, officialWhatsappUrl, suggestedCountry, nationalPhone, countryForPhone, matchingCountries} from '../../resources/js/contact-assistant.js';

const configuration = {email_required: false, services: [{id: 3, name: 'Consultoría'}]};
const answers = {name: 'Ana María O’Neill', whatsapp: '0034 (611) 123-456', email: '', service_id: '3', message: '', consent: true};
for (const [field, value] of Object.entries(answers)) assert.equal(fieldError(field, value, configuration), '');
assert.equal(internationalPhone(answers.whatsapp), '+34611123456');
for (const [field, value] of [['name', '<script>x</script>'], ['whatsapp', '611123456'], ['email', 'bad'], ['message', '<b>bad</b>'], ['consent', false], ['service_id', '99']]) {
    assert.ok(fieldError(field, value, configuration), `${field} must show an error`);
}
assert.ok(fieldError('email', '', {...configuration, email_required: true}));
assert.equal(fieldError('name', '李明', configuration), '');
const url = 'https://wa.me/34911234567?text=Hola%20%26%20consulta';
assert.equal(officialWhatsappUrl(url), url);
for (const unsafe of ['javascript:alert(1)', 'https://wa.me.evil.test/34911234567?text=hello', 'https://evil.test', 'https://user:pass@wa.me/34911234567?text=hello', 'https://wa.me/34911234567?text=hello&next=evil', '//wa.me/34911234567?text=hello']) {
    assert.equal(officialWhatsappUrl(unsafe), null);
}
const success = {ok: true, status: 202, json: async () => ({message: 'Guardada', whatsapp_url: url, whatsapp_status: 'link_prepared'})};
let attempts = [];
let finish;
const submission = new AssistantSubmission('/asistente-contacto', (endpoint, options) => {
    attempts.push({endpoint, options});
    return new Promise(resolve => { finish = resolve; });
});
const payload = {...answers, submission_token: 'same-token'};
const first = submission.submit(payload, 'csrf');
const second = submission.submit(payload, 'csrf');
assert.equal(first, second, 'Double clicks must share one request');
assert.equal(attempts.length, 1);
finish(success);
await first;
await submission.submit(payload, 'csrf');
assert.equal(attempts.length, 1, 'A saved form must never submit again');
assert.equal(attempts[0].options.headers['X-CSRF-TOKEN'], 'csrf');
assert.equal(attempts[0].options.credentials, 'same-origin');
assert.deepEqual(JSON.parse(attempts[0].options.body), payload);
let retry = 0;
const retryBodies = [];
const recovery = new AssistantSubmission('/asistente-contacto', async (endpoint, options) => {
    retryBodies.push(options.body);
    if (retry++ === 0) throw new Error('Network lost after sending');
    return success;
});
await assert.rejects(recovery.submit(payload, 'csrf'), failure => /sin duplic|duplicados/.test(failure.message));
await recovery.submit(payload, 'csrf');
assert.equal(retryBodies[0], retryBodies[1], 'Retries must keep the original idempotency token and answers');
for (const [status, message] of [[429, 'Espera antes'], [419, 'La sesión ha caducado'], [404, 'ya no está disponible'], [503, 'Reintenta']]) {
    const client = new AssistantSubmission('/asistente-contacto', async () => ({ok: false, status, json: async () => ({})}));
    await assert.rejects(client.submit(payload, 'csrf'), error => error.message.includes(message));
    assert.equal(client.saved, null);
}
const invalid = new AssistantSubmission('/asistente-contacto', async () => ({ok: false, status: 422, json: async () => ({errors: {name: ['Nombre inválido']}})}));
await assert.rejects(invalid.submit(payload, 'csrf'), error => error.errors.name[0] === 'Nombre inválido');
const injected = new AssistantSubmission('/asistente-contacto', async () => ({ok: true, status: 202, json: async () => ({whatsapp_url: 'https://evil.test'})}));
await assert.rejects(injected.submit(payload, 'csrf'), error => /enlace/.test(error.message));
assert.equal(injected.saved, null);
const countries = [
    {code: 'BO', name: 'Bolivia', dial_code: '591', lengths: [8], national_prefix: '', main: true},
    {code: 'ES', name: 'España', dial_code: '34', lengths: [9], national_prefix: '', main: true},
    {code: 'GB', name: 'Reino Unido', dial_code: '44', lengths: [10], national_prefix: '0', main: true},
    {code: 'IT', name: 'Italia', dial_code: '39', lengths: [10], national_prefix: '', main: true},
    {code: 'US', name: 'Estados Unidos', dial_code: '1', lengths: [10], national_prefix: '1', main: true},
    {code: 'CA', name: 'Canadá', dial_code: '1', lengths: [10], national_prefix: '1', main: false},
];
assert.equal(suggestedCountry(['es-BO'], countries, 'ES'), 'BO');
assert.equal(suggestedCountry(['zh-Hant-TW', 'en-CA'], countries, 'BO'), 'CA');
assert.equal(suggestedCountry(['es'], countries, 'BO'), 'BO');
assert.equal(suggestedCountry(['invalid_tag'], countries, 'BO'), 'BO');
assert.equal(nationalPhone('7777 7777', 'BO', countries), '+59177777777');
assert.equal(nationalPhone('07700 900123', 'GB', countries), '+447700900123');
assert.equal(nationalPhone('02 1234 5678', 'IT', countries), '+390212345678');
assert.equal(nationalPhone('+34 611123456', 'BO', countries), null);
assert.equal(nationalPhone('59177777777', 'BO', countries), null);
assert.equal(nationalPhone('7777777', 'BO', countries), null);
assert.equal(countryForPhone('0034 611123456', countries, 'BO').code, 'ES');
assert.equal(countryForPhone('+14165550123', countries, 'CA').code, 'CA');
assert.equal(countryForPhone('+14165550123', countries, 'BO').code, 'US');
assert.equal(countryForPhone('+99912345678', countries, 'BO'), null);
assert.deepEqual(matchingCountries('espana', countries).map(item => item.code), ['ES']);
assert.deepEqual(matchingCountries('+591', countries).map(item => item.code), ['BO']);
assert.deepEqual(matchingCountries('canada', countries).map(item => item.code), ['CA']);
const originalFetch = globalThis.fetch;
try {
    globalThis.fetch = function () { assert.equal(this, globalThis, 'Native fetch requires the global receiver'); return Promise.resolve(success); };
    const nativeClient = new AssistantSubmission('/asistente-contacto');
    await nativeClient.submit(payload, 'csrf');
    assert.equal(nativeClient.saved.whatsapp_url, url);
    globalThis.fetch = function (endpoint, options) {
        assert.equal(this, globalThis);
        assert.equal(options.credentials, 'same-origin');
        return Promise.resolve({ok: true, json: async () => ({enabled: true, submission_token: 'renewed'})});
    };
    assert.equal((await loadAssistantConfiguration('/asistente-contacto?fields=1')).submission_token, 'renewed');
} finally { globalThis.fetch = originalFetch; }
const throttled = new AssistantSubmission('/asistente-contacto', async () => ({ok: false, status: 429, headers: {get: () => '3600'}, json: async () => ({})}));
await assert.rejects(throttled.submit(payload, 'csrf'), error => error.kind === 'rate_limit' && error.message.includes('60 minutos'));
const timeoutSchedule = globalThis.setTimeout;
const timeoutCancel = globalThis.clearTimeout;
try {
    let abortRequest;
    globalThis.setTimeout = callback => { abortRequest = callback; return 1; };
    globalThis.clearTimeout = () => {};
    const slow = new AssistantSubmission('/asistente-contacto', (endpoint, options) => new Promise((resolve, reject) => {
        options.signal.addEventListener('abort', () => reject(new Error('aborted')));
    }));
    const pending = slow.submit(payload, 'csrf');
    abortRequest();
    await assert.rejects(pending, error => error.kind === 'timeout');
    assert.equal(slow.saved, null);
    const configurationRequest = loadAssistantConfiguration('/asistente-contacto?fields=1', (endpoint, options) => new Promise((resolve, reject) => {
        options.signal.addEventListener('abort', () => reject(new Error('configuration aborted')));
    }));
    abortRequest();
    await assert.rejects(configurationRequest, /configuration aborted/);
} finally {
    globalThis.setTimeout = timeoutSchedule;
    globalThis.clearTimeout = timeoutCancel;
}
// Use a controlled clock: animation cancellation must never emit stale prompts.
let timerId = 0;
const timers = new Map();
const typing = new ChatTyping(callback => { timers.set(++timerId, callback); return timerId; }, id => timers.delete(id));
const flush = () => { while (timers.size) { const [id, callback] = timers.entries().next().value; timers.delete(id); callback(); } };
let rendered = [];
let announcements = [];
typing.start('Hola 👋', text => rendered.push(text), () => announcements.push('Hola 👋'));
assert.equal(rendered[0], '');
assert.deepEqual(announcements, []);
flush();
assert.equal(rendered.at(-1), 'Hola 👋');
assert.deepEqual(announcements, ['Hola 👋']);
assert.ok(rendered.length > 2, 'Typing must progressively reveal text');
typing.start('Pregunta anterior', text => rendered.push(text), () => announcements.push('stale'));
typing.start('Pregunta actual', text => rendered.push(text), () => announcements.push('current'));
flush();
assert.equal(rendered.at(-1), 'Pregunta actual');
assert.equal(announcements.includes('stale'), false);
typing.start('Cerrar', text => rendered.push(text), () => announcements.push('closed'));
typing.complete();
assert.equal(rendered.at(-1), 'Cerrar');
assert.equal(timers.size, 0);
typing.start('Sin animación', text => rendered.push(text), () => announcements.push('reduced'), true);
assert.equal(rendered.at(-1), 'Sin animación');
assert.equal(timers.size, 0);
typing.start('Descartar', text => rendered.push(text), () => announcements.push('discarded'));
typing.stop();
flush();
assert.equal(announcements.includes('discarded'), false);
const nativeSchedule = globalThis.setTimeout;
const nativeCancel = globalThis.clearTimeout;
try {
    globalThis.setTimeout = function (callback) { assert.equal(this, globalThis, 'Browser timers require the global receiver'); timers.set(++timerId, callback); return timerId; };
    globalThis.clearTimeout = function (id) { assert.equal(this, globalThis, 'Browser timers require the global receiver'); timers.delete(id); };
    const browserTyping = new ChatTyping();
    browserTyping.start('Navegador', text => rendered.push(text), () => announcements.push('browser'));
    flush();
    assert.equal(rendered.at(-1), 'Navegador');
} finally {
    globalThis.setTimeout = nativeSchedule;
    globalThis.clearTimeout = nativeCancel;
}
process.stdout.write('Client flow, field validation, URL safety, CSRF, retry and double-click scenarios passed');

const custom = {id: 'f_aaaaaaaaaaaaaaaa', type: 'text', required: true, min_length: 2, max_length: 4};
assert.ok(configuredFieldError(custom, '', configuration));
assert.equal(configuredFieldError({...custom, required: false}, '', configuration), '');
assert.equal(configuredFieldError(custom, '李明', configuration), '');
for (const answer of ['a', 'abcde', '<b>']) assert.ok(configuredFieldError(custom, answer, configuration));
const numeric = {...custom, type: 'number', min_value: -2, max_value: 10};
assert.equal(configuredFieldError(numeric, '0', configuration), '');
for (const answer of ['-3', '11', 'NaN', '1e2', '1.12345']) assert.ok(configuredFieldError(numeric, answer, configuration));
const date = {...custom, type: 'date', min_date: '2026-01-01', max_date: '2026-12-31'};
assert.equal(configuredFieldError(date, '2026-06-15', configuration), '');
for (const answer of ['2026-02-30', '2025-12-31', '2027-01-01', '15/06/2026']) assert.ok(configuredFieldError(date, answer, configuration));
const options = {...custom, type: 'choice', options: [{value: 'o_aaaaaaaaaaaa', label: 'Primera'}]};
assert.equal(configuredFieldError(options, 'o_aaaaaaaaaaaa', configuration), '');
assert.ok(configuredFieldError(options, 'unknown', configuration));
assert.equal(configuredFieldError({...options, type: 'multiple'}, ['o_aaaaaaaaaaaa'], configuration), '');
for (const answer of [[], ['unknown'], ['o_aaaaaaaaaaaa', 'o_aaaaaaaaaaaa']]) assert.ok(configuredFieldError({...options, type: 'multiple'}, answer, configuration));
