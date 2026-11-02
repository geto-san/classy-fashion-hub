<?php

namespace ClassyFashion\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copy or independently export a whole disk with byte-for-byte verification.
 */
class CopyMedia extends Command
{
    protected $signature = 'classy:copy-media {source} {target} {--execute : Copy missing files; existing files must match}';

    protected $description = 'Copy a media disk while preserving paths and verifying every original.';

    /**
     * Never delete source files or silently overwrite conflicting target uploads.
     */
    public function handle(): int
    {
        if ($this->argument('source') === $this->argument('target')) {
            throw new RuntimeException('Source and target disks must differ.');
        }

        $source = Storage::disk($this->argument('source'));
        $target = Storage::disk($this->argument('target'));
        $count = 0;
        $bytes = 0;
        $missing = 0;

        foreach ($source->allFiles() as $path) {
            $original = $source->get($path);

            if (! $target->exists($path)) {
                if (! $this->option('execute')) {
                    $missing++;
                    $this->line('Missing: '.$path);

                    continue;
                }

                if (! $target->put($path, $original)) {
                    throw new RuntimeException('Upload failed: '.$path);
                }
            }

            if (! hash_equals(hash('sha256', $original), hash('sha256', $target->get($path)))) {
                throw new RuntimeException('Content mismatch; source preserved: '.$path);
            }

            $count++;
            $bytes += strlen($original);
        }

        $this->info("Verified {$count} files, {$bytes} bytes; {$missing} missing files.");

        return $missing === 0 ? self::SUCCESS : self::FAILURE;
    }
}
