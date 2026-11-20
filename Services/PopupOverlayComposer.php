<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Services;

use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Modules\Popups\DTOs\PopupContext;
use Illuminate\View\View;

final class PopupOverlayComposer
{
    public function __construct(private readonly ModuleManager $modules, private readonly PublicPopupReader $reader, private readonly FrontViewResolver $views) {}

    public function compose(View $view): void
    {
        $view->with(['popupOverlays' => [], 'popupOverlayView' => null]);
        if (! $this->modules->isModuleEnabled('popups') || (request()->attributes->get('cms.editorial_preview', false) || isset($view->getData()['popupSimulation']))) {
            return;
        }
        $market = request()->attributes->get('market') ?? (app()->bound('current.market') ? app('current.market') : null);
        if (! $market instanceof Market) {
            return;
        }
        $overlays = $this->reader->forContext(PopupContext::fromRequest(request(), $market, app()->getLocale()));
        if ($overlays !== []) {
            $view->with(['popupOverlays' => $overlays, 'popupOverlayView' => $this->views->resolve('popups.overlays', 'popups::public.index')]);
        }
    }
}
