<form method="POST" action="{{ $popup ? route('admin.popups.update', $popup) : route('admin.popups.store') }}"
      data-cms-translation-editor class="space-y-6" x-data="popupEditor({ configuration: @js(is_string(old('configuration')) ? (json_decode(old('configuration'), true) ?: []) : ($popup?->configuration ?? [])), locale: @js($languages->firstWhere('is_default', true)?->code ?? $languages->first()?->code), referenceMarkets: @js($popupReferenceMarkets), contentTypes: @js($popupTypes) })"
      x-on:locale-tab-change="switchLocale($event.detail.locale)">
    @csrf
    @if($popup) @method('PUT') @endif
    <input type="hidden" name="configuration" :value="JSON.stringify(configuration)">
    @if($errors->any())
        <x-admin.form-card><ul role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></x-admin.form-card>
    @endif
    @if(($popup?->getAttributes()['pending_revision_id'] ?? null))
        <p role="status">Estás editando una revisión pendiente. El contenido público se conserva hasta su aprobación.</p>
    @endif
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <x-admin.form-card class="space-y-4">
                <h2 class="font-bold text-lg">Contenido</h2>
                <x-admin.locale-tabs :languages="$languages" :active="$languages->firstWhere('is_default', true)?->code ?? $languages->first()?->code" group="popup-form" />
                @foreach($languages as $language)
                    <div x-show="activeLocale === @js($language->code)">
                        <label class="admin-field-label" for="popup-title-{{ $language->code }}">Título accesible · {{ $language->name }}</label>
                        <input id="popup-title-{{ $language->code }}" name="translations[{{ $language->code }}][title]" maxlength="255" class="admin-control w-full"
                               value="{{ old('translations.'.$language->code.'.title', $popup?->translations->whereNull('market_id')->firstWhere('locale', $language->code)?->title ?? '') }}">
                    </div>
                @endforeach
                <p class="text-sm text-[color:var(--admin-muted)]">El título identifica la ventana para lectores de pantalla. El contenido usa el mismo gestor de bloques que las páginas.</p>
                <x-admin.block-manager entity-type="popup" :entity-id="$popup?->getKey()" :sections="old('sections_json', $sectionsData)" name="sections_json" :languages="$languages" :target="request('section')" />
            </x-admin.form-card>
            <x-admin.form-card class="space-y-4">
                <h2 class="font-bold text-lg">Dónde aparece</h2>
                <p class="text-sm">Las condiciones de inclusión se combinan: deben cumplirse todas las dimensiones configuradas. Cualquier exclusión impide mostrar el popup.</p>
                <fieldset class="grid md:grid-cols-2 gap-3 border border-[color:var(--admin-border)] rounded p-4">
                    <legend class="font-bold px-2">Consultar referencias públicas</legend>
                    <label class="space-y-1"><span>Mercado de referencia</span><select x-model="referenceMarket" @change="setReferenceMarket(referenceMarket)" class="admin-control w-full"><template x-for="market in referenceMarkets" :key="market.code"><option :value="market.code" x-text="market.name"></option></template></select></label>
                    <label class="space-y-1"><span>Idioma de referencia</span><select x-model="referenceLocale" class="admin-control w-full"><template x-for="language in referenceLanguages()" :key="language.code"><option :value="language.code" x-text="language.name"></option></template></select></label>
                </fieldset>
                @foreach(['include' => 'Incluir', 'exclude' => 'Excluir'] as $kind => $label)
                    <fieldset class="space-y-3 border border-[color:var(--admin-border)] rounded p-4">
                        <legend class="font-bold px-2">{{ $label }}</legend>
                        @include('popups::admin._target-picker', ['conditionKind' => $kind, 'pickerKind' => 'pages'])
                        <label for="popup-types-{{ $kind }}" class="admin-field-label">Tipos de contenido</label>
                        <select id="popup-types-{{ $kind }}" multiple x-model="configuration.conditions.{{ $kind }}.types" class="admin-control w-full">
                            @foreach($popupTypes as $key => $typeLabel)<option value="{{ $key }}">{{ $typeLabel }}</option>@endforeach
                            <template x-for="key in unavailableContentTypes('{{ $kind }}')" :key="key"><option :value="key" x-text="'No disponible: ' + key"></option></template>
                        </select>
                        <p class="text-sm text-[color:var(--admin-muted)]">Sin selección se aplica a cualquier tipo. Las referencias conservadas de módulos ausentes quedan no disponibles.</p>
                        @include('popups::admin._target-picker', ['conditionKind' => $kind, 'pickerKind' => 'taxonomies'])
                        <details class="space-y-3 text-sm">
                            <summary class="cursor-pointer underline">Referencias avanzadas y no resueltas</summary>
                            <p>Los identificadores guardados se conservan aunque su propietario, idioma o mercado no esté disponible. Retíralos solo si quieres cambiar la condición.</p>
                            <label class="block space-y-1"><span>IDs de páginas</span><input class="admin-control w-full" :value="configuration.conditions.{{ $kind }}.pages?.join(', ') || ''" @input="configuration.conditions.{{ $kind }}.pages = pages($event.target.value)" pattern="\s*\d+(\s*,\s*\d+)*\s*|\s*"></label>
                            <label class="block space-y-1"><span>Taxonomías: propietario: ID, ID</span><textarea rows="2" class="admin-control w-full" :value="taxonomyText('{{ $kind }}')" @input="setTaxonomies('{{ $kind }}', $event.target.value)"></textarea></label>
                        </details>
                    </fieldset>
                @endforeach
                <p class="text-sm" role="note">Elige referencias publicadas en el idioma y mercado deseados. Una taxonomía debe estar asociada al contenido visitado. Las referencias que dejan de estar disponibles se conservan y requieren revisión antes de que el popup pueda abrirse.</p>
            </x-admin.form-card>
            <x-admin.form-card class="space-y-4">
                <h2 class="font-bold text-lg">Cuándo se abre</h2>
                <p class="text-sm">Basta con que ocurra uno de los disparadores. La apertura respeta la prioridad, la privacidad y el límite de frecuencia.</p>
                <template x-for="(trigger, index) in configuration.triggers" :key="index">
                    <fieldset class="grid md:grid-cols-3 gap-3 p-3 border border-[color:var(--admin-border)] rounded">
                        <legend class="sr-only" x-text="'Disparador ' + (index + 1)"></legend>
                        <label class="space-y-1"><span>Disparador</span>
                            <select class="admin-control w-full" :value="trigger.type" @change="changeTrigger(index, $event.target.value)">
                                <option value="delay">Tiempo tras cargar</option><option value="scroll">Porcentaje de scroll</option><option value="element">Elemento visible</option><option value="click">Clic en elemento</option><option value="inactivity">Inactividad</option><option value="exit_intent">Intención de salida (escritorio)</option><option value="form_success">Formulario enviado</option>
                            </select>
                        </label>
                        <template x-if="['delay', 'inactivity'].includes(trigger.type)"><label>Segundos<input type="number" min="0" max="86400" x-model.number="trigger.seconds" class="admin-control w-full" required></label></template>
                        <template x-if="trigger.type === 'scroll'"><label>Porcentaje<input type="number" min="0" max="100" x-model.number="trigger.percent" class="admin-control w-full" required></label></template>
                        <template x-if="['element', 'click'].includes(trigger.type)"><label>Selector CSS<input type="text" maxlength="500" x-model="trigger.selector" class="admin-control w-full" required placeholder="#contacto"></label></template>
                        <template x-if="trigger.type === 'form_success'"><label>Slug de formulario (vacío: cualquiera)<input type="text" maxlength="255" x-model="trigger.form_slug" class="admin-control w-full"></label></template>
                        <button type="button" @click="configuration.triggers.splice(index, 1)" :disabled="configuration.triggers.length === 1" class="admin-control self-end">Quitar disparador</button>
                    </fieldset>
                </template>
                <x-admin.button type="button" @click="addTrigger()">Añadir disparador</x-admin.button>
            </x-admin.form-card>
            <x-admin.form-card class="space-y-4">
                <h2 class="font-bold text-lg">Procedencia</h2>
                <label for="popup-referrers" class="admin-field-label">Dominios de referencia (coincidencia exacta, separados por comas)</label>
                <input id="popup-referrers" class="admin-control w-full" :value="configuration.provenance.referrers?.join(', ') || ''" @input="configuration.provenance.referrers = csv($event.target.value)" placeholder="ejemplo.com">
                <div class="grid md:grid-cols-2 gap-3">
                    @foreach(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key)
                        <label>{{ $key }}<input class="admin-control w-full" maxlength="255" :value="configuration.provenance.utm.{{ $key }} || ''" @input="setUtm('{{ $key }}', $event.target.value)"></label>
                    @endforeach
                </div>
                <p class="text-sm">Los valores se comparan en el navegador sin enviarlos a un servicio de seguimiento. Un referente ausente no coincide con un dominio restringido.</p>
            </x-admin.form-card>
        </div>
        <div class="space-y-6">
            <x-admin.form-card class="space-y-4">
                <h2 class="font-bold text-lg">Presentación y publicación</h2>
                <label for="popup-mode" class="admin-field-label">Formato</label>
                <select id="popup-mode" name="mode" class="admin-control w-full">@foreach($popupModes as $key => $label)<option value="{{ $key }}" @selected(old('mode', $popup?->mode ?? 'modal') === $key)>{{ $label }}</option>@endforeach</select>
                <label for="popup-priority" class="admin-field-label">Prioridad (mayor primero)</label>
                <input id="popup-priority" type="number" name="priority" min="-1000" max="1000" required value="{{ old('priority', $popup?->priority ?? 0) }}" class="admin-control w-full">
                <input type="hidden" name="is_active" value="0"><label class="flex gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $popup?->is_active ?? false))> Activado</label>
                <input type="hidden" name="is_published" value="0"><label class="flex gap-2"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $popup?->is_published ?? false))> Publicado</label>
                <label for="popup-starts" class="admin-field-label">Inicio ({{ config('app.timezone') }})</label>
                <input id="popup-starts" type="datetime-local" name="starts_at" value="{{ old('starts_at', $popup?->starts_at?->format('Y-m-d\TH:i')) }}" class="admin-control w-full">
                <label for="popup-ends" class="admin-field-label">Fin exclusivo ({{ config('app.timezone') }})</label>
                <input id="popup-ends" type="datetime-local" name="ends_at" value="{{ old('ends_at', $popup?->ends_at?->format('Y-m-d\TH:i')) }}" class="admin-control w-full">
                <x-admin.market-picker name="market_ids" :selected="old('market_ids', $popup?->markets->pluck('id')->all() ?? [])" :markets="$markets" group="popup-form" />
                <p class="text-sm">Sin mercados asignados: disponible en todos los mercados activos.</p>
                <label for="popup-locales" class="admin-field-label">Idiomas (vacío: todos)</label>
                <select id="popup-locales" multiple x-model="configuration.locales" class="admin-control w-full">@foreach($languages as $language)<option value="{{ $language->code }}">{{ $language->name }}</option>@endforeach</select>
                <label for="popup-devices" class="admin-field-label">Dispositivos (vacío: todos)</label>
                <select id="popup-devices" multiple x-model="configuration.devices" class="admin-control w-full"><option value="desktop">Escritorio (≥1024 px)</option><option value="tablet">Tableta (768–1023 px)</option><option value="mobile">Móvil (&lt;768 px)</option></select>
                <label for="popup-auth" class="admin-field-label">Sesión</label>
                <select id="popup-auth" x-model="configuration.auth" class="admin-control w-full"><option value="any">Cualquiera</option><option value="guest">Visitante sin sesión</option><option value="authenticated">Sesión autenticada</option></select>
            </x-admin.form-card>
            <x-admin.form-card class="space-y-4">
                <h2 class="font-bold text-lg">Frecuencia</h2>
                <label for="popup-frequency" class="admin-field-label">Límite</label>
                <select id="popup-frequency" x-model="configuration.frequency.mode" class="admin-control w-full"><option value="page">Una vez por página (memoria)</option><option value="session">Una vez por sesión de pestaña</option><option value="once">Una vez (máximo un año)</option><option value="cooldown">Intervalo entre impresiones</option></select>
                <div x-show="configuration.frequency.mode === 'cooldown'"><label for="popup-cooldown">Intervalo en segundos</label><input id="popup-cooldown" type="number" min="1" max="31536000" x-model.number="configuration.frequency.seconds" class="admin-control w-full"></div>
                <div x-show="configuration.frequency.mode !== 'page'" class="space-y-2">
                    <label for="popup-purpose" class="admin-field-label">Finalidad del almacenamiento</label>
                    <select id="popup-purpose" x-model="configuration.frequency.purpose" class="admin-control w-full"><option value="preferences">Preferencias</option><option value="analytics">Análisis</option><option value="marketing">Marketing</option></select>
                    <p class="text-sm">Solo se abre cuando el mecanismo común autoriza esta finalidad. Sin autorización ni almacenamiento disponible, esta configuración queda inactiva. No se crea una cookie propia.</p>
                </div>
                <x-admin.button variant="primary" type="submit">Guardar popup</x-admin.button>
                @if($popup)<a href="{{ route('admin.popups.preview', $popup) }}" target="_blank" rel="noopener" class="block underline">Simular versión editorial</a>@endif
            </x-admin.form-card>
        </div>
    </div>
</form>
