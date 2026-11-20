import assert from 'node:assert/strict';
import test from 'node:test';
import { createPopupRuntime, scheduleMatches, deviceFor, frequencyAllowed, provenanceMatches, recordImpression, sortPopups, bootPopups } from '../../.build/app/vendor/dbravoan/dbcms-module-popups/Resources/js/runtime.js';
import { createPopupTargetPicker } from '../../.build/app/vendor/dbravoan/dbcms-module-popups/Resources/js/target-picker.js';
import { createPopupEditor } from '../../.build/app/vendor/dbravoan/dbcms-module-popups/Resources/js/admin.js';

class CustomEvent extends Event {
    constructor(type, options = {}) { super(type); this.detail = options.detail; }
}
const popup = (id = 1, configuration = {}) => ({ id: `popup-${id}`, entityId: id, priority: 0, configuration: { triggers: [], frequency: { mode: 'page' }, ...configuration }, preview: false, unavailable: [] });
function environment() {
    let now = 0;
    let id = 0;
    const timers = new Map();
    const win = new EventTarget();
    const doc = new EventTarget();
    Object.assign(win, { CustomEvent, innerWidth: 1440, innerHeight: 1000, scrollY: 0, location: new URL('https://site.test/path?utm_source=fixture'),
        queueMicrotask, matchMedia: () => ({ matches: true }), setTimeout: (callback, delay) => { const key = ++id; timers.set(key, { at: now + delay, callback }); return key; }, clearTimeout: key => timers.delete(key),
    });
    Object.assign(doc, { referrer: 'https://source.test/article', documentElement: { scrollHeight: 3000 }, querySelector: () => null });
    const requests = [];
    win.addEventListener('cms-overlay-open', event => requests.push(event.detail));
    const emit = (target, type, detail = {}) => target.dispatchEvent(new CustomEvent(type, { detail }));
    const advance = async milliseconds => {
        now += milliseconds;
        for (const [key, timer] of [...timers]) if (timer.at <= now) { timers.delete(key); timer.callback(); }
        await Promise.resolve();
    };
    return { win, doc, requests, emit, advance, timers };
}

test('device and provenance restrictions use exact values without transmission', () => {
    assert.equal(deviceFor(767), 'mobile');
    assert.equal(deviceFor(768), 'tablet');
    assert.equal(deviceFor(1024), 'desktop');
    const location = new URL('https://site.test/?utm_source=campaign');
    assert.equal(provenanceMatches({ referrers: ['source.test'], utm: { utm_source: 'campaign' } }, location, 'https://source.test/a'), true);
    assert.equal(provenanceMatches({ referrers: ['source.test'] }, location, ''), false);
    assert.equal(provenanceMatches({ referrers: ['source.test'] }, location, 'https://evilsource.test'), false);
    assert.equal(provenanceMatches({ utm: { utm_source: 'other' } }, location), false);
});

test('page frequency and preview never access storage; persistent modes require the shared purpose', () => {
    let touches = 0;
    const win = { get localStorage() { touches++; throw Error('Forbidden storage'); }, get sessionStorage() { touches++; throw Error('Forbidden storage'); } };
    const seen = new Set();
    const plain = popup();
    assert.equal(frequencyAllowed(plain, win, seen), true);
    recordImpression(plain, win, seen);
    assert.equal(frequencyAllowed(plain, win, seen), false);
    const persistent = popup(2, { frequency: { mode: 'once', purpose: 'preferences' } });
    assert.equal(frequencyAllowed(persistent, win, seen), false);
    recordImpression({ ...persistent, preview: true }, win, seen);
    assert.equal(frequencyAllowed({ ...persistent, preview: true }, win, seen), true);
    assert.equal(touches, 0);
    win.dbcmsStorage = { allows: () => false };
    assert.equal(frequencyAllowed(persistent, win, seen), false);
    assert.equal(touches, 0);
});

test('authorized cooldown reads and writes only after an actual impression and rechecks withdrawal', () => {
    const values = new Map();
    let authorized = true;
    const win = { dbcmsStorage: { allows: purpose => authorized && purpose === 'preferences' }, localStorage: { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) } };
    const entry = popup(2, { frequency: { mode: 'cooldown', seconds: 30, purpose: 'preferences' } });
    assert.equal(frequencyAllowed(entry, win, new Set(), 1000), true);
    assert.equal(values.size, 0);
    recordImpression(entry, win, new Set(), 1000);
    assert.equal(frequencyAllowed(entry, win, new Set(), 1500), false);
    assert.equal(frequencyAllowed(entry, win, new Set(), 32000), true);
    authorized = false;
    assert.equal(frequencyAllowed(entry, win, new Set(), 32000), false);
});

test('trigger queue resolves by priority and id, counts only opened, and waits for close', async () => {
    const env = environment();
    const a = popup(3), b = { ...popup(1), priority: 5 }, c = { ...popup(2), priority: 5 };
    assert.deepEqual(sortPopups([a, c, b]).map(entry => entry.entityId), [1, 2, 3]);
    const seen = new Set();
    const runtime = createPopupRuntime(env.win, env.doc, [a, c, b], seen);
    runtime.queue(a); runtime.queue(c); runtime.queue(b);
    await Promise.resolve();
    assert.equal(env.requests[0].id, b.id);
    assert.equal(seen.size, 0);
    env.emit(env.win, 'cms-overlay-opened', { id: b.id });
    assert.equal(seen.has(b.id), true);
    runtime.drain();
    assert.equal(env.requests.length, 1);
    env.emit(env.win, 'cms-overlay-closed', { id: b.id });
    assert.equal(env.requests[1].id, c.id);
    runtime.destroy();
});

test('delay and scroll are OR, and another trigger cannot repeat an opened popup on the same page', async () => {
    const env = environment();
    const entry = popup(1, { triggers: [{ type: 'delay', seconds: 2 }, { type: 'scroll', percent: 50 }] });
    const runtime = createPopupRuntime(env.win, env.doc, [entry], new Set());
    env.win.scrollY = 1200;
    env.emit(env.win, 'scroll');
    await Promise.resolve();
    assert.equal(env.requests.length, 1);
    env.emit(env.win, 'cms-overlay-opened', { id: entry.id });
    env.emit(env.win, 'cms-overlay-closed', { id: entry.id });
    await env.advance(3000);
    assert.equal(env.requests.length, 1);
    runtime.destroy();
});

test('form-success trigger matches the submitted form and ignores submit intent alone', async () => {
    const env = environment();
    const runtime = createPopupRuntime(env.win, env.doc, [popup(1, { triggers: [{ type: 'form_success', form_slug: 'contact' }] })], new Set());
    env.emit(env.win, 'submit', { formSlug: 'contact' });
    env.emit(env.win, 'cms-form-submitted', { formSlug: 'other' });
    await Promise.resolve();
    assert.equal(env.requests.length, 0);
    env.emit(env.win, 'cms-form-submitted', { formSlug: 'contact' });
    await Promise.resolve();
    assert.equal(env.requests.length, 1);
    runtime.destroy();
});

test('inactivity resets on interaction and is cleaned up on navigation', async () => {
    const env = environment();
    const runtime = createPopupRuntime(env.win, env.doc, [popup(1, { triggers: [{ type: 'inactivity', seconds: 2 }] })], new Set());
    await env.advance(1000);
    env.emit(env.win, 'pointerdown');
    await env.advance(1000);
    assert.equal(env.requests.length, 0);
    await env.advance(1000);
    assert.equal(env.requests.length, 1);
    runtime.destroy();
    assert.equal(env.timers.size, 0);
});

test('preview installs no automatic trigger and never consumes frequency', async () => {
    const env = environment();
    const entry = { ...popup(1, { triggers: [{ type: 'delay', seconds: 0 }], frequency: { mode: 'once', purpose: 'preferences' } }), preview: true };
    const seen = new Set();
    const runtime = createPopupRuntime(env.win, env.doc, [entry], seen);
    await env.advance(1000);
    assert.equal(env.requests.length, 0);
    runtime.queue(entry);
    await Promise.resolve();
    env.emit(env.win, 'cms-overlay-opened', { id: entry.id });
    assert.equal(seen.size, 0);
    runtime.destroy();
});

test('absent or disabled module markup starts no runtime or browser listeners', () => {
    let listeners = 0;
    bootPopups({ addEventListener() { listeners++; } }, { querySelectorAll: () => [] });
    assert.equal(listeners, 0);
});

test('structured editor retains conditions and changes trigger-specific fields cleanly', () => {
    const editor = createPopupEditor({ configuration: { conditions: { include: { pages: [12], types: ['page'], taxonomies: { blog_category: ['4'] } } }, triggers: [{ type: 'scroll', percent: 20 }] }, locale: 'es' });
    assert.equal(editor.taxonomyText('include'), 'blog_category: 4');
    editor.setTaxonomies('exclude', 'blog_category: 9, 10');
    assert.deepEqual(editor.configuration.conditions.exclude.taxonomies, { blog_category: ['9', '10'] });
    editor.changeTrigger(0, 'form_success');
    assert.deepEqual(editor.configuration.triggers[0], { type: 'form_success', form_slug: '' });
    editor.setUtm('utm_source', 'campaign');
    editor.setUtm('utm_source', '');
    assert.deepEqual(editor.configuration.provenance.utm, {});
});

test('element visibility and explicit click triggers use their configured selector', async () => {
    const env = environment();
    let intersection;
    let observed;
    let disconnected = false;
    const target = {};
    env.doc.querySelector = selector => selector === '#offer' ? target : null;
    env.win.IntersectionObserver = class {
        constructor(callback) { intersection = callback; }
        observe(element) { observed = element; }
        disconnect() { disconnected = true; }
    };
    const runtime = createPopupRuntime(env.win, env.doc, [popup(1, { triggers: [{ type: 'element', selector: '#offer' }] }), popup(2, { triggers: [{ type: 'click', selector: '#action' }] })], new Set());
    assert.equal(observed, target);
    intersection([{ isIntersecting: true }]);
    await Promise.resolve();
    assert.equal(env.requests[0].id, 'popup-1');
    assert.equal(disconnected, true);
    const click = new CustomEvent('click');
    Object.defineProperty(click, 'target', { value: { closest: selector => selector === '#action' ? {} : null } });
    env.doc.dispatchEvent(click);
    await Promise.resolve();
    assert.equal(env.requests[1].id, 'popup-1'); // First blocked request is retained until core opens it.
    runtime.destroy();
});

test('desktop exit intent ignores internal movements and small touch viewports', async () => {
    const env = environment();
    const runtime = createPopupRuntime(env.win, env.doc, [popup(1, { triggers: [{ type: 'exit_intent' }] })], new Set());
    const move = (relatedTarget, clientY) => {
        const event = new Event('mouseout');
        Object.assign(event, { relatedTarget, clientY });
        env.doc.dispatchEvent(event);
    };
    move({}, -1);
    move(null, 20);
    env.win.innerWidth = 600;
    move(null, -1);
    await Promise.resolve();
    assert.equal(env.requests.length, 0);
    env.win.innerWidth = 1400;
    move(null, -1);
    await Promise.resolve();
    assert.equal(env.requests.length, 1);
    runtime.destroy();
});

test('a request blocked by privacy is retried on the shared ready notification', async () => {
    const env = environment();
    const seen = new Set();
    const entry = popup();
    const runtime = createPopupRuntime(env.win, env.doc, [entry], seen);
    runtime.queue(entry);
    await Promise.resolve();
    assert.equal(seen.size, 0);
    env.emit(env.win, 'cms-overlays-ready');
    assert.equal(env.requests.length, 2);
    env.emit(env.win, 'cms-overlay-opened', { id: entry.id });
    assert.equal(seen.size, 1);
    runtime.destroy();
});

test('an early trigger waits for the shared consent state before accessing optional storage', async () => {
    const env = environment();
    let allowed = false;
    let reads = 0;
    env.win.dbcmsStorage = { allows: () => allowed };
    Object.defineProperty(env.win, 'localStorage', { get() { reads++; return { getItem: () => null }; } });
    const entry = popup(1, { triggers: [{ type: 'delay', seconds: 0 }], frequency: { mode: 'once', purpose: 'preferences' } });
    const runtime = createPopupRuntime(env.win, env.doc, [entry], new Set());
    await env.advance(0);
    assert.equal(reads, 0);
    assert.equal(env.requests.length, 0);
    allowed = true;
    env.emit(env.win, 'cms-overlays-ready');
    assert.equal(reads, 1);
    assert.equal(env.requests[0].id, entry.id);
    runtime.destroy();
});

test('public-target picker searches bounded named pages and keeps unresolved selections until explicit removal', async () => {
    const target = { pages: [99] };
    const requests = [];
    const picker = createPopupTargetPicker({ kind: 'pages', target, endpoint: '/admin/popups/targets', types: [{ type: 'page', label: 'Página · Núcleo' }], fetcher: async (url, options) => {
        const query = new URL(url).searchParams; requests.push({ query, options });
        if (query.has('ids[]')) return { ok: true, json: async () => ({ items: [], next_offset: null }) };
        return { ok: true, json: async () => query.get('offset') === '20' ? { items: [{ id: 2, title: 'Contacto' }], next_offset: null } : { items: [{ id: 1, title: 'Inicio' }], next_offset: 20 } };
    } });
    await picker.setContext('es', 'es');
    assert.deepEqual(target.pages, [99]);
    assert.equal(picker.selections[0].title, '');
    assert.equal(picker.items[0].title, 'Inicio');
    assert.equal(requests[0].query.get('limit'), '20');
    assert.equal(requests[0].options.credentials, 'same-origin');
    picker.toggle(picker.items[0]);
    assert.deepEqual(target.pages, [99, 1]);
    await picker.load(picker.nextOffset, true);
    assert.deepEqual(picker.items.map(item => item.title), ['Inicio', 'Contacto']);
    assert.equal(picker.nextOffset, null);
    picker.remove('page', 99);
    assert.deepEqual(target.pages, [1]);
    picker.destroy();
});

test('taxonomy picker uses owner names, keeps missing owners, and never requests their records', async () => {
    const target = { taxonomies: { blog_category: ['7'], missing_owner: ['88'] } };
    const requestedTypes = [];
    const picker = createPopupTargetPicker({ kind: 'taxonomies', target, endpoint: '/targets', types: [{ type: 'page', label: 'Página' }, { type: 'blog_category', label: 'Categoría · Blog' }], fetcher: async url => {
        const query = new URL(url).searchParams; requestedTypes.push(query.get('type'));
        return { ok: true, json: async () => ({ items: query.get('locale') === 'es' ? [{ id: 7, title: 'Noticias' }] : [], next_offset: null }) };
    } });
    await picker.setContext('es', 'es');
    assert.equal(picker.selections.find(item => item.id === '7').title, 'Noticias');
    assert.equal(picker.typeLabel('blog_category'), 'Categoría · Blog');
    assert.deepEqual(picker.unavailableTypes(), ['missing_owner']);
    assert.ok(requestedTypes.every(type => type === 'blog_category'));
    await picker.setContext('en', 'mx');
    assert.deepEqual(target.taxonomies, { blog_category: ['7'], missing_owner: ['88'] });
    assert.equal(picker.selections[0].title, '');
    picker.remove('blog_category', '7');
    assert.deepEqual(target.taxonomies, { missing_owner: ['88'] });
    picker.destroy();
});

test('stale public-target responses cannot replace the current market and language choices', async () => {
    let resolveOld;
    const picker = createPopupTargetPicker({ kind: 'pages', target: { pages: [] }, endpoint: '/targets', types: [{ type: 'page', label: 'Página' }], fetcher: url => {
        const query = new URL(url).searchParams;
        if (query.get('locale') === 'es') return new Promise(resolve => { resolveOld = resolve; });
        return Promise.resolve({ ok: true, json: async () => ({ items: [{ id: 2, title: 'English page' }], next_offset: null }) });
    } });
    const old = picker.setContext('es', 'es');
    await picker.setContext('en', 'mx');
    resolveOld({ ok: true, json: async () => ({ items: [{ id: 1, title: 'Antigua respuesta' }], next_offset: null }) });
    await old;
    assert.deepEqual(picker.items.map(item => item.title), ['English page']);
    assert.equal(picker.market, 'mx');
    picker.destroy();
});

test('reference context follows market languages while preserving the content locale', () => {
    const editor = createPopupEditor({ locale: 'en', referenceMarkets: [
        { code: 'es', default_locale: 'es', languages: [{ code: 'es', name: 'Español' }, { code: 'en', name: 'English' }] },
        { code: 'mx', default_locale: 'es', languages: [{ code: 'es', name: 'Español' }] },
    ] });
    assert.equal(editor.referenceLocale, 'en');
    editor.setReferenceMarket('mx');
    assert.equal(editor.referenceLocale, 'es');
    assert.equal(editor.activeLocale, 'en');
});

test('the popup schedule is checked again before a delayed or queued opening, with an exclusive end', async () => {
    assert.equal(scheduleMatches({ starts: 1, ends: 2 }, 1999), true);
    assert.equal(scheduleMatches({ starts: 1, ends: 2 }, 2000), false);
    assert.equal(scheduleMatches({ ends: Number.NaN }, 1000), false);
    const env = environment();
    const entry = { ...popup(), schedule: { ends: Math.floor(Date.now() / 1000) - 1 } };
    const runtime = createPopupRuntime(env.win, env.doc, [entry], new Set());
    runtime.queue(entry);
    await Promise.resolve();
    assert.equal(env.requests.length, 0);
    runtime.destroy();
});
