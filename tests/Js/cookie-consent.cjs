const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const html = fs.readFileSync(0, 'utf8');
const scripts = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)].map(match => match[1]);
const script = scripts.find(script => script.includes('__corporateConsentInitialized'));
assert.ok(script, 'The rendered consent script must be present');

function run(choice, statistics, allowed = true, marketingAllowed = false, marketing = false, advertisingCookies = false) {
    const writes = [];
    const events = [];
    const listeners = {};
    const document = {
        get cookie() { return '_ga=abc; _ga_ABC1234567=def; _gid=ghi; session=necessary' + (advertisingCookies ? '; _gcl_au=ads; _fbp=pixel' : ''); },
        set cookie(value) { writes.push(value); },
        addEventListener(type, listener) { listeners[type] = listener; },
    };
    const window = {gtag: (...args) => events.push(args)};
    const sandbox = {window, document, location: {hostname: 'www.corporate.test', pathname: '/blog/article'}};
    vm.runInNewContext(script.replace('const allowed = true;', `const allowed = ${allowed};`).replace('const marketingAllowed = false;', `const marketingAllowed = ${marketingAllowed};`), sandbox);
    assert.ok(listeners.submit, 'Preferences must remain changeable');
    if (choice) listeners.submit({target: {matches: () => true, elements: {statistics: {checked: statistics}, marketing: {checked: marketing}}}, submitter: {value: choice}});
    return {writes, events, window};
}

for (const [choice, statistics] of [['reject', true], ['save', false]]) {
    const result = run(choice, statistics);
    assert.equal(result.window['ga-disable-G-ABC1234567'], true);
    assert.equal(result.events[0][0], 'consent');
    assert.equal(result.events[0][1], 'update');
    assert.equal(result.events[0][2].analytics_storage, 'denied');
    assert.ok(result.writes.some(cookie => cookie.startsWith('_ga=')));
    assert.ok(result.writes.some(cookie => cookie.includes('domain=.corporate.test')));
    assert.ok(result.writes.some(cookie => cookie.includes('path=/blog')));
    assert.ok(result.writes.every(cookie => !cookie.startsWith('session=')));
}
for (const [choice, statistics] of [['accept', false], ['save', true]]) {
    const result = run(choice, statistics);
    assert.equal(result.events.length, 0);
    assert.equal(result.writes.length, 0);
}
const denied = run(null, false, false);
assert.equal(denied.window['ga-disable-G-ABC1234567'], true);
assert.ok(denied.writes.length > 0);
const marketingOnly = run(null, false, false, true, true, true);
assert.equal(marketingOnly.events[0][2].analytics_storage, 'denied');
assert.equal(marketingOnly.events[0][2].ad_storage, undefined);
assert.ok(marketingOnly.writes.every(cookie => !/^(_gcl_|_fbp=)/.test(cookie)));
const withdrewStatistics = run('save', false, true, true, true, true);
assert.equal(withdrewStatistics.events[0][2].analytics_storage, 'denied');
assert.equal(withdrewStatistics.events[0][2].ad_storage, undefined);
assert.ok(withdrewStatistics.writes.every(cookie => !/^(_gcl_|_fbp=)/.test(cookie)));
const withdrewMarketing = run('save', true, true, true, false, true);
assert.equal(withdrewMarketing.events[0][2].ad_storage, 'denied');
assert.equal(withdrewMarketing.events[0][2].analytics_storage, undefined);
assert.ok(withdrewMarketing.writes.some(cookie => cookie.startsWith('_gcl_au=')));
assert.ok(withdrewMarketing.writes.every(cookie => !/^(_ga|_gid|session)=/.test(cookie)));
process.stdout.write('8 client consent scenarios passed');
