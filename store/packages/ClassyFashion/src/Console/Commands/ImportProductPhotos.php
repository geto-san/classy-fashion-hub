<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Repositories\MediaRepository;
use ClassyFashion\Support\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Replace placeholder (or older) product images with the real photos in
 * database/seeders/product-images. Matching is by product name, e.g.
 * "Denim Jacket" -> denim-jacket.jpg, denim-jacket-2.jpg.
 *
 * Idempotent: a photo whose content is already attached is left alone, and
 * products without a supplied photo are never touched.
 *
 * Usage: php artisan classy:import-photos [--dry-run]
 */
class ImportProductPhotos extends Command
{
    protected $signature = 'classy:import-photos {--dry-run : Report what would change without changing it}';

    protected $description = 'Attach real product photos from database/seeders/product-images.';

    /**
     * Upload originals before changing gallery references.
     */
    public function handle(MediaRepository $media): int
    {
        $disk = Storage::disk('public');

        $products = $media->products();

        $changed = 0;
        $missing = [];

        foreach ($products as $product) {
            $photos = ProductImage::photos((string) $product->name);

            if ($photos === []) {
                $missing[] = $product->name;

                continue;
            }

            $wanted = array_map(
                fn (string $file) => 'products/'.$product->product_id.'/cfh-'.substr(md5_file($file), 0, 12).'.'.pathinfo($file, PATHINFO_EXTENSION),
                $photos
            );

            $current = $media->gallery($product->product_id);

            if ($current === $wanted) {
                continue;
            }

            $changed++;

            if ($this->option('dry-run')) {
                $this->line("would update: {$product->name}");

                continue;
            }

            foreach ($photos as $position => $file) {
                $disk->put($wanted[$position], file_get_contents($file));
            }

            $media->replaceGallery($product->product_id, $wanted);

            foreach (array_diff($current, $wanted) as $old) {
                $disk->delete($old);
            }
        }

        $this->info("{$changed} product(s) ".($this->option('dry-run') ? 'would be ' : '').'updated with real photos.');

        if ($missing !== []) {
            $this->line(count($missing).' product(s) still use placeholders: '.implode(', ', $missing));
        }

        return self::SUCCESS;
    }
}
