<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Support\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Restore media missing from the (possibly ephemeral) local disk.
 *
 * Product images are regenerated as branded placeholders; the brand
 * logo/favicon are restored from the committed copies in public/images.
 * Safe to run on every boot: existing files are skipped.
 *
 * Usage: php artisan classy:repair-images [--dry-run]
 */
class RepairImages extends Command
{
    protected $signature = 'classy:repair-images {--dry-run : Report missing files without restoring}';

    protected $description = 'Restore missing local media files (redeploy-safe).';

    public function handle(): int
    {
        $disk = Storage::disk('public');

        $restored = 0;
        $missingUploads = 0;

        $images = DB::table('product_images')
            ->leftJoin('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'product_images.product_id')
                    ->where('product_flat.locale', 'en');
            })
            ->select('product_images.path', 'product_flat.name')
            ->get();

        foreach ($images as $image) {
            if ($disk->exists($image->path)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $missingUploads++;

                continue;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'classy').'.png';

            ProductImage::placeholder($tmp, (string) ($image->name ?: 'Classy Fashion Hub'));

            $disk->put($image->path, file_get_contents($tmp));

            @unlink($tmp);

            $restored++;
        }

        foreach (['logo' => 'brand-logo.png', 'favicon' => 'brand-favicon.png'] as $column => $file) {
            $channel = DB::table('channels')->where('id', 1)->first();

            $path = $channel?->{$column};

            if ($path && $disk->exists($path)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $missingUploads++;

                continue;
            }

            $target = 'channel/cfh-'.($column === 'logo' ? 'logo-brandkit.png' : 'favicon-brandkit.png');

            $disk->put($target, file_get_contents(public_path('images/'.$file)));

            DB::table('channels')->where('id', 1)->update([$column => $target]);

            $restored++;
        }

        $this->info($this->option('dry-run')
            ? "{$missingUploads} file(s) would be restored."
            : "{$restored} file(s) restored, present files skipped.");

        return self::SUCCESS;
    }
}
