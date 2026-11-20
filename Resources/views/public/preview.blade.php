@extends(app(\DbCms\Core\Services\Cms\FrontViewResolver::class)->layout())

@section('content')
    <main class="max-w-4xl mx-auto p-8 space-y-6">
        <h1 class="text-3xl font-bold">{{ __('Simulación del popup') }}: {{ $popupSimulation['title'] }}</h1>
        <p>{{ __('Esta previsualización no registra impresiones ni utiliza almacenamiento del visitante. Los bloques reflejan el mercado e idioma seleccionados.') }}</p>
        <p>{{ __('Las condiciones de página y taxonomía se comprueban en una página pública con contexto aportado por su propietario.') }}</p>
        @if($popupSimulation['unavailable'])
            <p role="status">{{ __('Contexto no disponible para esta simulación') }}: {{ implode(', ', $popupSimulation['unavailable']) }}</p>
        @endif
        <button type="button" data-popup-simulate="{{ $popupSimulation['id'] }}" class="px-5 py-3 border rounded">{{ __('Abrir simulación') }}</button>
        <dl class="grid grid-cols-2 gap-3">
            <dt>{{ __('Presentación') }}</dt><dd>{{ $popupSimulation['mode'] }}</dd>
            <dt>{{ __('Prioridad') }}</dt><dd>{{ $popupSimulation['priority'] }}</dd>
        </dl>
        <details>
            <summary>{{ __('Condiciones y disparadores configurados') }}</summary>
            <pre class="overflow-auto p-4">{{ json_encode($popupSimulation['configuration'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    </main>
    @include('popups::public.index')
@endsection
