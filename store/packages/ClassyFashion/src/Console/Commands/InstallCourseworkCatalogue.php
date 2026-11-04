<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Repositories\CourseworkCatalogueRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Validate or install the licensed catalogue used for the academic presentation.
 */
class InstallCourseworkCatalogue extends Command
{
    protected $signature = 'classy:install-coursework-catalogue {--execute : Install the reviewed products and presentation stock}';

    protected $description = 'Prepare the fashion catalogue with ordinary product names and categories.';

    /**
     * Keep image acquisition separate from writes to the shop database.
     */
    public function handle(CourseworkCatalogueRepository $catalogue): int
    {
        $manifest = json_decode(file_get_contents(__DIR__.'/../../Resources/coursework-catalogue/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $rows = $catalogue->validate($manifest);

        if (! $this->option('execute')) {
            $this->info(count($rows).' catalogue entries validated; no changes made.');

            return self::SUCCESS;
        }

        $files = array_flip(Storage::disk('public')->allFiles('products/catalogue-20261104'));

        foreach ($rows as $row) {
            if (! isset($files[$row['image_path']])) {
                throw new RuntimeException('Upload the reviewed photograph first: '.$row['image_path']);
            }
        }

        $result = $catalogue->import($manifest);
        $this->info(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
