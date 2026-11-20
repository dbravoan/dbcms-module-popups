<div data-cms-popups>
    @foreach($popupOverlays as $popupOverlay)
        <script type="application/json" data-popup-config>@json(\Illuminate\Support\Arr::except($popupOverlay, ['html', 'title']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
        <div hidden class="cms-popup" data-cms-overlay data-overlay-id="{{ $popupOverlay['id'] }}"
             data-popup-mode="{{ $popupOverlay['mode'] }}" data-overlay-priority="{{ $popupOverlay['priority'] }}"
             data-overlay-modal="{{ in_array($popupOverlay['mode'], ['top_bar', 'bottom_bar'], true) ? 'false' : 'true' }}"
             role="{{ in_array($popupOverlay['mode'], ['top_bar', 'bottom_bar'], true) ? 'region' : 'dialog' }}"
             @if(! in_array($popupOverlay['mode'], ['top_bar', 'bottom_bar'], true)) aria-modal="true" @endif aria-labelledby="{{ $popupOverlay['id'] }}-title" tabindex="-1">
            <div class="cms-popup__panel" data-overlay-panel>
                <h2 id="{{ $popupOverlay['id'] }}-title" class="cms-popup__title">{{ $popupOverlay['title'] }}</h2>
                <button type="button" class="cms-popup__close" data-cms-overlay-close aria-label="{{ __('admin.common.close') }}">&times;</button>
                {!! $popupOverlay['html'] !!}
            </div>
        </div>
    @endforeach
</div>
