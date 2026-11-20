const YEAR = 365 * 24 * 60 * 60 * 1000;
const memory = new Set();
const booted = new WeakSet();

export function scheduleMatches(schedule = {}, now = Date.now()) {
    return (schedule.starts == null || (Number.isFinite(schedule.starts) && now >= schedule.starts * 1000))
        && (schedule.ends == null || (Number.isFinite(schedule.ends) && now < schedule.ends * 1000));
}

export function deviceFor(width) {
    return width >= 1024 ? 'desktop' : width >= 768 ? 'tablet' : 'mobile';
}

export function provenanceMatches(rules = {}, location, referrer = '') {
    if (rules.referrers?.length) {
        let hostname = '';
        try { hostname = new URL(referrer).hostname.toLowerCase(); } catch { /* Direct visits have no referrer. */ }
        if (!rules.referrers.some(host => host.toLowerCase() === hostname)) return false;
    }
    const query = new URL(location.href).searchParams;
    return Object.entries(rules.utm || {}).every(([key, value]) => query.get(key) === value);
}

export function frequencyAllowed(popup, win, seen = memory, now = Date.now()) {
    if (popup.preview) return true;
    if (seen.has(popup.id)) return false;
    const frequency = popup.configuration?.frequency || { mode: 'page' };
    if (frequency.mode === 'page') return true;
    // No storage object is accessed before the shared authorization decision.
    if (!frequency.purpose || win.dbcmsStorage?.allows(frequency.purpose) !== true) return false;
    try {
        const storage = frequency.mode === 'session' ? win.sessionStorage : win.localStorage;
        const last = Number(storage.getItem(`dbcms.popup.v1.${popup.entityId}`));
        if (!last) return true;
        const lifetime = frequency.mode === 'cooldown' ? Number(frequency.seconds) * 1000 : YEAR;
        return now - last >= lifetime;
    } catch { return false; }
}

export function recordImpression(popup, win, seen = memory, now = Date.now()) {
    if (popup.preview) return;
    seen.add(popup.id);
    const frequency = popup.configuration?.frequency || { mode: 'page' };
    if (frequency.mode === 'page' || win.dbcmsStorage?.allows(frequency.purpose) !== true) return;
    try {
        const storage = frequency.mode === 'session' ? win.sessionStorage : win.localStorage;
        storage.setItem(`dbcms.popup.v1.${popup.entityId}`, String(now));
    } catch { /* The in-memory impression still prevents a second opening on this page. */ }
}

export function sortPopups(popups) {
    return [...popups].sort((a, b) => (b.priority - a.priority) || (a.entityId - b.entityId));
}

/** Trigger controller only. Focus, Escape, dialog exclusivity and scroll belong to core. */
export function createPopupRuntime(win, doc, popups, seen = memory) {
    const entries = sortPopups(popups);
    const pending = new Map();
    const cleanups = [];
    let active = null;
    let scheduled = false;
    let destroyed = false;
    const listen = (target, name, callback, options) => {
        target.addEventListener(name, callback, options);
        cleanups.push(() => target.removeEventListener(name, callback, options));
    };
    const timer = (callback, delay) => {
        const id = win.setTimeout(callback, delay);
        cleanups.push(() => win.clearTimeout(id));
        return id;
    };
    const contextEligible = popup => popup.preview || (
        !popup.unavailable?.length
        && scheduleMatches(popup.schedule)
        && (!popup.configuration.devices?.length || popup.configuration.devices.includes(deviceFor(win.innerWidth)))
        && provenanceMatches(popup.configuration.provenance, win.location, doc.referrer)
    );
    const drain = () => {
        scheduled = false;
        if (destroyed || active) return;
        for (const popup of sortPopups([...pending.values()])) {
            if (!contextEligible(popup) || (!popup.preview && seen.has(popup.id))) { pending.delete(popup.id); continue; }
            if (!frequencyAllowed(popup, win, seen)) continue;
            win.dispatchEvent(new win.CustomEvent('cms-overlay-open', {
                detail: { id: popup.id, automatic: !popup.preview, priority: popup.priority },
            }));
            // An unopened request remains pending until core is ready (e.g. privacy closes).
            break;
        }
    };
    const queue = popup => {
        if (destroyed || !contextEligible(popup) || (!popup.preview && seen.has(popup.id))) return;
        pending.set(popup.id, popup);
        if (!scheduled) { scheduled = true; win.queueMicrotask(drain); }
    };
    listen(win, 'cms-overlay-opened', event => {
        const popup = entries.find(entry => entry.id === event.detail?.id);
        if (!popup) return;
        active = popup.id;
        pending.delete(popup.id);
        recordImpression(popup, win, seen);
    });
    listen(win, 'cms-overlay-closed', event => {
        if (event.detail?.id === active) active = null;
        drain();
    });
    listen(win, 'cms-overlays-ready', drain);
    listen(win, 'pagehide', () => destroy(), { once: true });
    for (const popup of entries) {
        if (popup.preview) {
            listen(doc, 'click', event => {
                const target = event.target?.closest?.('[data-popup-simulate]');
                if (target?.dataset.popupSimulate === popup.id) queue(popup);
            });
            continue;
        }
        for (const trigger of popup.configuration.triggers || []) {
            const fire = () => queue(popup);
            switch (trigger.type) {
                case 'delay': timer(fire, Math.max(0, Number(trigger.seconds)) * 1000); break;
                case 'scroll': {
                    const onScroll = () => {
                        const maximum = Math.max(0, doc.documentElement.scrollHeight - win.innerHeight);
                        if (maximum > 0 && (win.scrollY / maximum) * 100 >= Number(trigger.percent)) fire();
                    };
                    listen(win, 'scroll', onScroll, { passive: true });
                    onScroll();
                    break;
                }
                case 'element': {
                    let target;
                    try { target = doc.querySelector(trigger.selector); } catch { break; }
                    if (!target || !win.IntersectionObserver) break;
                    const observer = new win.IntersectionObserver(records => {
                        if (records.some(record => record.isIntersecting)) { fire(); observer.disconnect(); }
                    });
                    observer.observe(target);
                    cleanups.push(() => observer.disconnect());
                    break;
                }
                case 'click':
                    listen(doc, 'click', event => {
                        try { if (event.target?.closest?.(trigger.selector)) fire(); } catch { /* Invalid selectors stay inert. */ }
                    });
                    break;
                case 'inactivity': {
                    let inactivity = win.setTimeout(fire, Number(trigger.seconds) * 1000);
                    cleanups.push(() => win.clearTimeout(inactivity));
                    const reset = () => { win.clearTimeout(inactivity); inactivity = win.setTimeout(fire, Number(trigger.seconds) * 1000); };
                    for (const name of ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart']) listen(win, name, reset, { passive: true });
                    break;
                }
                case 'exit_intent':
                    listen(doc, 'mouseout', event => {
                        if (deviceFor(win.innerWidth) === 'desktop' && win.matchMedia('(pointer: fine)').matches
                            && !event.relatedTarget && event.clientY <= 0) fire();
                    });
                    break;
                case 'form_success':
                    listen(win, 'cms-form-submitted', event => {
                        const slug = event.detail?.formSlug ?? event.detail?.form_slug ?? event.detail?.slug;
                        if (!trigger.form_slug || slug === trigger.form_slug) fire();
                    });
                    break;
            }
        }
    }
    function destroy() {
        destroyed = true;
        for (const cleanup of cleanups) cleanup();
        pending.clear();
    }
    return { queue, drain, destroy };
}

export function bootPopups(win, doc) {
    for (const root of doc.querySelectorAll('[data-cms-popups]')) {
        if (booted.has(root)) continue;
        booted.add(root);
        const popups = [];
        for (const data of root.querySelectorAll('[data-popup-config]')) {
            try {
                const popup = JSON.parse(data.textContent);
                if (typeof popup.id === 'string' && popup.configuration) popups.push(popup);
            } catch { /* Invalid server markup is not executable configuration. */ }
        }
        if (popups.length) createPopupRuntime(win, doc, popups);
    }
}
