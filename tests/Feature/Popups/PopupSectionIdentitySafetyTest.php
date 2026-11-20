<?php

declare(strict_types=1);

use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionEditorService;
use DbCms\Core\Services\Capabilities\ContentSectionActions;
use DbCms\Modules\Popups\Models\Popup;
use Tests\Support\CmsFixtures;
use Tests\Support\PopupsComposition;

uses(PopupsComposition::class);

it('carries the popup ordering locale through PopupRequest and the shared form DTO', function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->actingAs(User::where('email', 'admin@db-cms.io')->firstOrFail());
    $configuration = ['triggers' => [['type' => 'delay', 'seconds' => 2]], 'frequency' => ['mode' => 'page']];
    $popup = Popup::create(['mode' => 'modal', 'is_active' => true, 'is_published' => false, 'priority' => 0, 'configuration' => $configuration]);
    $popup->translations()->create(['locale' => 'es', 'title' => 'Popup', 'slug' => 'popup']);
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'popup', 'id' => $popup->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ]]);
    $payload = app(SectionEditorService::class)->payload($popup->fresh());
    $payload['en'] = array_reverse($payload['en']);
    $this->put(route('admin.popups.update', $popup), ['translations' => ['es' => ['title' => 'Popup']], 'mode' => 'modal', 'priority' => 0, 'configuration' => json_encode($configuration), 'is_active' => true, 'is_published' => false,
        'sections_json' => json_encode($payload, JSON_THROW_ON_ERROR), 'sections_order_locale' => 'en'])
        ->assertRedirect(route('admin.popups.index'))->assertSessionHasNoErrors();
    $result = $actions->sectionsGet(['type' => 'popup', 'id' => $popup->id]);
    expect(array_column($result['sections'], 'id'))->toBe(array_reverse(array_column($initial['sections'], 'id')))
        ->and($result['sections'][1]['translations']['es']['text'])->toBe('Español A')
        ->and($result['sections'][1]['translations']['en']['text'])->toBe('English A');
});
