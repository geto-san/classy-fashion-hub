<?php

namespace ClassyFashion\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copy local media to Cloudinary preserving paths, so flipping
 * FILESYSTEM_DISK=cloudinary never breaks existing image URLs.
 *
 * Usage: php artisan classy:media-to-cloud [--dry-run]
 */
class PushMediaToCloud extends Command
{
    protected $signature = 'classy:media-to-cloud {--dry-run : List missing files without uploading}';

    protected $description = 'Upload local media files to the cloudinary disk, preserving paths.';

    public function handle(): int
    {
        if (! config('filesystems.disks.cloudinary.cloud_name')) {
            $this->error('Cloudinary is not configured (CLOUDINARY_CLOUD_NAME missing).');

            return self::FAILURE;
        }

        $local = Storage::disk('public');
        $cloud = Storage::disk('cloudinary');

        $paths = DB::table('product_images')->pluck('path')
            ->merge(DB::table('channels')->whereNotNull('logo')->pluck('logo'))
            ->merge(DB::table('channels')->whereNotNull('favicon')->pluck('favicon'))
            ->filter()
            ->unique()
            ->values();

        $missing = 0;
        $pushed = 0;

        foreach ($paths as $path) {
            if (! $local->exists($path)) {
                $this->warn("Local file gone, skipped: {$path}");

                continue;
            }

            if ($cloud->exists($path)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $missing++;

                continue;
            }

            $cloud->put($path, $local->get($path), 'public');

            $pushed++;
        }

        $this->info($this->option('dry-run')
            ? "{$missing} file(s) would be uploaded."
            : "{$pushed} file(s) uploaded, already present skipped.");

        return self::SUCCESS;
    }
}
