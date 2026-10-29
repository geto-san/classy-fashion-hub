<?php

use function Pest\Laravel\get;

it('serves the v2 storefront theme and chrome on the shop', function () {
    get(route('shop.home.index'))
        ->assertOk()
        ->assertSee('/css/classy-theme.css', false)
        ->assertSee('cfh-announce', false)
        ->assertSee('cfh-footer', false)
        ->assertSee('UGX 5,000', false)
        ->assertSee('Webale nnyo', false);

    get('/css/classy-theme.css')->assertOk();
});

it('keeps the storefront theme out of the admin panel', function () {
    get(route('admin.session.create'))
        ->assertOk()
        ->assertDontSee('/css/classy-theme.css', false);
});

it('defines the brand tokens and Ugandan flag accent in the theme', function () {
    $css = file_get_contents(public_path('css/classy-theme.css'));

    expect($css)->toContain('--cfh-noir')
        ->and($css)->toContain('--cfh-flag-yellow')
        ->and($css)->toContain('.cfh-card')
        ->and($css)->toContain('.cfh-footer');
});
