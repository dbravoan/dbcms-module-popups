<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Http\Controllers\Admin;

use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Http\Adapters\FrontPageResponseMapper;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Middleware\SecurityHeaders;
use DbCms\Core\Models\Market;
use DbCms\Modules\Popups\Models\Popup;
use DbCms\Modules\Popups\Services\PopupAdministration;
use DbCms\Modules\Popups\Services\PopupEditorialPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PopupPreviewGetController extends Controller
{
    public function __invoke(Request $request, Popup $popup, PopupAdministration $service, PopupEditorialPreview $preview, FrontPageResponseMapper $responses): Response
    {
        $input = $request->validate(['market' => 'nullable|integer|exists:markets,id', 'locale' => 'nullable|string|max:10']);
        $market = Market::query()->where('is_active', true)->when(isset($input['market']), fn ($q) => $q->whereKey($input['market']))->orderByDesc('is_default')->firstOrFail();
        $locale = (string) ($input['locale'] ?? $market->default_locale);
        abort_unless($market->languages()->where('code', $locale)->where('is_active', true)->exists(), 404);
        $request->attributes->set('cms.editorial_preview', true);
        $request->attributes->set('market', $market);
        app()->setLocale($locale);
        $page = $preview->render($service->forEditor($popup), new PublicContentContext($market, $locale));

        return response($responses->view($page))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Content-Security-Policy', app(SecurityHeaders::class)->previewPolicy());
    }
}
