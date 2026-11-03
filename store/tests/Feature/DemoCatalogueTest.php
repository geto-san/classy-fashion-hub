<?php

use ClassyFashion\Repositories\DemoCatalogueRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Sales\Repositories\OrderRepository;

beforeEach(function () {
    config(['repository.cache.enabled' => false, 'queue.default' => 'sync']);
    $this->manifestPath = base_path('packages/ClassyFashion/src/Resources/demo-catalogue/manifest.json');
    $this->manifest = json_decode(file_get_contents($this->manifestPath), true, 512, JSON_THROW_ON_ERROR);
});

it('previews the catalogue without changing products or needing cloud credentials', function () {
    $before = app(ProductRepository::class)->getModel()->count();

    $this->artisan('classy:seed-demo-catalogue', ['manifest' => $this->manifestPath])->assertSuccessful();

    expect(app(ProductRepository::class)->getModel()->count())->toBe($before);
});

it('rejects duplicate photographs and invalid provenance before writing', function () {
    $catalogue = app(DemoCatalogueRepository::class);
    $bad = $this->manifest;
    $bad['products'][1]['image_sha256'] = $bad['products'][0]['image_sha256'];

    expect(fn () => $catalogue->validate($bad))->toThrow(InvalidArgumentException::class);

    $bad = $this->manifest;
    $bad['products'][0]['image_license'] = 'unknown';

    expect(fn () => $catalogue->validate($bad))->toThrow(InvalidArgumentException::class);
});

it('refuses to seed when an uploaded original is missing', function () {
    Storage::fake('public');

    expect(fn () => $this->artisan('classy:seed-demo-catalogue', [
        'manifest' => $this->manifestPath,
        '--execute' => true,
    ])->run())->toThrow(RuntimeException::class, 'Upload the licensed original first');
});

it('refuses to expose zero-stock demos while backorders are enabled', function () {
    app(CoreConfigRepository::class)->create([
        'channel' => 'default',
        'locale' => 'en',
        'catalog' => ['inventory' => ['stock_options' => ['back_orders' => '1']]],
    ]);
    $before = app(ProductRepository::class)->getModel()->count();

    expect(fn () => app(DemoCatalogueRepository::class)->import($this->manifest))
        ->toThrow(RuntimeException::class, 'backorders disabled');
    expect(app(ProductRepository::class)->getModel()->count())->toBe($before);
});

it('imports 100 non-purchasable listings and preserves existing business data and later edits', function () {
    $repositories = [CustomerRepository::class, OrderRepository::class, CoreConfigRepository::class];
    $before = collect($repositories)->mapWithKeys(fn ($class) => [$class => app($class)->getModel()->orderBy('id')->get()->toJson()]);
    $products = app(ProductRepository::class);
    $count = $products->getModel()->count();
    $catalogue = app(DemoCatalogueRepository::class);
    $result = $catalogue->import($this->manifest);

    expect($result['created'])->toHaveCount(100);
    expect($products->getModel()->count())->toBe($count + 100);

    foreach ($result['created'] as $sku) {
        $product = $products->findOneByField('sku', $sku);
        expect($product->getTypeInstance()->isSaleable())->toBeFalse();
        expect((int) $product->inventories->sum('qty'))->toBe(0);
        expect($product->images)->toHaveCount(1);
    }

    $first = $products->findOneByField('sku', $result['created'][0]);
    $products->update(['name' => '[DEMO] Client edited this item', 'locale' => 'en', 'channel' => 'default'], $first->id);
    $repeat = $catalogue->import($this->manifest);

    expect($repeat['created'])->toBe([]);
    expect($repeat['skipped'])->toHaveCount(100);
    expect($products->find($first->id)->name)->toBe('[DEMO] Client edited this item');
    expect($products->getModel()->count())->toBe($count + 100);

    foreach ($repositories as $class) {
        expect(app($class)->getModel()->orderBy('id')->get()->toJson())->toBe($before[$class]);
    }
});

it('rolls back the entire batch if an indexing event fails', function () {
    $products = app(ProductRepository::class);
    $before = $products->getModel()->count();
    Event::listen('catalog.product.update.after', function ($product) {
        if ($product->sku === 'CFH-DEMO-20261103-002') {
            throw new RuntimeException('Test indexing failure');
        }
    });

    expect(fn () => app(DemoCatalogueRepository::class)->import($this->manifest))
        ->toThrow(RuntimeException::class, 'Test indexing failure');
    expect($products->getModel()->count())->toBe($before);
});
