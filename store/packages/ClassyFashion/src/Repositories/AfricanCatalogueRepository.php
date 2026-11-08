<?php

namespace ClassyFashion\Repositories;

use ClassyFashion\Support\Photography;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Install a balanced, visually reviewed African coursework collection.
 */
class AfricanCatalogueRepository extends CourseworkCatalogueRepository
{
    /**
     * Require distinct retailer references and photographs without people.
     */
    public function validate(array $manifest): array
    {
        $rows = $manifest['products'] ?? [];
        $expected = ['women' => 31, 'men' => 31, 'footwear' => 30, 'accessories' => 30];
        $counts = array_count_values(array_column($rows, 'department'));
        $collectionCounts = array_count_values(array_column($rows, 'collection'));
        $distribution = array_values($collectionCounts);
        sort($distribution);

        if (($manifest['purpose'] ?? '') !== 'african-coursework-catalogue'
            || count($rows) !== 122
            || count($manifest['collections'] ?? []) !== 24
            || count($collectionCounts) !== 24
            || $distribution !== array_merge(array_fill(0, 22, 5), [6, 6])
            || array_diff_assoc($expected, $counts)
            || array_diff_key($counts, $expected)) {
            throw new InvalidArgumentException('Exactly 122 reviewed products across 24 balanced collections and four departments are required.');
        }

        foreach ($rows as $row) {
            $host = parse_url($row['image_source_url'] ?? '', PHP_URL_HOST);
            $collection = $manifest['collections'][$row['collection'] ?? ''] ?? [];

            if (! preg_match('/^CFH-AFR-(WOMEN|MEN|FOOTWEAR|ACCESSORIES)-\d{3}$/', $row['sku'] ?? '')
                || strlen($row['name'] ?? '') < 5
                || ! is_int($row['price'] ?? null) || $row['price'] < 1000
                || ($collection['department'] ?? null) !== ($row['department'] ?? null)
                || ! in_array($host, ['www.jumia.co.ke', 'www.jumia.com.gh', 'www.jumia.com.ng', 'kikoy.com'], true)
                || ! filter_var($row['image_source_url'] ?? '', FILTER_VALIDATE_URL)
                || ($row['image_usage'] ?? '') !== 'coursework-retailer-reference'
                || ($row['review']['contains_people'] ?? null) !== false
                || ($row['gallery'] ?? null) !== []
                || ! in_array($row['review']['style'] ?? '', ['African', 'East African'], true)
                || ! preg_match('/^[a-f0-9]{64}$/', $row['image_sha256'] ?? '')
                || ! preg_match('~^products/african-20261108/[a-z0-9-]+\.(jpg|png|webp)$~', $row['image_path'] ?? '')
                || ! Photography::allows($row['image_path'] ?? '')
                || min($row['image_width'] ?? 0, $row['image_height'] ?? 0) < 300) {
                throw new InvalidArgumentException('Invalid product, source record, department or photograph review.');
            }
        }

        foreach (['sku', 'url_key', 'image_sha256', 'image_source_url'] as $key) {
            if (count(array_unique(array_column($rows, $key))) !== count($rows)) {
                throw new InvalidArgumentException('African catalogue '.$key.' values must be unique.');
            }
        }

        return $rows;
    }

    /**
     * Preserve business records and retire categories from the withdrawn stock batch.
     */
    public function import(array $manifest): array
    {
        $this->validate($manifest);

        return DB::transaction(function () use ($manifest) {
            $result = parent::import($manifest);

            if (! $result['created'] && ! $result['updated']) {
                return $result;
            }

            $this->publishCategoryPhotography($manifest);

            $previous = json_decode(file_get_contents(__DIR__.'/../Resources/coursework-catalogue/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $retired = array_diff(
                array_merge(array_keys($previous['departments']), array_keys($previous['collections']), array_keys($previous['groups'] ?? []), ['casual-wear-men', 'casual-wear', 'formal-wear-men', 'formal-wear']),
                array_merge(array_keys($manifest['departments']), array_keys($manifest['collections'])),
            );

            foreach ($retired as $slug) {
                if ($category = $this->categories->findBySlug($slug)) {
                    $category->forceFill(['status' => 0, 'logo_path' => null, 'banner_path' => null])->save();
                }
            }

            return $result;
        });
    }

    /**
     * Give every department and collection a matching reviewed product photograph.
     */
    protected function publishCategoryPhotography(array $manifest): void
    {
        foreach (['women' => 'dresses', 'men' => 'everyday', 'footwear' => 'outerwear', 'accessories' => 'caps'] as $slug => $slot) {
            $path = $manifest['presentation']['images'][$slot]['path'];
            $category = $this->categories->findBySlug($slug);
            $category->forceFill(['logo_path' => $path, 'banner_path' => $path])->save();
        }

        foreach ($manifest['collections'] as $slug => $collection) {
            $row = collect($manifest['products'])->firstWhere('collection', $slug);
            $category = $this->categories->findBySlug($slug);
            $category->forceFill(['logo_path' => $row['image_path'], 'banner_path' => $row['image_path']])->save();
        }
    }
}
