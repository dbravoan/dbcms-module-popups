<div class="space-y-3 border border-[color:var(--admin-border)] rounded p-3"
     x-data="popupTargetPicker({ target: configuration.conditions.{{ $conditionKind }}, kind: @js($pickerKind), endpoint: @js(route('admin.popups.targets')), types: @js($popupTargetTypes) })"
     x-effect="setContext(referenceLocale, referenceMarket)">
    <h3 class="font-bold">{{ $pickerKind === 'pages' ? 'Páginas' : 'Referencias de taxonomía' }}</h3>
    @if($pickerKind !== 'pages')
        <label class="block space-y-1"><span>Tipo de taxonomía pública</span>
            <select x-model="type" @change="changeType()" class="admin-control w-full"><option value="">Seleccionar tipo…</option>
                <template x-for="entry in types" :key="entry.type"><option :value="entry.type" x-text="entry.label"></option></template>
                <template x-for="key in unavailableTypes()" :key="key"><option :value="key" x-text="'No disponible: ' + key"></option></template>
            </select>
        </label>
    @endif
    <label class="block space-y-1"><span>Buscar por nombre</span><input type="search" maxlength="200" x-model="search" @input.debounce.300ms="searchChanged()" class="admin-control w-full" placeholder="Escribe un nombre…"></label>
    <p x-show="error" x-text="error" role="alert" class="text-sm"></p>
    <p role="status" class="text-sm" x-text="loading ? 'Consultando referencias públicas…' : items.length ? items.length + ' resultados cargados.' : 'Sin resultados en este contexto.'"></p>
    <ul class="space-y-2 max-h-60 overflow-auto" :aria-busy="loading">
        <template x-for="item in items" :key="item.id"><li><label class="flex items-start gap-2"><input type="checkbox" :checked="selected(item)" @change="toggle(item)"><span x-text="item.title"></span></label></li></template>
    </ul>
    <p x-show="!loading && !error && !items.length" class="text-sm text-[color:var(--admin-muted)]">No hay referencias publicadas en este idioma y mercado.</p>
    <button type="button" x-show="nextOffset !== null" :disabled="loading" @click="load(nextOffset, true)" class="underline">Cargar más resultados</button>
    <div class="space-y-2">
        <h4 class="text-sm font-bold">Selección guardada</h4>
        <p x-show="resolving" role="status" class="text-sm">Comprobando nombres guardados…</p>
        <p x-show="!selections.length" class="text-sm text-[color:var(--admin-muted)]">Sin restricción para este grupo.</p>
        <ul class="space-y-2">
            <template x-for="selection in selections" :key="selection.key"><li class="flex items-start justify-between gap-3 text-sm">
                <span><span x-text="selection.title || ('Referencia guardada sin resolver en este contexto · ID ' + selection.id)"></span><small class="block text-[color:var(--admin-muted)]" x-text="selection.typeLabel"></small></span>
                <button type="button" @click="remove(selection.type, selection.id)" class="underline" :aria-label="'Retirar ' + (selection.title || selection.id)">Retirar</button>
            </li></template>
        </ul>
    </div>
</div>
