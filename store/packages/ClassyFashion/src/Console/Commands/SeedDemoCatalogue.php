<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Repositories\DemoCatalogueRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Preview or explicitly import the licensed client testing catalogue.
 */
class SeedDemoCatalogue extends Command
{
    protected $signature = 'classy:seed-demo-catalogue {manifest : Path to the sourced JSON manifest} {--execute : Import visible demo listings with zero stock}';

    protected $description = 'Validate or seed 100 licensed demo products without replacing existing products.';

    /**
     * Check every original exists before starting the database transaction.
     */
    public function handle(DemoCatalogueRepository $catalogue): int
    {
        $manifest = json_decode(file_get_contents($this->argument('manifest')), true, 512, JSON_THROW_ON_ERROR);
        $rows = $catalogue->validate($manifest);

        if (! $this->option('execute')) {
            $this->info(count($rows).' demo products validated; no changes made. Add --execute to import.');

            return self::SUCCESS;
        }

        $existing = array_flip(Storage::disk('public')->allFiles('products/demo-catalogue-20261103'));

        foreach ($rows as $row) {
            if (! isset($existing[$row['image_path']])) {
                throw new RuntimeException('Upload the licensed original first: '.$row['image_path']);
            }
        }

        $result = $catalogue->import($manifest);
        $this->info(count($result['created']).' created; '.count($result['skipped']).' existing products left unchanged.');

        return self::SUCCESS;
    }
}
