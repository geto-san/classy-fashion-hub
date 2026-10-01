<?php

use Webkul\User\Models\Admin;

use function Pest\Laravel\get;

it('reports sales totals over the demo orders', function () {
    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    $response = get(route('admin.reporting.sales.stats', ['type' => 'total-sales']))
        ->assertOk();

    $stats = $response->json('statistics');

    expect($stats)->not->toBeEmpty();
});

it('exports the sales report', function () {
    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    $response = get(route('admin.reporting.sales.export', ['type' => 'total-sales', 'format' => 'csv']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('csv');
});
