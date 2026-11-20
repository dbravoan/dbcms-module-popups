<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Services;

use DbCms\Core\DTOs\Content\EntityMutationData;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionEditorService;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Content\EditorialDraftService;
use DbCms\Modules\Popups\Models\Popup;

final class PopupAdministration
{
    public function __construct(private readonly EntityMutationService $mutations, private readonly SectionEditorService $sections, private readonly EditorialDraftService $drafts, private readonly PopupEligibility $eligibility, private readonly PopupTargetReader $targets) {}

    /** @param array<string,mixed> $data */
    public function create(array $data, User $actor): void
    {
        $this->mutations->create('popup', EntityMutationData::fromForm($data)->arguments, $actor);
    }

    /** @param array<string,mixed> $data */
    public function update(Popup $popup, array $data): void
    {
        $arguments = EntityMutationData::fromForm($data)->arguments;
        if ($popup->is_published && ! empty($arguments['attributes']['is_published'])) {
            unset($arguments['attributes']['is_published'], $arguments['attributes']['published_at']);
            if (array_key_exists('is_active', $arguments['attributes']) && (bool) $arguments['attributes']['is_active'] === $popup->is_active) {
                unset($arguments['attributes']['is_active']);
            }
        }
        $this->mutations->update('popup', $popup->getKey(), $arguments);
    }

    public function delete(Popup $popup): void
    {
        $this->mutations->delete('popup', $popup->getKey());
    }

    public function forEditor(Popup $popup): Popup
    {
        $popup->load(['translations', 'sections.translations', 'markets']);
        $draft = $this->drafts->forEditor($popup);
        assert($draft instanceof Popup);

        return $draft;
    }

    /**
     * @return array<string,mixed> */
    public function formData(?Popup $popup = null): array
    {
        $popup = $popup === null ? null : $this->forEditor($popup);

        $markets = Market::with('languages')->where('is_active', true)->get();
        $referenceMarkets = $markets->map(static fn (Market $market): array => ['code' => $market->code, 'name' => $market->name, 'is_default' => $market->is_default, 'default_locale' => $market->default_locale, 'languages' => $market->languages->where('is_active', true)->map(static fn ($language): array => ['code' => $language->code, 'name' => $language->name])->values()->all()])->values()->all();

        return [
            'popup' => $popup,
            'sectionsData' => $popup === null ? [] : $this->sections->payload($popup),
            'markets' => $markets,
            'popupReferenceMarkets' => $referenceMarkets,
            'popupTargetTypes' => $this->targets->types(),
            'languages' => Language::where('is_active', true)->get(),
            'popupTypes' => $this->eligibility->types(),
            'popupModes' => ['modal' => 'Ventana modal', 'fullscreen' => 'Pantalla completa', 'top_bar' => 'Barra superior', 'bottom_bar' => 'Barra inferior', 'side' => 'Panel lateral'],
        ];
    }
}
