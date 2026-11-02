<?php

namespace ClassyFashion\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Exercise real credentials before copying business media.
 */
class CheckStorage extends Command
{
    protected $signature = 'classy:check-storage {disk=public}';

    protected $description = 'Verify upload, read, URL delivery, existence, and deletion on a media disk.';

    /**
     * Use a unique test original and remove it even when delivery verification fails.
     */
    public function handle(): int
    {
        $disk = Storage::disk($this->argument('disk'));
        $path = 'migration-check/'.Str::uuid().'.png';
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');

        try {
            if (! $disk->put($path, $bytes) || ! $disk->exists($path) || $disk->get($path) !== $bytes) {
                throw new RuntimeException('Upload, existence, or original-byte read check failed.');
            }

            if (config('filesystems.disks.'.$this->argument('disk').'.visibility') === 'public') {
                $url = $disk->url($path);

                if (! str_starts_with($url, 'https://') || Http::timeout(30)->get($url)->throw()->body() !== $bytes) {
                    throw new RuntimeException('HTTPS delivery URL does not return the original file.');
                }
            }
        } finally {
            $disk->delete($path);
        }

        if ($disk->exists($path)) {
            throw new RuntimeException('Deleted test original still exists.');
        }

        $this->info('Storage contract passed. Test original deleted.');

        return self::SUCCESS;
    }
}
