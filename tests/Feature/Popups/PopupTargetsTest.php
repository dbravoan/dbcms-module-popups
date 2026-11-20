<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Modules\Blog\Models\Category;
use DbCms\Modules\Popups\Services\PopupTargetReader;
use Illuminate\Support\Facades\DB;
use Tests\Support\CmsFixtures;
use Tests\Support\PopupsBlogComposition;

uses(PopupsBlogComposition::class);

beforeEach(function (): void {
    $this->withoutVite();
    CmsFixtures::localization(['es', 'en'], ['es', 'mx']);
    CmsFixtures::identities(['admin' => [], 'viewer' => ['access:admin']]);
    $this->market = Market::where('code', 'es')->firstOrFail();
    $this->actor = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->targetUrl = fn (array $input = []): string => route('admin.popups.targets', $input + ['type' => 'page', 'market' => 'es', 'locale' => 'es']);
    $this->targetPage = function (string $title, bool $published = true, string $locale = 'es', string $market = 'es'): Page {
        $page = Page::create(['is_published' => $published, 'published_at' => now()->subMinute()]);
        $page->translations()->create(['locale' => $locale, 'title' => $title, 'slug' => 'reference-'.$page->id]);
        $page->markets()->sync([Market::where('code', $market)->firstOrFail()->id]);

        return $page;
    };
});

test('popup target lookup requires resource permission and caps each named page', function (): void {
    $this->actingAs(User::where('email', 'viewer@db-cms.io')->firstOrFail());
    $this->getJson(($this->targetUrl)())->assertForbidden();
    $this->actingAs($this->actor);
    $this->getJson(($this->targetUrl)(['limit' => 51]))->assertUnprocessable()->assertJsonValidationErrors('limit');
    $this->getJson(($this->targetUrl)(['type' => 'popup']))->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->getJson(($this->targetUrl)(['market' => 'missing']))->assertUnprocessable()->assertJsonValidationErrors('locale');
    CmsModule::where('identifier', 'popups')->update(['is_active' => false]);
    $this->getJson(($this->targetUrl)())->assertNotFound();
});

test('named page targets preserve publication locale market and bounded cursor filtering', function (): void {
    $this->actingAs($this->actor);
    $first = ($this->targetPage)('Página pública primera');
    $second = ($this->targetPage)('Página pública segunda');
    $draft = ($this->targetPage)('Página pública secreta', false);
    ($this->targetPage)('Página pública de otro mercado', true, 'es', 'mx');
    ($this->targetPage)('Página pública de otro idioma', true, 'en');
    $this->getJson(($this->targetUrl)(['search' => 'pública', 'limit' => 1]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('items.0.id', $first->id)->assertJsonPath('items.0.title', 'Página pública primera')->assertJsonPath('next_offset', 1)->assertJsonCount(1, 'items');
    $this->getJson(($this->targetUrl)(['search' => 'pública', 'offset' => 1, 'limit' => 1]))->assertOk()->assertJsonPath('items.0.id', $second->id)->assertJsonPath('next_offset', null)->assertJsonCount(1, 'items');
    $this->getJson(($this->targetUrl)(['ids' => [(string) $draft->id]]))->assertOk()->assertJsonPath('items', []);
    $regional = ($this->targetPage)('Nombre global español');
    $regional->translations()->create(['locale' => 'es', 'market_id' => Market::where('code', 'mx')->firstOrFail()->id, 'title' => 'Nombre regional mexicano', 'slug' => 'regional-'.$regional->id]);
    $this->getJson(($this->targetUrl)(['ids' => [(string) $regional->id]]))->assertOk()->assertJsonPath('items.0.title', 'Nombre global español');
    $this->get(route('admin.popups.create'))->assertOk()->assertSee('Buscar por nombre')->assertSee('Selección guardada')->assertSee('Referencias avanzadas y no resueltas');
});

test('taxonomy names come from the public owner and disabled owners never query their entities', function (): void {
    $this->actingAs($this->actor);
    $category = Category::create(['is_active' => true]);
    $category->translations()->create(['locale' => 'es', 'name' => 'Categoría pública con nombre', 'slug' => 'public-category']);
    $category->markets()->sync([$this->market->id]);
    $private = Category::create(['is_active' => false]);
    $private->translations()->create(['locale' => 'es', 'name' => 'Categoría secreta', 'slug' => 'private-category']);
    $this->getJson(($this->targetUrl)(['type' => 'blog_category']))->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $category->id)->assertJsonPath('items.0.title', 'Categoría pública con nombre');
    $types = collect(app(PopupTargetReader::class)->types());
    expect($types->firstWhere('type', 'blog_category')['label'])->toContain('Categoría del blog')->toContain('Blog');
    CmsModule::where('identifier', 'blog')->update(['is_active' => false]);
    DB::enableQueryLog();
    $this->getJson(($this->targetUrl)(['type' => 'blog_category']))->assertUnprocessable()->assertJsonValidationErrors('type');
    expect(collect(app(PopupTargetReader::class)->types())->pluck('type')->all())->not->toContain('blog_category');
    $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
    DB::disableQueryLog();
    expect($queries)->not->toMatch('/(?:from|join) [`"]?(?:posts|categories|category_translations)/i');
});
