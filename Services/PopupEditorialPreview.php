<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Services;

use DbCms\Core\Content\Contracts\EditorialPreviewHandlerInterface;
use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Modules\Popups\DTOs\PopupContext;
use DbCms\Modules\Popups\Models\Popup;
use Illuminate\Database\Eloquent\Model;

final class PopupEditorialPreview implements EditorialPreviewHandlerInterface
{
    public function __construct(private readonly PublicPopupReader $reader, private readonly FrontViewResolver $views) {}

    public function render(Model $entity, PublicContentContext $context): FrontPageData
    {
        if (! $entity instanceof Popup) {
            return FrontPageData::missing();
        }
        $popup = $this->reader->prepare($entity, new PopupContext($context->market, $context->locale), preview: true);
        if ($popup === null) {
            return FrontPageData::missing();
        }

        return FrontPageData::view($this->views->resolve('popups.preview', 'popups::public.preview'), ['popupOverlays' => [$popup], 'popupSimulation' => $popup, 'currentMarket' => $context->market, 'currentLocale' => $context->locale]);
    }
}
