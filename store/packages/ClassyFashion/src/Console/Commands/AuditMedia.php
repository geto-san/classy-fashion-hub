<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Repositories\MediaRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Check catalogue, configuration, theme, and private attachment references before cutover.
 */
class AuditMedia extends Command
{
    protected $signature = 'classy:audit-media {--http : Verify public HTTPS delivery in addition to storage existence}';

    protected $description = 'Verify database media references without repairing or replacing originals.';

    /**
     * Distinguish bundled assets, missing originals, and URLs tied to the previous host.
     */
    public function handle(MediaRepository $repository): int
    {
        $failed = 0;
        $checked = 0;

        foreach ($repository->references() as $reference) {
            $checked++;
            $path = $reference['path'];
            $disk = Storage::disk($reference['disk']);

            if ($reference['legacy_url']) {
                $this->error('Reference still contains the old storage hostname: '.$path);
                $failed++;
            }

            if (! $disk->exists($path)) {
                if ($reference['disk'] === 'public' && is_file(public_path($path))) {
                    continue;
                }

                $this->error('Missing '.$reference['disk'].' original: '.$path);
                $failed++;

                continue;
            }

            if ($this->option('http') && $reference['disk'] === 'public') {
                $url = $disk->url($path);

                if (! str_starts_with($url, 'https://') || ! Http::timeout(30)->head($url)->successful()) {
                    $this->error('Public HTTPS delivery failed: '.$path);
                    $failed++;
                }
            }
        }

        $this->info("Checked {$checked} distinct media references; {$failed} failures.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
