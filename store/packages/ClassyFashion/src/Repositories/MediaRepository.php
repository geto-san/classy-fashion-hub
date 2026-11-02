<?php

namespace ClassyFashion\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Read and update media references without assuming a local filesystem.
 */
class MediaRepository
{
    /**
     * Resolve the product name and gallery position for missing originals.
     */
    public function productImages(): iterable
    {
        return DB::table('product_images')
            ->leftJoin('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'product_images.product_id')
                    ->where('product_flat.locale', 'en');
            })
            ->select('product_images.path', 'product_images.position', 'product_flat.name')
            ->get();
    }

    /**
     * Find the default channel brand assets.
     */
    public function channel(): ?object
    {
        return DB::table('channels')->where('id', 1)->first();
    }

    /**
     * Save a brand reference after its upload has succeeded.
     */
    public function updateChannelImage(string $column, string $path): void
    {
        if (! in_array($column, ['logo', 'favicon'], true)) {
            throw new \InvalidArgumentException('Unsupported channel image column.');
        }

        DB::table('channels')->where('id', 1)->update([$column => $path]);
    }

    /**
     * Find one localized name for every product.
     */
    public function products(): iterable
    {
        return DB::table('product_flat')->where('locale', 'en')->select('product_id', 'name')->get()->unique('product_id');
    }

    /**
     * Read the currently attached gallery in order.
     */
    public function gallery(int $productId): array
    {
        return DB::table('product_images')->where('product_id', $productId)->orderBy('position')->pluck('path')->all();
    }

    /**
     * Replace references atomically after all desired originals have uploaded.
     */
    public function replaceGallery(int $productId, array $paths): void
    {
        DB::transaction(function () use ($productId, $paths) {
            DB::table('product_images')->where('product_id', $productId)->delete();

            foreach ($paths as $position => $path) {
                DB::table('product_images')->insert([
                    'type' => null,
                    'path' => $path,
                    'product_id' => $productId,
                    'position' => $position,
                ]);
            }
        });
    }

    /**
     * Enumerate stored originals, including private attachments and theme/configuration content.
     */
    public function references(): array
    {
        $references = [];
        $columns = [
            ['product_images', 'path', 'public'],
            ['product_videos', 'path', 'public'],
            ['product_attribute_values', 'text_value', 'public'],
            ['categories', 'logo_path', 'public'],
            ['categories', 'banner_path', 'public'],
            ['channels', 'logo', 'public'],
            ['channels', 'favicon', 'public'],
            ['attribute_options', 'swatch_value', 'public'],
            ['locales', 'logo_path', 'public'],
            ['product_review_attachments', 'path', 'public'],
            ['rma_images', 'path', 'private'],
            ['rma_messages', 'attachment_path', 'private'],
            ['product_downloadable_links', 'file', 'private'],
            ['product_downloadable_samples', 'file', 'private'],
            ['core_config', 'value', 'public'],
            ['theme_section_translations', 'options', 'public'],
            ['theme_section_translations', 'draft_options', 'public'],
        ];

        foreach ($columns as [$table, $column, $disk]) {
            $query = DB::table($table)->whereNotNull($column);

            if ($table === 'product_attribute_values') {
                $query->whereIn('attribute_id', DB::table('attributes')->select('id')->whereIn('type', ['image', 'file']));
            }

            foreach ($query->pluck($column) as $value) {
                preg_match_all('~(?:https?://[^\s"<>]+/storage/|/storage/)?(?:images/storefront|themes|products|categories|channels|storefront|locales|reviews|attribute-options|configurations|theme-sections|rmas)/[^\s"<>]+?\.(?:jpe?g|png|webp|gif|svg|avif|ico|pdf|zip|mp4|webm)~i', str_replace('\\/', '/', (string) $value), $matches);

                $direct = ! in_array($table, ['core_config', 'theme_section_translations'], true);

                if ($direct && ! str_starts_with((string) $value, 'http') && (str_contains((string) $value, '/') || str_contains((string) $value, '.'))) {
                    $matches[0][] = (string) $value;
                }

                foreach ($matches[0] as $original) {
                    $path = preg_replace('~^(?:https?://[^/]+)?/storage/~', '', $original);
                    $references[$disk.':'.$path] = [
                        'disk' => $disk,
                        'path' => $path,
                        'legacy_url' => str_starts_with($original, 'http'),
                    ];
                }
            }
        }

        return array_values($references);
    }
}
