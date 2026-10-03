<?php

use function Pest\Laravel\get;

it('serves the brand v1.0 head assets on the storefront', function () {
    get(route('shop.home.index'))
        ->assertOk()
        ->assertSee('/brand.css', false)
        ->assertSee('/css/classy-brand.css', false)
        ->assertSee('/favicon.svg', false)
        ->assertSee('og-image.png', false)
        ->assertSee('Classy Fashion Hub — shirts, jackets, shoes and more', false);
});

it('serves the brand stylesheets and icons', function () {
    get('/brand.css')->assertOk();

    expect(file_get_contents(public_path('brand.css')))->toContain('--cfh-noir');

    get('/css/classy-brand.css')->assertOk();

    get('/favicon.ico')->assertOk();

    get('/og-image.png')->assertOk();
});

it('uses the official kit logo and favicon', function () {
    $logo = getimagesize(public_path('images/brand-logo.png'));

    expect($logo[0])->toBeGreaterThanOrEqual(600);

    $favicon = getimagesize(public_path('images/brand-favicon.png'));

    expect($favicon[0])->toBe(48);
});
