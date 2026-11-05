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
 * Prepare a complete academic catalogue through the normal Bagisto writers.
 */
class CourseworkCatalogueRepository
{
    /**
     * Retain existing product identities when replacing the earlier sample batch.
     */
    public function __construct(
        protected ProductRepository $products,
        protected CategoryRepository $categories,
        protected ChannelRepository $channels,
        protected MediaRepository $media,
    ) {}

    /**
     * Require readable names, distinct photographs and documented reuse rights.
     */
    public function validate(array $manifest): array
    {
        $rows = $manifest['products'] ?? [];

        if (($manifest['purpose'] ?? '') !== 'academic-presentation' || count($rows) < 100 || count($rows) > 250) {
            throw new InvalidArgumentException('A reviewed academic catalogue with 100–250 products is required.');
        }

        foreach ($rows as $row) {
            if (! preg_match('/^CFH-[A-Z0-9-]{4,50}$/', $row['sku'] ?? '')
                || preg_match('/demo|sample|testing/i', $row['name'] ?? '')
                || strlen($row['name'] ?? '') < 5
                || ! is_int($row['price'] ?? null) || $row['price'] < 1000
                || ! isset($manifest['collections'][$row['collection'] ?? ''])
                || ! in_array($row['image_license'] ?? '', ['Burst', 'Unsplash', 'CC0-1.0', 'CC-BY-4.0'], true)
                || ! filter_var($row['image_source_url'] ?? '', FILTER_VALIDATE_URL)
                || ! preg_match('/^[a-f0-9]{64}$/', $row['image_sha256'] ?? '')
                || ! preg_match('~^products/catalogue-20261104/[a-z0-9-]+\.(jpg|webp|png)$~', $row['image_path'] ?? '')
                || min($row['image_width'] ?? 0, $row['image_height'] ?? 0) < 800
                || max($row['image_width'] ?? 0, $row['image_height'] ?? 0) < 1200) {
                throw new InvalidArgumentException('Invalid catalogue entry, photograph quality or reuse record.');
            }
        }

        foreach (['sku', 'image_sha256', 'url_key'] as $key) {
            if (count(array_unique(array_column($rows, $key))) !== count($rows)) {
                throw new InvalidArgumentException('Catalogue '.$key.' values must be unique.');
            }
        }

        return $rows;
    }

    /**
     * Install presentation stock without changing customers, orders or staff accounts.
     */
    public function import(array $manifest): array
    {
        if (! config('classy.coursework_mode')) {
            throw new RuntimeException('Presentation stock requires coursework mode.');
        }

        $rows = $this->validate($manifest);
        $existing = $this->products->getModel()->whereIn('sku', array_column($rows, 'sku'))->count();

        if ($existing === count($rows)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => $existing];
        }

        $channel = $this->channels->findOneByField('code', 'default');
        $inventory = $channel?->inventory_sources->firstWhere('status', 1);

        if (! $channel || ! $inventory || $channel->base_currency->code !== 'UGX') {
            throw new RuntimeException('An active UGX channel and inventory source are required.');
        }

        return DB::transaction(function () use ($manifest, $rows, $channel, $inventory) {
            $departments = [];
            $groups = [];
            $collections = [];
            $report = ['created' => 0, 'updated' => 0, 'skipped' => 0];

            foreach ($manifest['departments'] as $slug => $data) {
                $departments[$slug] = $this->category($slug, $data, $channel->root_category_id);
            }

            foreach ($manifest['collections'] as $slug => $data) {
                if (isset($data['parent_group']) && ! isset($groups[$data['parent_group']])) {
                    $group = $manifest['groups'][$data['parent_group']];
                    $groups[$data['parent_group']] = $this->category($data['parent_group'], $group, $departments[$group['department']]->id);
                }

                $parent = isset($data['parent_group']) ? $groups[$data['parent_group']]->id : $departments[$data['department']]->id;
                $collections[$slug] = $this->category($slug, $data, $parent);
            }

            foreach (['formal-wear-men', 'formal-wear', 'casual-wear-men', 'casual-wear'] as $slug) {
                $existing = $this->categories->findBySlug($slug);

                if ($existing && $existing->parent_id === $channel->root_category_id) {
                    $this->categories->update(['parent_id' => $departments['men']->id], $existing->id);
                }
            }

            $casual = $this->categories->findBySlug('casual-wear-men') ?? $this->categories->findBySlug('casual-wear');
            $createdAt = now()->startOfSecond();

            foreach ($rows as $position => $row) {
                $product = $this->products->findOneByField('sku', $row['sku']);

                if ($product) {
                    $report['skipped']++;

                    continue;
                }

                if (! $product && isset($row['previous_sku'])) {
                    $product = $this->products->findOneByField('sku', $row['previous_sku']);
                }

                if ($product) {
                    $report['updated']++;
                } else {
                    $product = $this->products->create(['type' => 'simple', 'attribute_family_id' => 1, 'sku' => $row['sku']]);
                    $report['created']++;
                }

                $product->forceFill(['created_at' => $createdAt->copy()->subSeconds(count($rows) - $position)])->save();
                $collection = $collections[$row['collection']];
                $department = $departments[$manifest['collections'][$row['collection']]['department']];
                $categoryIds = [$collection->id, $department->id, $collection->parent_id];

                if ($department->id === $departments['men']->id && $row['collection'] !== 'tailoring' && $casual) {
                    $categoryIds[] = $casual->id;
                }

                $product = $this->products->update([
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'url_key' => $row['url_key'] ?? Str::slug($row['name']),
                    'short_description' => '<p>'.e($row['short_description']).'</p>',
                    'description' => '<p>'.e($row['description']).'</p>',
                    'price' => $row['price'],
                    'cost' => $row['cost'],
                    'weight' => $row['weight'] ?? 0.5,
                    'status' => 1,
                    'visible_individually' => 1,
                    'new' => 1,
                    'featured' => $row['featured'] ?? 0,
                    'manage_stock' => 1,
                    'inventories' => [$inventory->id => 20],
                    'categories' => array_values(array_unique($categoryIds)),
                    'channels' => [$channel->id],
                    'locale' => 'en',
                    'channel' => $channel->code,
                ], $product->id);
                $paths = array_merge([$row['image_path']], array_column($row['gallery'] ?? [], 'path'));
                $this->media->replaceGallery($product->id, $paths);
                Event::dispatch('catalog.product.update.after', $product);
            }

            if (isset($manifest['presentation'])) {
                app(CourseworkProfileRepository::class)->publish($manifest['presentation'], $channel->id);
            }

            return $report;
        });
    }

    /**
     * Reuse earlier category identities and keep all names free of presentation labels.
     */
    protected function category(string $slug, array $data, int $parentId): mixed
    {
        $category = $this->categories->findBySlug($slug);

        foreach ($data['previous_slugs'] ?? [] as $previous) {
            $category ??= $this->categories->findBySlug($previous);
        }

        $payload = [
            'parent_id' => $parentId,
            'position' => $data['position'] ?? 1,
            'status' => 1,
            'display_mode' => 'products_only',
            'en' => [
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'],
                'meta_title' => $data['name'].' | Classy Fashion Hub',
                'meta_description' => $data['description'],
            ],
        ];

        return $category
            ? $this->categories->update($payload, $category->id)
            : $this->categories->create($payload);
    }
}
