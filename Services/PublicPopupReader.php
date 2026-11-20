<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Services;

use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Services\Content\PublicTranslation;
use DbCms\Modules\Popups\DTOs\PopupContext;
use DbCms\Modules\Popups\Models\Popup;
use Illuminate\Database\Eloquent\Builder;

final class PublicPopupReader
{
    public function __construct(private readonly ModuleManager $modules, private readonly PopupEligibility $eligibility, private readonly SectionRendererService $sections) {}

    /**
     * @return list<array<string,mixed>> */
    public function forContext(PopupContext $context): array
    {
        // This precedes every popup query, including translation and section queries.
        if (! $this->modules->isModuleEnabled('popups') || ! $context->market->is_active
            || ! $context->market->languages()->where('code', $context->locale)->where('is_active', true)->exists()) {
            return [];
        }
        $popups = Popup::query()->published()->forMarket($context->market)
            ->whereHas('translations', fn (Builder $q) => $q->where('locale', $context->locale)->where(fn (Builder $row) => $row->whereNull('market_id')->orWhere('market_id', $context->market->id)))
            ->with(['translations' => fn ($q) => $q->where('locale', $context->locale)->where(fn ($row) => $row->whereNull('market_id')->orWhere('market_id', $context->market->id))])
            ->orderByDesc('priority')->orderBy('id')->get();
        $result = [];
        foreach ($popups as $popup) {
            if (! $this->eligibility->matches($popup->configuration, $context)) {
                continue;
            }
            $popup->load(['sections' => fn ($q) => $q->where('is_active', true)->where('status', 'published')->with('translations')]);
            $prepared = $this->prepare($popup, $context);
            if ($prepared !== null) {
                $result[] = $prepared;
            }
        }

        return $result;
    }

    /** Uses already hydrated snapshot relations for a private simulation.
     * @return array<string,mixed>|null */
    public function prepare(Popup $popup, PopupContext $context, bool $preview = false): ?array
    {
        $translation = PublicTranslation::resolve($popup, $context->locale, $context->market);
        if ($translation === null || trim((string) $translation->getAttribute('title')) === '') {
            return null;
        }
        $html = [];
        foreach ($popup->sections as $section) {
            if (! $section->is_active || (! $preview && $section->status !== 'published')) {
                continue;
            }
            $html[] = $this->sections->renderSection($section, $context->market, $context->locale);
        }

        return [
            'id' => 'popup-'.$popup->id.($preview ? '-preview' : ''), 'entityId' => $popup->id,
            'title' => (string) $translation->getAttribute('title'), 'mode' => $popup->mode,
            'priority' => $popup->priority, 'html' => implode("\n", $html),
            'configuration' => $popup->configuration, 'preview' => $preview,
            'schedule' => ['starts' => $popup->starts_at?->getTimestamp(), 'ends' => $popup->ends_at?->getTimestamp()],
            'unavailable' => $this->eligibility->unavailable($popup->configuration, $context),
        ];
    }
}
