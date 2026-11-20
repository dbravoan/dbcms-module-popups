import { createPopupTargetPicker } from './target-picker.js';

export function createPopupEditor(initial = {}) {
    const configuration = structuredClone(initial.configuration || {});
    configuration.conditions ||= {};
    for (const kind of ['include', 'exclude']) configuration.conditions[kind] ||= { pages: [], types: [], taxonomies: {} };
    configuration.triggers ||= [{ type: 'delay', seconds: 3 }];
    configuration.frequency ||= { mode: 'page', purpose: 'preferences' };
    configuration.frequency.purpose ||= 'preferences';
    configuration.auth ||= 'any';
    configuration.locales ||= [];
    configuration.devices ||= [];
    configuration.provenance ||= { referrers: [], utm: {} };
    configuration.provenance.utm ||= {};
    const referenceMarkets = initial.referenceMarkets || [];
    const market = referenceMarkets.find(market => market.is_default) || referenceMarkets[0];
    return {
        configuration,
        referenceMarkets,
        referenceMarket: market?.code || '',
        referenceLocale: market?.languages?.some(language => language.code === initial.locale) ? initial.locale : market?.default_locale || market?.languages?.[0]?.code || initial.locale,
        contentTypes: initial.contentTypes || {},
        referenceLanguages() { return this.referenceMarkets.find(market => market.code === this.referenceMarket)?.languages || []; },
        setReferenceMarket(code) {
            this.referenceMarket = code;
            if (!this.referenceLanguages().some(language => language.code === this.referenceLocale)) this.referenceLocale = this.referenceLanguages()[0]?.code || '';
        },
        unavailableContentTypes(kind) { return (this.configuration.conditions[kind].types || []).filter(type => !Object.hasOwn(this.contentTypes, type)); },
        activeLocale: initial.locale,
        switchLocale(locale) {
            this.activeLocale = locale;
            window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale } }));
        },
        csv(value) { return value.split(',').map(part => part.trim()).filter(Boolean); },
        pages(value) { return this.csv(value).map(Number); },
        taxonomyText(kind) {
            return Object.entries(this.configuration.conditions[kind].taxonomies || {}).map(([key, values]) => `${key}: ${values.join(', ')}`).join('\n');
        },
        setTaxonomies(kind, text) {
            const result = {};
            for (const line of text.split('\n').filter(line => line.trim())) {
                const colon = line.indexOf(':');
                if (colon === -1) { result[line.trim()] = []; continue; }
                result[line.slice(0, colon).trim()] = this.csv(line.slice(colon + 1));
            }
            this.configuration.conditions[kind].taxonomies = result;
        },
        setUtm(key, value) {
            if (value.trim()) this.configuration.provenance.utm[key] = value.trim();
            else delete this.configuration.provenance.utm[key];
        },
        addTrigger() { this.configuration.triggers.push({ type: 'delay', seconds: 3 }); },
        changeTrigger(index, type) {
            this.configuration.triggers[index] = {
                type,
                ...(['delay', 'inactivity'].includes(type) ? { seconds: 3 } : {}),
                ...(type === 'scroll' ? { percent: 50 } : {}),
                ...(['element', 'click'].includes(type) ? { selector: '' } : {}),
                ...(type === 'form_success' ? { form_slug: '' } : {}),
            };
        },
    };
}

export default function registerPopupsAdmin(Alpine) {
    Alpine.data('popupEditor', createPopupEditor);
    Alpine.data('popupTargetPicker', createPopupTargetPicker);
}
