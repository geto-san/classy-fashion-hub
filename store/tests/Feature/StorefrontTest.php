<?php

use ClassyFashion\Support\Storefront;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Theme\Repositories\SectionRepository;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    config(['repository.cache.enabled' => false, 'responsecache.enabled' => false]);
});

it('renders the Ethereal campaign with real collection links and product feeds', function () {
    get(route('shop.home.index'))->assertOk()
        ->assertSee('cfh-campaign', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('/images/storefront/classy-collection-campaign-1672w.webp', false)
        ->assertSee('/images/storefront/shop-dresses.webp', false)
        ->assertSee('/images/storefront/shop-caps.webp', false)
        ->assertSee('/images/storefront/shop-collection.webp', false)
        ->assertSee('v-ethereal-products', false)
        ->assertSee('cfh-editorial-splits', false)
        ->assertSee('cfh-editorial-grid', false)
        ->assertSee('cfh-shop-journal', false)
        ->assertDontSee('home-offer', false)
        ->assertDontSee('top-collection-container', false);
});

it('restores the original sections without overwriting their content', function () {
    $sections = app(SectionRepository::class);
    $original = $sections->find(5)->options;
    $sections->massUpdateStatus(['status' => 0], [1, 5, 6, 8, 10, 12]);

    $this->artisan('classy:setup-homepage')->assertSuccessful();

    foreach ([1, 5, 6, 8, 10, 12] as $id) {
        expect((int) $sections->find($id)->status)->toBe(1);
    }

    expect($sections->find(5)->options)->toBe($original);
});

it('keeps collection photographs attached to their descriptions and destinations', function () {
    $collections = Storefront::collections();

    expect($collections)->toHaveCount(3);
    expect($collections[0]['src'])->toBe(Storefront::image('dresses_image'));
    expect($collections[0]['alt'])->toBe(Storefront::get('dresses_alt'));
    expect($collections[1]['link'])->toBe(Storefront::get('caps_link'));
    expect($collections[2]['title'])->toBe(Storefront::get('everyday_title'));
});

it('lets the owner publish text and photos while retaining fields not uploaded', function () {
    Storage::fake('public');
    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    get(route('admin.classy.storefront.index'))->assertOk()->assertSee('Publish changes');

    $data = collect(Storefront::fields())->reject(fn ($type) => $type === 'image')
        ->mapWithKeys(fn ($type, $field) => [$field => $type === 'boolean' ? (int) Storefront::get($field) : Storefront::get($field)])
        ->all();
    $data['hero_title'] = 'Fresh looks from Mbarara';
    $data['hero_image'] = UploadedFile::fake()->image('campaign.webp', 1672, 940);
    $data['dresses_image'] = UploadedFile::fake()->image('shop.jpg', 1200, 900);

    put(route('admin.classy.storefront.update'), $data)->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.classy.storefront.index'));

    $saved = app(CoreConfigRepository::class)->findOneWhere([
        'code' => 'general.content.storefront.hero_title',
        'channel_code' => 'default',
        'locale_code' => 'en',
    ]);
    expect($saved->value)->toBe('Fresh looks from Mbarara');
    expect(Storefront::get('hero_title'))->toBe('Fresh looks from Mbarara');
    Storage::disk('public')->assertExists(Storefront::get('dresses_image'));
    Storage::disk('public')->assertExists(Storefront::get('hero_image'));
    expect(Storefront::image('hero_image'))->not->toContain('/images/storefront/classy-collection-campaign');
    expect(Storefront::collections()[0]['src'])->toBe(Storefront::image('dresses_image'));
    expect(Storefront::image('caps_image'))->toBe('/images/storefront/shop-caps.webp');
});

it('renders an owner campaign without the bundled responsive source set', function () {
    app(CoreConfigRepository::class)->create([
        'channel' => 'default',
        'locale' => 'en',
        'general' => ['content' => ['storefront' => [
            'hero_image' => 'storefront/owner-campaign.webp',
            'hero_alt' => 'The owner collection',
        ]]],
    ]);

    get(route('shop.home.index'))->assertOk()
        ->assertSee(Storefront::image('hero_image'), false)
        ->assertSee('The owner collection')
        ->assertDontSee('classy-collection-campaign-640w.webp', false);
});

it('rejects external or unsafe owner links before publishing', function () {
    expect(Storefront::validLink('/search?query=dress'))->toBeTrue();
    foreach (['https://example.com', '//example.com', '/\\example.com', "/\ntest", 'javascript:alert(1)'] as $link) {
        expect(Storefront::validLink($link))->toBeFalse();
    }
});

it('keeps workers out of homepage publishing and routes technical appearance to the editor', function () {
    $this->actingAs(Admin::where('email', 'worker@classy.local')->firstOrFail(), 'admin');
    get(route('admin.classy.storefront.index'))->assertUnauthorized();
    put(route('admin.classy.storefront.update'), [])->assertUnauthorized();

    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');
    get(route('admin.appearance.themes.index'))->assertRedirect(route('admin.classy.storefront.index'));
    expect(collect(config('menu.admin'))->pluck('key')->filter(fn ($key) => str_starts_with($key, 'appearance')))->toBeEmpty();
});

it('honors the owner assistant switch while retaining the campaign', function () {
    app(CoreConfigRepository::class)->create([
        'channel' => 'default',
        'locale' => 'en',
        'general' => ['content' => ['storefront' => [
            'assistant_enabled' => 0,
        ]]],
    ]);

    get(route('shop.home.index'))->assertOk()->assertSee('cfh-campaign', false);
    get(route('classy.assistant.index'))->assertNotFound();
    \Pest\Laravel\postJson(route('classy.assistant.chat'), ['message' => 'hi'])->assertNotFound();
});
