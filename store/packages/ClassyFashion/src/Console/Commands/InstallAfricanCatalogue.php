<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Repositories\AfricanCatalogueRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Validate or explicitly install the reviewed African retailer collection.
 */
class InstallAfricanCatalogue extends Command
{
    protected $signature = 'classy:install-african-catalogue {--execute : Install the reviewed African coursework products}';

    protected $description = 'Validate the person-free African collection and its balanced department counts.';

    /**
     * Verify media before any database changes and keep installation opt-in.
     */
    public function handle(AfricanCatalogueRepository $catalogue): int
    {
        $manifest = json_decode(file_get_contents(__DIR__.'/../../Resources/african-catalogue/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $rows = $catalogue->validate($manifest);

        if (! $this->option('execute')) {
            $this->info(count($rows).' African products validated across 24 collections of five or six. No changes made.');

            return self::SUCCESS;
        }

        $files = array_flip(Storage::disk('public')->allFiles('products/african-20261108'));

        foreach ($rows as $row) {
            if (! isset($files[$row['image_path']])) {
                throw new RuntimeException('Upload the reviewed product photograph first: '.$row['image_path']);
            }
        }

        $this->info(json_encode($catalogue->import($manifest), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
