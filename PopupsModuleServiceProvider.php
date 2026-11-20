<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Modules\BaseModuleServiceProvider;
use DbCms\Core\Observers\ContentTranslationCacheObserver;
use DbCms\Modules\Popups\Models\Popup;
use DbCms\Modules\Popups\Models\PopupSectionTranslation;
use DbCms\Modules\Popups\Models\PopupTranslation;
use DbCms\Modules\Popups\Schema\PopupEntitySchemas;
use DbCms\Modules\Popups\Services\PopupCookieDisclosure;
use DbCms\Modules\Popups\Services\PopupOverlayComposer;
use Illuminate\Support\Facades\View;

final class PopupsModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'popups';

    protected string $name = 'Popups';

    public function register(): void
    {
        $this->app->singleton(PopupCookieDisclosure::class);
        $this->app->tag(PopupCookieDisclosure::class, 'cms.cookie-disclosures');
        config(['cms.page_cache_models' => array_values(array_unique(array_merge((array) config('cms.page_cache_models', []), [Popup::class])))]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'popups');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->app->make(EntitySchemaRegistryInterface::class)->register(PopupEntitySchemas::popup());
        PopupTranslation::observe(ContentTranslationCacheObserver::class);
        PopupSectionTranslation::observe(ContentTranslationCacheObserver::class);
        View::composer('components.cms.overlays', PopupOverlayComposer::class);
        parent::boot();
    }

    public function getAdminNavigation(): array
    {
        return [['id' => 'popups', 'title' => 'Popups', 'route' => 'admin.popups.index', 'createRoute' => 'admin.popups.create', 'createTooltip' => 'Crear popup', 'icon' => 'window-restore', 'permission' => 'manage:popups']];
    }
}
