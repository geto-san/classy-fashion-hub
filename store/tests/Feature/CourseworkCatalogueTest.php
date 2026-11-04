<?php

use ClassyFashion\Filesystem\CloudinaryAdapter;
use ClassyFashion\Repositories\CourseworkCatalogueRepository;
use ClassyFashion\Repositories\DemoCatalogueRepository;
use ClassyFashion\Support\CloudProductImages;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Sales\Repositories\OrderRepository;

use function Pest\Laravel\getJson;

beforeEach(function () {
    config(['classy.coursework_mode' => true, 'queue.default' => 'sync', 'repository.cache.enabled' => false, 'responsecache.enabled' => false]);
    $this->manifest = json_decode(file_get_contents(base_path('packages/ClassyFashion/src/Resources/coursework-catalogue/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
});

it('rejects low resolution or repeated primary photographs before writing', function () {
    $catalogue = app(CourseworkCatalogueRepository::class);
    $bad = $this->manifest;
    $bad['products'][0]['image_width'] = 320;
    expect(fn () => $catalogue->validate($bad))->toThrow(InvalidArgumentException::class);
    $bad = $this->manifest;
    $bad['products'][1]['image_sha256'] = $bad['products'][0]['image_sha256'];
    expect(fn () => $catalogue->validate($bad))->toThrow(InvalidArgumentException::class);
});

it('replaces the earlier sample batch while retaining identities and existing business records', function () {
    $old = json_decode(file_get_contents(base_path('packages/ClassyFashion/src/Resources/demo-catalogue/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
    app(DemoCatalogueRepository::class)->import($old);
    $products = app(ProductRepository::class);
    $originalId = $products->findOneByField('sku', 'CFH-DEMO-20261103-001')->id;
    $before = collect([CustomerRepository::class, OrderRepository::class, ChannelRepository::class])->mapWithKeys(fn ($class) => [$class => app($class)->getModel()->orderBy('id')->get()->toJson()]);
    $catalogue = app(CourseworkCatalogueRepository::class);
    $report = $catalogue->import($this->manifest);

    expect($report['updated'])->toBe(100);
    expect($report['created'])->toBe(count($this->manifest['products']) - 100);
    expect($products->findOneByField('sku', $this->manifest['products'][0]['sku'])->id)->toBe($originalId);
    expect($products->getModel()->where('sku', 'like', 'CFH-DEMO-%')->count())->toBe(0);

    foreach ($this->manifest['products'] as $row) {
        $product = $products->findOneByField('sku', $row['sku']);
        expect($product->name)->toBe($row['name']);
        expect($product->getTypeInstance()->isSaleable())->toBeTrue();
        expect($product->images)->toHaveCount(1 + count($row['gallery']));
    }

    $seen = [];
    foreach (['arrivals', 'layers', 'everyday', 'finishing'] as $collection) {
        $data = getJson(route('classy.collections.index', $collection))->assertOk()->json('data');
        expect($data)->not->toBeEmpty();
        $ids = array_column($data, 'id');
        expect(array_intersect($ids, $seen))->toBe([]);
        $seen = array_merge($seen, $ids);
    }

    $first = $products->findOneByField('sku', $this->manifest['products'][0]['sku']);
    $products->update(['name' => 'Client edited this style', 'locale' => 'en', 'channel' => 'default', 'inventories' => [1 => 7]], $first->id);
    $again = $catalogue->import($this->manifest);
    expect($again['skipped'])->toBe(count($this->manifest['products']));
    expect($products->find($first->id)->name)->toBe('Client edited this style');
    expect((int) $products->find($first->id)->inventories->sum('qty'))->toBe(7);

    foreach ($before as $class => $snapshot) {
        expect(app($class)->getModel()->orderBy('id')->get()->toJson())->toBe($snapshot);
    }
});

it('uses direct CDN image URLs without checking every hosted gallery object', function () {
    $adapter = Mockery::mock(CloudinaryAdapter::class);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('getAdapter')->andReturn($adapter);
    $disk->shouldReceive('url')->with('products/example.jpg')->andReturn('https://res.cloudinary.com/example/raw/upload/products/example.jpg');
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);
    $product = (object) ['name' => 'Classic Shirt', 'sku' => 'CFH-SHIRT-001', 'parent_id' => null, 'images' => collect([(object) ['path' => 'products/example.jpg', 'alt' => null]])];

    expect(product_image())->toBeInstanceOf(CloudProductImages::class);
    $base = product_image()->getProductBaseImage($product);
    expect($base['large_image_url'])->toBe('https://res.cloudinary.com/example/raw/upload/products/example.jpg');
    expect($base['alt'])->toBe('Classic Shirt');
    expect(product_image()->getGalleryImages($product))->toHaveCount(1);
});

it('does not repeat products between price-sorted pages when prices tie', function () {
    $products = app(ProductRepository::class);
    $expected = [];
    for ($i = 0; $i < 30; $i++) {
        $sku = 'CFH-PRICE-TIE-'.$i;
        $product = $products->create(['type' => 'simple', 'attribute_family_id' => 1, 'sku' => $sku]);
        $product = $products->update([
            'sku' => $sku, 'name' => 'CoursePriceTie '.$i, 'url_key' => strtolower($sku),
            'price' => 35000, 'weight' => 1, 'status' => 1, 'visible_individually' => 1,
            'channels' => [1], 'locale' => 'en', 'channel' => 'default',
            'inventories' => [1 => 10], 'manage_stock' => 1,
        ], $product->id);
        Event::dispatch('catalog.product.update.after', $product);
        $expected[] = $product->id;
    }

    $actual = [];
    foreach ([1, 2, 3] as $page) {
        $rows = getJson(route('shop.api.products.index', ['query' => 'CoursePriceTie', 'sort' => 'price-asc', 'limit' => 12, 'page' => $page]))->assertOk()->json('data');
        $actual = array_merge($actual, array_column($rows, 'id'));
    }
    expect($actual)->toBe($expected);
});
