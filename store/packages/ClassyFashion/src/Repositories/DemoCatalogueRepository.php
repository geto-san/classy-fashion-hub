<?php

namespace ClassyFashion\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\Product\Repositories\ProductRepository;

/**
 * Import isolated, non-purchasable demo products without changing store settings.
 */
class DemoCatalogueRepository
{
    /**
     * Resolve the normal catalogue repositories.
     */
    public function __construct(
        protected ProductRepository $products,
        protected CategoryRepository $categories,
        protected ChannelRepository $channels,
        protected MediaRepository $media,
    ) {}

    /**
     * Reject incomplete manifests before making any changes.
     */
    public function validate(array $manifest): array
    {
        $rows = $manifest['products'] ?? [];

        if (($manifest['purpose'] ?? '') !== 'client-testing' || count($rows) !== 100) {
            throw new InvalidArgumentException('A client-testing manifest with exactly 100 products is required.');
        }

        $skus = [];
        $hashes = [];

        foreach ($rows as $row) {
            if (! preg_match('/^CFH-DEMO-20261103-[0-9]{3}$/', $row['sku'] ?? '')
                || ! str_starts_with($row['name'] ?? '', '[DEMO] ')
                || ! in_array($row['kind'] ?? '', ['dress', 'shirt', 't-shirt', 'pants', 'shoes'], true)
                || ! is_int($row['price'] ?? null) || $row['price'] <= 0
                || ($row['image_license'] ?? '') !== 'CC0-1.0'
                || ! preg_match('~^products/demo-catalogue-20261103/[a-z0-9-]+\.webp$~', $row['image_path'] ?? '')
                || ! preg_match('/^[a-f0-9]{64}$/', $row['image_sha256'] ?? '')
                || ! str_starts_with($row['price_source_url'] ?? '', 'https://www.jumia.ug/')
                || ! str_starts_with($row['image_source_url'] ?? '', 'https://raw.githubusercontent.com/alexeygrigorev/clothing-dataset-small/')
                || ! str_contains($row['description'] ?? '', 'Demo catalogue')) {
                throw new InvalidArgumentException('Invalid demo product or missing provenance.');
            }

            $skus[] = $row['sku'];
            $hashes[] = $row['image_sha256'];
        }

        if (count(array_unique($skus)) !== 100 || count(array_unique($hashes)) !== 100) {
            throw new InvalidArgumentException('Demo SKUs and photographs must be unique.');
        }

        return $rows;
    }

    /**
     * Preserve any existing SKU, including changes the client made after import.
     */
    public function import(array $manifest): array
    {
        $rows = $this->validate($manifest);
        $channel = $this->channels->findOneByField('code', 'default');

        if (! $channel || $channel->base_currency->code !== 'UGX'
            || core()->getConfigData('catalog.inventory.stock_options.back_orders')) {
            throw new RuntimeException('The default channel must use UGX with backorders disabled.');
        }

        $source = $channel->inventory_sources->firstWhere('status', 1);

        if (! $source) {
            throw new RuntimeException('An active inventory source is required.');
        }

        return DB::transaction(function () use ($rows, $channel, $source) {
            $created = [];
            $skipped = [];

            foreach ($rows as $row) {
                if ($this->products->findOneByField('sku', $row['sku'])) {
                    $skipped[] = $row['sku'];

                    continue;
                }

                $category = $this->category($row['kind'], $channel->root_category_id);
                $product = $this->products->create([
                    'type' => 'simple',
                    'attribute_family_id' => 1,
                    'sku' => $row['sku'],
                ]);

                $product = $this->products->update([
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'url_key' => Str::slug($row['sku']),
                    'short_description' => '<p>'.e($row['short_description']).'</p>',
                    'description' => '<p>'.e($row['description']).'</p><p>Photo: CC0 clothing dataset. Example price benchmark: <a href="'.e($row['price_source_url']).'" rel="nofollow noopener">Jumia Uganda category listing</a>. This photograph does not identify a Jumia listing.</p>',
                    'price' => $row['price'],
                    'weight' => 0,
                    'status' => 1,
                    'visible_individually' => 1,
                    'new' => 1,
                    'manage_stock' => 1,
                    'inventories' => [$source->id => 0],
                    'categories' => array_values(array_unique(array_filter([$category->id, $category->parent_id !== $channel->root_category_id ? $category->parent_id : null]))),
                    'channels' => [$channel->id],
                    'locale' => 'en',
                    'channel' => $channel->code,
                ], $product->id);

                $this->media->replaceGallery($product->id, [$row['image_path']]);
                Event::dispatch('catalog.product.update.after', $product);

                if ($product->getTypeInstance()->isSaleable()) {
                    throw new RuntimeException('Demo product unexpectedly became purchasable.');
                }

                $created[] = $product->sku;
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /**
     * Keep demo categories separate from the real shop catalogue.
     */
    protected function category(string $kind, int $rootId): mixed
    {
        $slug = 'demo-'.$kind;
        $category = $this->categories->getModel()->whereHas('translations', fn ($query) => $query->where('slug', $slug)->where('locale', 'en'))->first();

        if ($category) {
            return $category;
        }

        $parentName = match ($kind) {
            'dress' => 'Womens',
            'shirt' => 'Mens',
            default => 'Casual Wear',
        };
        $parent = $this->categories->getModel()->whereHas('translations', fn ($query) => $query->where('name', $parentName)->where('locale', 'en'))->first();
        $name = 'Demo '.match ($kind) {
            'dress' => 'dresses and tops',
            'shirt' => 'shirts',
            't-shirt' => 'T-shirts',
            'pants' => 'trousers',
            default => 'footwear',
        };

        return $this->categories->create([
            'parent_id' => $parent?->id ?? $rootId,
            'position' => 100,
            'status' => 1,
            'display_mode' => 'products_only',
            'en' => [
                'name' => $name,
                'slug' => $slug,
                'description' => 'Demo catalogue for client testing. These items are not for sale.',
                'meta_title' => $name,
                'meta_description' => 'Client testing catalogue.',
            ],
        ]);
    }
}
