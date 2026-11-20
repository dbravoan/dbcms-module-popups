<?php

declare(strict_types=1);

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Modules\ContentAudit\Services\EditorialWorkflow;
use DbCms\Modules\Popups\DTOs\PopupContext;
use DbCms\Modules\Popups\Models\Popup;
use DbCms\Modules\Popups\Models\PopupTranslation;
use DbCms\Modules\Popups\Services\PopupCookieDisclosure;
use DbCms\Modules\Popups\Services\PopupEditorialPreview;
use DbCms\Modules\Popups\Services\PopupEligibility;
use DbCms\Modules\Popups\Services\PublicPopupReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;
use Tests\Support\PopupsAuditComposition;

uses(PopupsAuditComposition::class);

beforeEach(function (): void {
    $this->withoutVite();
    CmsFixtures::localization(['es', 'en'], ['es', 'mx']);
    CmsFixtures::identities(['admin' => [], 'viewer' => ['access:admin']]);
    $this->market = Market::where('code', 'es')->firstOrFail();
    $this->actor = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->popupConfig = ['triggers' => [['type' => 'delay', 'seconds' => 2]], 'frequency' => ['mode' => 'page']];
    $this->makePopup = function (array $attributes = [], array $configuration = []): Popup {
        $popup = Popup::create($attributes + ['mode' => 'modal', 'is_active' => true, 'is_published' => true, 'published_at' => now()->subMinute(), 'priority' => 0, 'configuration' => $configuration ?: $this->popupConfig]);
        $popup->translations()->create(['locale' => 'es', 'title' => 'Aviso '.$popup->id, 'slug' => 'aviso-'.$popup->id]);

        return $popup;
    };
});

test('popup routes require both module availability and the resource permission', function (): void {
    $popup = ($this->makePopup)();
    $this->get(route('admin.popups.index'))->assertRedirect(route('admin.login'));
    $this->actingAs(User::where('email', 'viewer@db-cms.io')->firstOrFail());
    foreach (['index' => [], 'create' => [], 'edit' => [$popup], 'preview' => [$popup]] as $route => $args) {
        $this->get(route('admin.popups.'.$route, $args))->assertForbidden();
    }
    $this->post(route('admin.popups.store'), [])->assertForbidden();
    $this->put(route('admin.popups.update', $popup), [])->assertForbidden();
    $this->delete(route('admin.popups.destroy', $popup))->assertForbidden();
    $this->actingAs($this->actor);
    CmsModule::where('identifier', 'popups')->update(['is_active' => false]);
    $this->get(route('admin.popups.index'))->assertNotFound();
});

test('popup CRUD uses the canonical blocks writer and published updates preserve live snapshots', function (): void {
    $this->actingAs($this->actor);
    $data = ['mode' => 'side', 'priority' => 5, 'configuration' => json_encode($this->popupConfig), 'is_active' => '1', 'is_published' => '1', 'translations' => ['es' => ['title' => 'Aviso vivo']], 'market_ids' => [$this->market->id], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => '<p>Bloque vivo</p>']]])];
    $this->post(route('admin.popups.store'), $data)->assertRedirect(route('admin.popups.index'))->assertSessionHasNoErrors();
    $popup = Popup::firstOrFail();
    expect($popup->mode)->toBe('side')->and($popup->published_at)->not->toBeNull();
    expect($popup->sections()->firstOrFail()->translations()->firstOrFail()->content_json)->toMatchArray(['content' => '<p>Bloque vivo</p>']);
    $this->get(route('admin.popups.create'))->assertOk()->assertSee('Dónde aparece')->assertSee('Cuándo se abre');
    $this->get(route('admin.popups.edit', $popup))->assertOk()->assertSee('name="sections_json"', false);
    $updated = array_replace($data, ['priority' => 9, 'translations' => ['es' => ['title' => 'Aviso pendiente']], 'sections_json' => json_encode(['es' => [['type' => 'text', 'content' => '<p>Bloque pendiente</p>']]])]);
    $this->put(route('admin.popups.update', $popup), $updated)->assertRedirect(route('admin.popups.index'))->assertSessionHasNoErrors();
    expect($popup->fresh()->priority)->toBe(5)->and($popup->fresh()->translations->first()->title)->toBe('Aviso vivo');
    $this->get(route('admin.popups.edit', $popup))->assertViewHas('popup', fn (Popup $draft): bool => $draft->priority === 9 && $draft->translations->first()->title === 'Aviso pendiente');
    app(EditorialWorkflow::class)->approve($popup->fresh(), $this->actor);
    expect($popup->fresh()->priority)->toBe(9)->and($popup->fresh()->translations->first()->title)->toBe('Aviso pendiente');
    $snapshot = app(ContentSnapshotService::class)->capture($popup->fresh());
    expect($snapshot['snapshot_version'])->toBe(3)->and($snapshot['market_ids'])->toBe([$this->market->id]);
    $this->delete(route('admin.popups.destroy', $popup))->assertRedirect(route('admin.popups.index'));
    expect(Popup::count())->toBe(0);
});

test('generic entity writes reject incomplete triggers and unsupported persistence configuration transactionally', function (array $configuration): void {
    expect(fn () => app(EntityMutationService::class)->create('popup', ['attributes' => ['configuration' => $configuration], 'translations' => ['es' => ['title' => 'Invalid']]], $this->actor))->toThrow(ValidationException::class);
    expect(Popup::count())->toBe(0);
})->with([
    [['triggers' => [['type' => 'click']]]],
    [['triggers' => [['type' => 'scroll', 'percent' => 101]]]],
    [['triggers' => [['type' => 'delay', 'seconds' => 1]], 'frequency' => ['mode' => 'once']]],
    [['triggers' => [['type' => 'delay', 'seconds' => 1]], 'frequency' => ['mode' => 'cooldown', 'purpose' => 'preferences']]],
]);

test('public popup selection requires enabled publication schedule locale market and deterministic priority', function (): void {
    $second = ($this->makePopup)(['priority' => 3]);
    $first = ($this->makePopup)(['priority' => 8]);
    $tie = ($this->makePopup)(['priority' => 3]);
    ($this->makePopup)(['is_active' => false]);
    ($this->makePopup)(['is_published' => false]);
    ($this->makePopup)(['starts_at' => now()->addHour()]);
    ($this->makePopup)(['ends_at' => now()]);
    ($this->makePopup)(['published_at' => now()->addHour()]);
    $otherMarket = ($this->makePopup)();
    $otherMarket->markets()->sync([Market::where('code', 'mx')->firstOrFail()->id]);
    $otherLanguage = ($this->makePopup)();
    $otherLanguage->translations()->update(['locale' => 'en']);
    $onlyRegionalElsewhere = ($this->makePopup)();
    $onlyRegionalElsewhere->translations()->update(['market_id' => Market::where('code', 'mx')->firstOrFail()->id]);
    $context = new PopupContext($this->market, 'es');
    expect(array_column(app(PublicPopupReader::class)->forContext($context), 'entityId'))->toBe([$first->id, $second->id, $tie->id]);
});

test('exclusions win and applicable condition dimensions are AND while unsupported owner context is explicit', function (): void {
    $eligibility = app(PopupEligibility::class);
    $context = new PopupContext($this->market, 'es', 'page', 12, ['blog_category' => ['4']]);
    $configuration = ['conditions' => ['include' => ['pages' => [12], 'types' => ['page'], 'taxonomies' => ['blog_category' => ['4', '8']]]]];
    expect($eligibility->matches($configuration, $context))->toBeTrue();
    $configuration['conditions']['exclude'] = ['pages' => [12]];
    expect($eligibility->matches($configuration, $context))->toBeFalse();
    unset($configuration['conditions']['exclude']);
    $configuration['conditions']['include']['taxonomies']['blog_category'] = ['9'];
    expect($eligibility->matches($configuration, $context))->toBeFalse();
    $configuration['conditions']['include']['types'] = ['private_inquiry'];
    expect($eligibility->unavailable($configuration, $context))->toContain('unavailable_type:private_inquiry');
    $configuration['conditions']['include'] = ['taxonomies' => ['missing_owner' => ['4']]];
    expect($eligibility->matches($configuration, $context))->toBeFalse()->and($eligibility->unavailable($configuration, $context))->toBe(['unavailable_taxonomy:missing_owner']);
    expect($eligibility->matches(['auth' => 'authenticated'], $context))->toBeFalse();
    expect($eligibility->matches(['locales' => ['en']], $context))->toBeFalse();
});

test('disabled popup module never queries owned data or contributes storage disclosures', function (): void {
    ($this->makePopup)();
    CmsModule::where('identifier', 'popups')->update(['is_active' => false]);
    DB::enableQueryLog();
    expect(app(PublicPopupReader::class)->forContext(new PopupContext($this->market, 'es')))->toBe([]);
    expect(app(PopupCookieDisclosure::class)->categories('es'))->toBe([]);
    expect(app(PopupCookieDisclosure::class)->revisionData())->toBe([]);
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();
    expect(implode("\n", $queries))->not->toMatch('/(?:from|join) [`"]?(?:popups|popup_translations|popup_sections)/i');
});

test('snapshot popup preview uses hydrated content and never re-queries a published popup', function (): void {
    $popup = new Popup(['mode' => 'modal', 'priority' => 7, 'configuration' => $this->popupConfig]);
    $popup->id = 77;
    $popup->setRelation('translations', collect([new PopupTranslation(['locale' => 'es', 'title' => 'Aviso hidratado'])]));
    $popup->setRelation('sections', collect());
    DB::enableQueryLog();
    $preview = app(PopupEditorialPreview::class)->render($popup, new PublicContentContext($this->market, 'es'));
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();
    expect($preview->data['popupSimulation']['title'])->toBe('Aviso hidratado')->and($preview->data['popupSimulation']['preview'])->toBeTrue();
    expect(implode("\n", $queries))->not->toContain('popups');
    expect(app(PopupEditorialPreview::class)->render($popup, new PublicContentContext($this->market, 'en'))->viewName)->toBeNull();
});

test('popup storage disclosure contains only configured cross-visit storage', function (): void {
    ($this->makePopup)();
    expect(app(PopupCookieDisclosure::class)->categories('en'))->toBe([]);
    $popup = ($this->makePopup)([], ['triggers' => [['type' => 'delay', 'seconds' => 1]], 'frequency' => ['mode' => 'session', 'purpose' => 'preferences']]);
    $categories = app(PopupCookieDisclosure::class)->categories('en');
    expect($categories['preferences']['required'])->toBeFalse()->and($categories['preferences']['cookies'][0]['name'])->toBe('dbcms.popup.v1.'.$popup->id);
    expect(app(EntitySchemaRegistryInterface::class)->get('popup')->searchable)->toBeFalse();
});

test('private popup simulation renders content without consuming a live impression', function (): void {
    $this->actingAs($this->actor);
    $popup = ($this->makePopup)();
    $response = $this->get(route('admin.popups.preview', ['popup' => $popup, 'market' => $this->market->id, 'locale' => 'es']));
    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('data-popup-simulate', false)->assertSee('popup-'.$popup->id.'-preview', false);
    expect(Popup::count())->toBe(1);
});
