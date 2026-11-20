/** Named, bounded public references. Stored unresolved selections are never pruned by a lookup. */
export function createPopupTargetPicker(options) {
    const fetcher = options.fetcher || globalThis.fetch;
    const types = (options.types || []).filter(type => options.kind === 'pages' ? type.type === 'page' : type.type !== 'page');
    const storedType = Object.keys(options.target.taxonomies || {}).find(key => types.some(type => type.type === key));
    const controllers = new Set();
    let searchController = null;
    let generation = 0;
    let searchVersion = 0;
    let context = '';
    const cancel = () => { for (const controller of controllers) controller.abort(); controllers.clear(); };
    return {
        target: options.target,
        kind: options.kind,
        endpoint: options.endpoint,
        types,
        type: options.kind === 'pages' ? 'page' : storedType || types.find(type => /category|categoría|taxonomy|etiqueta|tag/i.test(type.type + ' ' + type.label))?.type || types[0]?.type || '',
        market: '', locale: '', search: '', items: [], labels: {}, nextOffset: null, loading: false, resolving: false, error: '',
        get selectedGroups() {
            return this.kind === 'pages' ? { page: this.target.pages || [] } : this.target.taxonomies || {};
        },
        get selections() {
            return Object.entries(this.selectedGroups).flatMap(([type, ids]) => ids.map(id => ({ type, id, key: `${type}:${id}`, title: this.labels[`${type}:${id}`] || '', typeLabel: this.typeLabel(type) })));
        },
        typeLabel(type) { return this.types.find(item => item.type === type)?.label || `Tipo guardado no disponible: ${type}`; },
        unavailableTypes() { return Object.keys(this.selectedGroups).filter(type => !this.types.some(item => item.type === type)); },
        selected(item) { return (this.selectedGroups[this.type] || []).some(id => String(id) === String(item.id)); },
        toggle(item) {
            const ids = [...(this.selectedGroups[this.type] || [])];
            const index = ids.findIndex(id => String(id) === String(item.id));
            if (index === -1) ids.push(this.kind === 'pages' ? Number(item.id) : String(item.id));
            else ids.splice(index, 1);
            this.store(this.type, ids);
            this.labels[`${this.type}:${item.id}`] = item.title;
        },
        remove(type, id) { this.store(type, (this.selectedGroups[type] || []).filter(value => String(value) !== String(id))); },
        store(type, ids) {
            if (this.kind === 'pages') this.target.pages = ids;
            else {
                this.target.taxonomies ||= {};
                if (ids.length) this.target.taxonomies[type] = ids;
                else delete this.target.taxonomies[type];
            }
        },
        url(extra = {}) {
            const url = new URL(this.endpoint, globalThis.location?.origin || 'http://localhost');
            const query = { type: this.type, market: this.market, locale: this.locale, ...extra };
            for (const [key, value] of Object.entries(query)) {
                if (Array.isArray(value)) for (const entry of value) url.searchParams.append(`${key}[]`, String(entry));
                else if (value !== null && value !== undefined) url.searchParams.set(key, String(value));
            }
            return url.href;
        },
        async setContext(locale, market) {
            const key = `${locale}:${market}:${this.type}`;
            if (context === key) return;
            context = key; generation++; cancel();
            this.locale = locale; this.market = market; this.items = []; this.labels = {}; this.nextOffset = null;
            await Promise.all([this.load(), this.resolveSelected()]);
        },
        async changeType() { context = ''; await this.setContext(this.locale, this.market); },
        async searchChanged() { this.items = []; this.nextOffset = null; await this.load(); },
        async load(offset = 0, append = false) {
            if (!this.market || !this.locale || !this.types.some(type => type.type === this.type)) { this.loading = false; return; }
            searchController?.abort();
            const controller = new AbortController(); controllers.add(controller); searchController = controller;
            const current = generation, request = ++searchVersion;
            this.loading = true; this.error = '';
            try {
                const response = await fetcher(this.url({ search: this.search, offset, limit: 20 }), { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal });
                const body = await response.json();
                if (current !== generation || request !== searchVersion) return;
                if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || 'No se pudieron consultar las referencias.');
                this.items = append ? [...this.items, ...body.items.filter(item => !this.items.some(existing => String(existing.id) === String(item.id)))] : body.items;
                this.nextOffset = body.next_offset;
                for (const item of body.items) this.labels[`${this.type}:${item.id}`] = item.title;
            } catch (error) {
                if (error.name !== 'AbortError' && current === generation && request === searchVersion) { this.error = error.message; this.items = []; this.nextOffset = null; }
            } finally {
                controllers.delete(controller);
                if (current === generation && request === searchVersion) this.loading = false;
            }
        },
        async resolveSelected() {
            const current = generation;
            this.resolving = true;
            try {
                for (const [type, ids] of Object.entries(this.selectedGroups)) {
                    if (!this.types.some(item => item.type === type)) continue;
                    for (let offset = 0; offset < ids.length; offset += 50) {
                        const controller = new AbortController(); controllers.add(controller);
                        try {
                            const response = await fetcher(this.url({ type, ids: ids.slice(offset, offset + 50), limit: 50 }), { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal });
                            const body = await response.json();
                            if (current !== generation) return;
                            if (response.ok) for (const item of body.items) this.labels[`${type}:${item.id}`] = item.title;
                        } catch (error) { if (error.name === 'AbortError') return; }
                        finally { controllers.delete(controller); }
                    }
                }
            } finally { if (current === generation) this.resolving = false; }
        },
        destroy() { generation++; cancel(); },
    };
}
